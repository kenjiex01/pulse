<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\LuDay;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\RawTimekeepingInandout;
use App\Models\TimekeepingEmployeeConsultationHour;
use App\Support\PayrollAllowanceDeduction;
use App\Support\PayrollPeriodComputation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Faculty allowance on allowance pay periods: expected hours = sum of consultation slot durations
 * for each calendar day in the payroll period that matches the employee's setup (e.g. Mon/Thu only).
 * Actual hours credited from time logs overlapping those slots.
 */
class FacultyConsultationAllowancePayrollService
{
    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
    ) {}

    public function hasConsultationHoursSetup(Employee $employee): bool
    {
        $employee->loadMissing('consultationHours');

        return $employee->consultationHours->isNotEmpty();
    }

  /**
   * Faculty without consultation hours must not receive allowance payroll lines.
   */
    public function shouldSkipAllowanceIncome(Employee $employee, bool $useFacultyPath, ?IncomeType $incomeType): bool
    {
        if (! $useFacultyPath || $incomeType === null) {
            return false;
        }

        if (! PayrollPeriodComputation::isAllowanceIncomeType($incomeType)) {
            return false;
        }

        return ! $this->hasConsultationHoursSetup($employee);
    }

    /**
     * @param  list<array{user_type: ?string, salaries: Collection<int, EmployeeSalary>, use_faculty_path: bool}>  $slices
     */
    public function applyForDetail(
        PayrollBatchDetail $detail,
        PayrollCalendar $calendar,
        Employee $employee,
        array $slices,
    ): void {
        if (! $calendar->computesAllowance()) {
            return;
        }

        $hasFacultySlice = collect($slices)->contains(fn (array $slice) => (bool) ($slice['use_faculty_path'] ?? false));

        if (! $hasFacultySlice) {
            return;
        }

        $employee->loadMissing('consultationHours');
        $from = $calendar->dt_from;
        $to = $calendar->dt_to;

        if ($from === null || $to === null) {
            return;
        }

        foreach ($slices as $slice) {
            if (! ($slice['use_faculty_path'] ?? false)) {
                continue;
            }

            /** @var Collection<int, EmployeeSalary> $salaries */
            $salaries = $slice['salaries'];
            $salary = $salaries->last();

            if ($salary === null) {
                continue;
            }

            $expectedHours = $this->expectedHoursForPeriod($salary, $employee, $from, $to);

            if ($expectedHours <= 0) {
                continue;
            }

            $actualHours = $this->actualConsultationHours((int) $employee->employee_id, $employee, $from, $to);

            if ($actualHours + 0.0001 >= $expectedHours) {
                continue;
            }

            $shortfallHours = round($expectedHours - $actualHours, 4);
            $deductionTypeId = PayrollAllowanceDeduction::deductionTypeId();

            if ($deductionTypeId === null) {
                continue;
            }

            $allowanceIncomes = PayrollIncome::query()
                ->with('incomeType')
                ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
                ->where(function ($query) {
                    $query->where('is_manual', false)->orWhereNull('is_manual');
                })
                ->get()
                ->filter(fn (PayrollIncome $income) => PayrollPeriodComputation::isAllowanceIncomeType($income->incomeType));

            foreach ($allowanceIncomes as $income) {
                $allowanceTotal = round((float) $income->taxable + (float) $income->non_taxable, 2);

                if ($allowanceTotal <= 0) {
                    continue;
                }

                $deductionAmount = round($allowanceTotal * ($shortfallHours / $expectedHours), 2);

                if ($deductionAmount <= 0) {
                    continue;
                }

                PayrollDeduction::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'deduction_type_id' => $deductionTypeId,
                    'hours' => $shortfallHours,
                    'days' => null,
                    'employee_amount' => $deductionAmount,
                    'employer_amount' => 0,
                    'is_manual' => false,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
            }
        }
    }

    public function expectedHoursForPeriod(
        EmployeeSalary $salary,
        Employee $employee,
        CarbonInterface $from,
        CarbonInterface $to,
    ): float {
        unset($salary);

        if (! $this->hasConsultationHoursSetup($employee)) {
            return 0.0;
        }

        return $this->expectedConsultationHoursInPayPeriod($employee, $from, $to);
    }

    public function expectedConsultationHoursInPayPeriod(
        Employee $employee,
        CarbonInterface $from,
        CarbonInterface $to,
    ): float {
        $employee->loadMissing('consultationHours');
        /** @var array<int, float> $hoursByDayId */
        $hoursByDayId = [];

        foreach ($employee->consultationHours->groupBy('day_id') as $dayId => $slots) {
            $hoursByDayId[(int) $dayId] = collect($slots)->sum(
                fn (TimekeepingEmployeeConsultationHour $hour) => $this->slotDurationHours(
                    $hour->time_start,
                    $hour->time_end,
                ),
            );
        }

        if ($hoursByDayId === []) {
            return 0.0;
        }

        $total = 0.0;
        $cursor = CarbonImmutable::parse($from->toDateString());
        $end = CarbonImmutable::parse($to->toDateString());

        while ($cursor->lte($end)) {
            $dayId = LuDay::idFromDate($cursor);
            $total += (float) ($hoursByDayId[$dayId] ?? 0.0);
            $cursor = $cursor->addDay();
        }

        return round($total, 4);
    }

    public function consultationHoursPerScheduledDay(Employee $employee): float
    {
        $employee->loadMissing('consultationHours');

        $hours = $employee->consultationHours;

        if ($hours->isEmpty()) {
            return 0.0;
        }

        $weeklyHours = $this->weeklyConsultationHours($hours);
        $scheduledDays = $hours->pluck('day_id')->unique()->count();

        if ($scheduledDays <= 0 || $weeklyHours <= 0) {
            return 0.0;
        }

        return round($weeklyHours / $scheduledDays, 4);
    }

    /**
     * @param  Collection<int, TimekeepingEmployeeConsultationHour>  $hours
     */
    public function weeklyConsultationHours(Collection $hours): float
    {
        $total = 0.0;

        foreach ($hours as $hour) {
            $duration = $this->slotDurationHours($hour->time_start, $hour->time_end);
            $total += $duration;
        }

        return round($total, 4);
    }

    public function actualConsultationHours(
        int $employeeId,
        Employee $employee,
        CarbonInterface $from,
        CarbonInterface $to,
    ): float {
        $employee->loadMissing('consultationHours');
        $slotsByDayId = $employee->consultationHours->groupBy('day_id');

        if ($slotsByDayId->isEmpty()) {
            return 0.0;
        }

        $dayPunches = $this->timeLogsPayroll->dayPunchesForPeriod($employeeId, $from, $to);
        $total = 0.0;
        $cursor = CarbonImmutable::parse($from->toDateString());
        $end = CarbonImmutable::parse($to->toDateString());

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $dayId = LuDay::idFromDate($cursor);
            /** @var Collection<int, TimekeepingEmployeeConsultationHour>|null $slots */
            $slots = $slotsByDayId->get($dayId);

            if ($slots === null || $slots->isEmpty()) {
                $cursor = $cursor->addDay();

                continue;
            }

            $punches = $dayPunches->get($date, collect());
            $workSpan = $this->workSpanFromPunches($punches, $date);

            if ($workSpan === null) {
                $cursor = $cursor->addDay();

                continue;
            }

            foreach ($slots as $slot) {
                $total += $this->overlapHours(
                    $workSpan['start'],
                    $workSpan['end'],
                    $this->slotStart($cursor, $slot->time_start),
                    $this->slotEnd($cursor, $slot->time_start, $slot->time_end),
                );
            }

            $cursor = $cursor->addDay();
        }

        return round($total, 4);
    }

    private function slotDurationHours(?string $timeStart, ?string $timeEnd): float
    {
        if ($timeStart === null || $timeEnd === null || $timeStart === '' || $timeEnd === '') {
            return 0.0;
        }

        try {
            $start = CarbonImmutable::parse('2000-01-01 '.$this->normalizeTime($timeStart));
            $end = CarbonImmutable::parse('2000-01-01 '.$this->normalizeTime($timeEnd));

            if ($end->lte($start)) {
                $end = $end->addDay();
            }

            return max(0.0, $start->diffInMinutes($end) / 60);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private function slotStart(CarbonImmutable $date, ?string $timeStart): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$this->normalizeTime((string) $timeStart));
    }

    private function slotEnd(CarbonImmutable $date, ?string $timeStart, ?string $timeEnd): CarbonImmutable
    {
        $end = CarbonImmutable::parse($date->toDateString().' '.$this->normalizeTime((string) $timeEnd));

        if ($timeStart !== null && $timeEnd !== null && $end->lte($this->slotStart($date, $timeStart))) {
            return $end->addDay();
        }

        return $end;
    }

    private function overlapHours(
        CarbonImmutable $workStart,
        CarbonImmutable $workEnd,
        CarbonImmutable $slotStart,
        CarbonImmutable $slotEnd,
    ): float {
        $start = $workStart->greaterThan($slotStart) ? $workStart : $slotStart;
        $end = $workEnd->lessThan($slotEnd) ? $workEnd : $slotEnd;

        if ($end->lte($start)) {
            return 0.0;
        }

        return $start->diffInMinutes($end) / 60;
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $punches
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function workSpanFromPunches(Collection $punches, string $date): ?array
    {
        if ($punches->isEmpty()) {
            return null;
        }

        $ordered = $punches->sortBy(fn (RawTimekeepingInandout $punch) => sprintf(
            '%s-%010d',
            $punch->dt_datetime?->format('Y-m-d H:i:s') ?? '',
            (int) $punch->timekeeping_inandout_id,
        ))->values();

        $firstIn = $ordered->first(fn (RawTimekeepingInandout $punch) => (bool) $punch->is_in);
        $lastOut = $ordered->filter(fn (RawTimekeepingInandout $punch) => ! (bool) $punch->is_in)->last();

        if ($firstIn?->dt_datetime === null || $lastOut?->dt_datetime === null) {
            return null;
        }

        return [
            'start' => CarbonImmutable::parse($firstIn->dt_datetime),
            'end' => CarbonImmutable::parse($lastOut->dt_datetime),
        ];
    }
}
