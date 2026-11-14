<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Support\PayrollAllowanceDeduction;
use App\Support\PayrollCalendarWorkingDays;
use App\Support\PayrollPeriodComputation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Staff allowance on allowance pay periods: full allowance unless late, undertime, or absence.
 * ALWD = (allowance / (days_per_period × hours_per_day)) × penalty hours.
 */
class StaffAllowancePayrollService
{
    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly EmployeeSalaryResolverService $salaryResolver,
        private readonly EmployeeOvertimeApprovalService $overtimeApprovals,
    ) {}

    /**
     * @param  list<array{user_type: ?string, salaries: Collection<int, EmployeeSalary>, use_faculty_path: bool}>  $slices
     */
    public function applyForDetail(
        PayrollBatchDetail $detail,
        PayrollCalendar $calendar,
        Employee $employee,
        array $slices,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $shiftCode,
        bool $autoComputeExcessAsOt = false,
    ): void {
        unset($autoComputeExcessAsOt);

        if (! $calendar->computesAllowance()) {
            return;
        }

        $from = $calendar->dt_from;
        $to = $calendar->dt_to;

        if ($from === null || $to === null) {
            return;
        }

        $employeeId = (int) $detail->employee_id;
        $deductionTypeId = PayrollAllowanceDeduction::deductionTypeId();

        if ($deductionTypeId === null) {
            return;
        }

        foreach ($slices as $slice) {
            if ($slice['use_faculty_path'] ?? false) {
                continue;
            }

            /** @var Collection<int, EmployeeSalary> $salaries */
            $salaries = $slice['salaries'];
            $salary = $salaries->last();

            if ($salary === null) {
                continue;
            }

            $periodHours = $this->periodHoursForAllowanceRate($salary);

            if ($periodHours <= 0) {
                continue;
            }

            $attendance = $this->staffAttendanceSnapshot(
                $salaries,
                $employee,
                $employeeId,
                $employee->employee_number,
                $from,
                $to,
                $policy,
                $shiftCode?->time_in,
                $shiftCode?->time_out,
                $shiftCode,
            );

            $penaltyHours = $this->penaltyHours($salary, $employeeId, $calendar, $attendance);

            if ($penaltyHours <= 0) {
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

                $hourlyRate = $allowanceTotal / $periodHours;
                $deductionAmount = round(min($allowanceTotal, $hourlyRate * $penaltyHours), 2);

                if ($deductionAmount <= 0) {
                    continue;
                }

                PayrollDeduction::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'deduction_type_id' => $deductionTypeId,
                    'hours' => $penaltyHours,
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

    public function periodHoursForAllowanceRate(EmployeeSalary $salary): float
    {
        $daysPerPeriod = (float) ($salary->days_per_period ?? 0);
        $hoursPerDay = (float) ($salary->hours_per_day ?? 0);

        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8.0;
        }

        if ($daysPerPeriod <= 0) {
            return 0.0;
        }

        return round($daysPerPeriod * $hoursPerDay, 4);
    }

    /**
     * @param  array<string, mixed>|null  $attendance
     */
    public function penaltyHours(
        EmployeeSalary $salary,
        int $employeeId,
        PayrollCalendar $calendar,
        ?array $attendance,
    ): float {
        if ($attendance === null) {
            return 0.0;
        }

        $lateMinutes = (int) ($attendance['late_minutes'] ?? 0) + (int) ($attendance['break_late_minutes'] ?? 0);
        $undertimeMinutes = (int) ($attendance['undertime_minutes'] ?? 0);
        $absentHours = (float) ($attendance['absent_hours'] ?? 0.0);

        if ($absentHours <= 0 && (float) ($attendance['absent_days'] ?? 0) > 0) {
            $hoursPerDay = (float) ($salary->hours_per_day ?? 0);
            $absentHours = (float) $attendance['absent_days'] * ($hoursPerDay > 0 ? $hoursPerDay : 8.0);
        }

        if ((bool) $salary->use_payroll_calendar_days) {
            $rateDays = PayrollCalendarWorkingDays::forEmployeeSalary(
                $salary,
                $employeeId,
                $calendar->dt_from,
                $calendar->dt_to,
            );
            $unworked = PayrollCalendarWorkingDays::unworkedAbsence(
                $salary,
                $rateDays,
                (float) ($attendance['worked_days'] ?? 0),
                $calendar->dt_from,
                $calendar->dt_to,
                PayrollCalendarWorkingDays::restDayIdsForEmployee($employeeId),
                array_values(array_filter(
                    (array) ($attendance['worked_dates'] ?? []),
                    fn ($date) => is_string($date) && $date !== '',
                )),
            );
            $hoursPerDay = (float) ($salary->hours_per_day ?? 0);
            $absentHours += (float) $unworked['days'] * ($hoursPerDay > 0 ? $hoursPerDay : 8.0);
        }

        $hours = ($lateMinutes / 60) + ($undertimeMinutes / 60) + $absentHours;

        return $hours > 0 ? round($hours, 4) : 0.0;
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return array<string, mixed>|null
     */
    private function staffAttendanceSnapshot(
        Collection $salaries,
        Employee $employee,
        int $employeeId,
        ?string $employeeNumber,
        CarbonInterface $from,
        CarbonInterface $to,
        ?TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?ShiftCode $shiftCode,
    ): ?array {
        $salary = $salaries->last();

        if ($salary === null) {
            return null;
        }

        if ($this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
            $otMinutesByDate = $this->overtimeApprovals->billableMinutesByDate(
                $employeeId,
                $from,
                $to,
                $shiftCode,
            );
            $overtimeMinutes = array_sum($otMinutesByDate);
            $excludeExcessFromBasicHours = $overtimeMinutes > 0;

            $result = $salaries->count() > 1
                ? $this->timeLogsPayroll->computeForPeriodWithSalaries(
                    $salaries,
                    $this->salaryResolver,
                    $employeeId,
                    $from,
                    $to,
                    $policy,
                    $scheduleStart,
                    $scheduleEnd,
                    $shiftCode,
                    excludeExcessFromBasicHours: $excludeExcessFromBasicHours,
                    otMinutesByDate: $otMinutesByDate,
                )
                : $this->timeLogsPayroll->computeForPeriod(
                    $salary,
                    $employeeId,
                    $from,
                    $to,
                    $policy,
                    $scheduleStart,
                    $scheduleEnd,
                    $shiftCode,
                    excludeExcessFromBasicHours: $excludeExcessFromBasicHours,
                    otMinutesByDate: $otMinutesByDate,
                );

            $result['break_late_minutes'] = $this->timeLogsPayroll->totalBreakLateMinutes(
                $employeeId,
                $from,
                $to,
                $policy,
                $shiftCode,
            );

            return $result;
        }

        if (! $this->employeeLoadPayroll->usesEmployeeLoad($salary)) {
            return null;
        }

        return $salaries->count() > 1
            ? $this->employeeLoadPayroll->computeForPeriodWithSalaries(
                $salaries,
                $this->salaryResolver,
                $employeeId,
                $employeeNumber,
                $from,
                $to,
                $policy,
            )
            : $this->employeeLoadPayroll->computeForPeriod(
                $salary,
                $employeeId,
                $employeeNumber,
                $from,
                $to,
                $policy,
            );
    }
}
