<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\EmployeeRequest;
use App\Models\EmployeeRequestPayrollInclusion;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pays pulled employee requests on the payroll batch being processed.
 *
 * A request approved on Nov 12 for work on Sep 6 is paid on the Nov 1–15 batch
 * when that work has not been paid yet. Once a batch includes it, later batches skip it.
 * Reprocessing the same batch calculates it again.
 */
class EmployeeRequestPayrollService
{
    public function __construct(
        private readonly EmployeeRequestPullService $requests,
        private readonly EmployeeOvertimeApprovalService $overtimeApprovals,
        private readonly PayrollOvertimeService $overtimePayroll,
        private readonly EmployeeSalaryResolverService $salaryResolver,
        private readonly EmployeeAttendanceViewService $attendanceView,
        private readonly TimeLogsPayrollService $timeLogs,
    ) {}

    public function applyToDetail(PayrollBatchDetail $detail): int
    {
        $detail->loadMissing([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
        ]);
        $calendar = $detail->payrollBatch?->payrollCalendar;
        $employee = $detail->employee;

        if ($calendar?->dt_from === null || $calendar->dt_to === null || $employee === null) {
            return 0;
        }

        $from = CarbonImmutable::parse($calendar->dt_from->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($calendar->dt_to->toDateString())->endOfDay();
        $employeeId = (int) $employee->employee_id;
        $detailId = (int) $detail->payroll_batch_detail_id;
        $batchId = (int) $detail->payroll_batch_id;

        EmployeeRequestPayrollInclusion::query()
            ->where('payroll_batch_detail_id', $detailId)
            ->update([
                'payroll_batch_id' => null,
                'payroll_batch_detail_id' => null,
                'hours' => null,
                'amount' => null,
                'included_at' => null,
            ]);

        $effects = $this->effectsFor($employeeId, $to);
        $applied = 0;
        $overtime = [];

        foreach ($effects as $effect) {
            if ($effect['type'] === 'overtime') {
                $overtime[$effect['date']][] = $effect;

                continue;
            }

            $applied += $this->applyDatedEffect($detail, $effect, $from, $to, $employeeId, $batchId);
        }

        foreach ($overtime as $date => $group) {
            $applied += $this->applyOvertimeDate($detail, $group, (string) $date, $from, $to, $employeeId, $batchId);
        }

        return $applied;
    }

    /**
     * @param  array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}  $effect
     */
    private function applyDatedEffect(
        PayrollBatchDetail $detail,
        array $effect,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $employeeId,
        int $batchId,
    ): int {
        $row = $this->openRow($effect['key'], (int) $detail->payroll_batch_detail_id);

        if ($row === null) {
            return 0;
        }

        $date = CarbonImmutable::parse($effect['date'])->startOfDay();
        $inside = $date->toDateString() >= $from->toDateString() && $date->toDateString() <= $to->toDateString();
        $salary = $this->salaryOn($employeeId, (int) ($detail->payrollBatch?->payrollCalendar?->pay_type_id ?? 0), $date);

        if ($effect['type'] === 'amendment' && $inside) {
            $this->mark($row, $effect, $detail, $batchId, 0, 0);

            return 0;
        }

        if ($effect['type'] === 'return_to_work' && $inside) {
            $this->mark($row, $effect, $detail, $batchId, 0, 0);

            return 0;
        }

        if ($salary === null) {
            return 0;
        }

        $hourly = $salary->preciseHourlyRate();
        $hoursPerDay = (float) ($salary->hours_per_day ?? 0);

        if ($hourly === null || $hourly <= 0 || $hoursPerDay <= 0) {
            return 0;
        }

        $paidBasic = $this->paidBasicHours($employeeId, $date, $batchId);

        if ($effect['type'] === 'leave') {
            $leaveHours = round($hoursPerDay * $effect['fraction'], 4);
            $workedHours = $inside ? $this->workedBasicHours($detail, $employeeId, $date) : $paidBasic;
            $unpaid = round(max(0, $leaveHours - $workedHours), 4);

            if ($unpaid <= 0) {
                $this->mark($row, $effect, $detail, $batchId, 0, 0);

                return 0;
            }

            $amount = round($unpaid * $hourly, 2);
            $this->createIncome($detail, 'BASC', $unpaid, $amount, $this->remark($effect['label'], $date));
            $this->mark($row, $effect, $detail, $batchId, $unpaid, $amount);

            return $amount > 0 ? 1 : 0;
        }

        if ($effect['type'] === 'return_to_work') {
            if ($paidBasic <= 0) {
                $this->mark($row, $effect, $detail, $batchId, 0, 0);

                return 0;
            }

            $amount = round($paidBasic * $hourly, 2);
            $this->createDeduction($detail, $paidBasic, $amount, $date, $effect['request_number']);
            $this->mark($row, $effect, $detail, $batchId, $paidBasic, $amount);

            return $amount > 0 ? 1 : 0;
        }

        $worked = $this->workedBasicHours($detail, $employeeId, $date);
        $delta = round($worked - $paidBasic, 4);

        if (abs($delta) < 0.01 && $worked <= 0 && $paidBasic <= 0) {
            return 0;
        }

        if (abs($delta) < 0.01) {
            $this->mark($row, $effect, $detail, $batchId, 0, 0);

            return 0;
        }

        $amount = round(abs($delta) * $hourly, 2);

        if ($delta > 0) {
            $this->createIncome($detail, 'BASC', $delta, $amount, $this->remark('Amended logs', $date));
        } else {
            $this->createDeduction($detail, abs($delta), $amount, $date, $effect['request_number']);
        }

        $this->mark($row, $effect, $detail, $batchId, abs($delta), $amount);

        return $amount > 0 ? 1 : 0;
    }

    /**
     * @param  list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>  $group
     */
    private function applyOvertimeDate(
        PayrollBatchDetail $detail,
        array $group,
        string $dateKey,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $employeeId,
        int $batchId,
    ): int {
        $open = [];

        foreach ($group as $effect) {
            $row = $this->openRow($effect['key'], (int) $detail->payroll_batch_detail_id);

            if ($row !== null) {
                $open[] = ['effect' => $effect, 'row' => $row];
            }
        }

        if ($open === []) {
            return 0;
        }

        $date = CarbonImmutable::parse($dateKey)->startOfDay();
        $inside = $dateKey >= $from->toDateString() && $dateKey <= $to->toDateString();
        $shift = $detail->employee?->timekeepingSetup?->shiftCode;
        $billableByDate = $this->overtimeApprovals->billableMinutesByDate($employeeId, $date, $date, $shift instanceof ShiftCode ? $shift : null);
        $billable = (int) ($billableByDate[$dateKey] ?? 0);
        $paidMinutes = (int) round($this->paidOtHours($employeeId, $date, $batchId) * 60);
        $includedMinutes = (int) round($this->includedOvertimeHours($employeeId, $date, (int) $detail->payroll_batch_detail_id) * 60);
        $payable = max(0, $billable - $paidMinutes - $includedMinutes);

        if ($inside) {
            $this->markGroup($open, $detail, $batchId, round($billable / 60, 4), 0);

            return 0;
        }

        if ($payable <= 0) {
            if ($billable > 0 || $paidMinutes > 0 || $includedMinutes > 0) {
                $this->markGroup($open, $detail, $batchId, 0, 0);
            }

            return 0;
        }

        $salary = $this->salaryOn($employeeId, (int) ($detail->payrollBatch?->payrollCalendar?->pay_type_id ?? 0), $date);

        if ($salary === null) {
            return 0;
        }

        $amount = $this->overtimePayroll->overtimePay($salary, $payable);
        $hours = round($payable / 60, 4);
        $label = $open[0]['effect']['request_number'];
        $this->createIncome($detail, 'OVRT', $hours, $amount, $this->remark('Job order '.$label, $date));
        $this->markGroup($open, $detail, $batchId, $hours, $amount);

        return $amount > 0 ? 1 : 0;
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function effectsFor(int $employeeId, CarbonImmutable $approvedThrough): array
    {
        $requests = EmployeeRequest::query()
            ->whereNotNull('applied_at')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $approvedThrough->toDateTimeString())
            ->get();

        $effects = [];

        foreach ($requests as $request) {
            foreach ($this->effectsOn($request, $employeeId, $approvedThrough) as $effect) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function effectsOn(EmployeeRequest $request, int $employeeId, CarbonImmutable $approvedThrough): array
    {
        $periodEnd = $approvedThrough->toDateString();

        return match ($request->request_type_code) {
            EmployeeRequestPullService::TYPE_JOB_ORDER => $this->jobOrderEffects($request, $employeeId, $periodEnd),
            EmployeeRequestPullService::TYPE_AMEND_ATTENDANCE => $this->singleDateEffects($request, $employeeId, $periodEnd, 'amendment', 'Amended logs'),
            EmployeeRequestPullService::TYPE_LEAVE_OF_ABSENCE => $this->leaveEffects($request, $employeeId, $periodEnd),
            EmployeeRequestPullService::TYPE_RETURN_TO_WORK => $this->returnEffects($request, $employeeId, $periodEnd),
            default => [],
        };
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function jobOrderEffects(EmployeeRequest $request, int $employeeId, string $periodEnd): array
    {
        $effects = [];

        foreach (is_array($request->items) ? $request->items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $employee = $this->requests->matchEmployee($item['employee_id'] ?? null, $item['employee_name'] ?? null)
                ?? $this->requests->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);

            if ($employee === null || (int) $employee->employee_id !== $employeeId) {
                continue;
            }

            foreach (is_array($item['work_schedules'] ?? null) ? $item['work_schedules'] : [] as $schedule) {
                if (! is_array($schedule)) {
                    continue;
                }

                $start = $this->dateOnly($schedule['date_from'] ?? $schedule['date'] ?? null);
                $end = $this->dateOnly($schedule['date_to'] ?? null) ?? $start;
                $clock = preg_replace('/\D/', '', (string) ($schedule['time_from'] ?? '')) ?? '';

                if ($start === null || $end === null || $end < $start) {
                    continue;
                }

                $cursor = CarbonImmutable::parse($start);
                $last = CarbonImmutable::parse($end);
                $days = 0;

                while ($cursor->lte($last) && $days < 62) {
                    $date = $cursor->toDateString();

                    if ($date <= $periodEnd) {
                        $effects[] = $this->effect(
                            'ot:'.$request->employee_request_id.':'.$employeeId.':'.$date.':'.$clock,
                            'overtime',
                            $date,
                            $request,
                            'Job order',
                        );
                    }

                    $cursor = $cursor->addDay();
                    $days++;
                }
            }
        }

        return $effects;
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function singleDateEffects(EmployeeRequest $request, int $employeeId, string $periodEnd, string $type, string $label): array
    {
        $employee = $this->requests->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);

        if ($employee === null || (int) $employee->employee_id !== $employeeId) {
            return [];
        }

        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $date = $this->dateOnly($payload['attendance_date'] ?? null);

        if ($date === null || $date > $periodEnd) {
            return [];
        }

        return [$this->effect('amend:'.$request->employee_request_id.':'.$employeeId.':'.$date, $type, $date, $request, $label)];
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function leaveEffects(EmployeeRequest $request, int $employeeId, string $periodEnd): array
    {
        $employee = $this->requests->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);

        if ($employee === null || (int) $employee->employee_id !== $employeeId) {
            return [];
        }

        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $label = trim((string) ($payload['leave_type_label'] ?? 'Leave'));
        $label = $label !== '' ? $label : 'Leave';
        $effects = [];

        foreach (is_array($payload['leave_rows'] ?? null) ? $payload['leave_rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $start = $this->dateOnly($row['date_of_leave'] ?? null);

            if ($start === null) {
                continue;
            }

            foreach ($this->leaveSpan($start, $row['num_days'] ?? 1) as $span) {
                if ($span['date'] > $periodEnd) {
                    continue;
                }

                $effects[] = $this->effect(
                    'leave:'.$request->employee_request_id.':'.$employeeId.':'.$span['date'],
                    'leave',
                    $span['date'],
                    $request,
                    $label,
                    $span['fraction'],
                );
            }
        }

        return $effects;
    }

    /**
     * @return list<array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}>
     */
    private function returnEffects(EmployeeRequest $request, int $employeeId, string $periodEnd): array
    {
        $employee = $this->requests->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);

        if ($employee === null || (int) $employee->employee_id !== $employeeId) {
            return [];
        }

        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $effects = [];

        foreach (is_array($payload['absence_rows'] ?? null) ? $payload['absence_rows'] : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($this->absenceDates((string) ($row['dates_of_absence'] ?? '')) as $date) {
                if ($date > $periodEnd) {
                    continue;
                }

                $effects[] = $this->effect(
                    'rtw:'.$request->employee_request_id.':'.$employeeId.':'.$date.':'.$index,
                    'return_to_work',
                    $date,
                    $request,
                    'Return to work',
                );
            }
        }

        return $effects;
    }

    /**
     * @return array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}
     */
    private function effect(string $key, string $type, string $date, EmployeeRequest $request, string $label, float $fraction = 1): array
    {
        return [
            'key' => $key,
            'type' => $type,
            'date' => $date,
            'request_id' => (int) $request->employee_request_id,
            'request_number' => (string) $request->request_number,
            'label' => $label,
            'fraction' => $fraction,
        ];
    }

    /**
     * @return list<array{date: string, fraction: float}>
     */
    private function leaveSpan(string $start, mixed $numDays): array
    {
        $raw = strtolower(trim((string) $numDays));
        $fraction = 1.0;
        $days = 1;

        if (str_contains($raw, '1/2') || str_contains($raw, '0.5') || str_contains($raw, 'half')) {
            $fraction = 0.5;
        } elseif (preg_match('/\d+(?:\.\d+)?/', $raw, $match) === 1) {
            $value = (float) $match[0];

            if ($value > 0 && $value < 1) {
                $fraction = $value;
            } elseif ($value >= 1) {
                $days = min(31, (int) round($value));
            }
        }

        $cursor = CarbonImmutable::parse($start);
        $span = [];

        for ($i = 0; $i < $days; $i++) {
            $span[] = ['date' => $cursor->toDateString(), 'fraction' => $fraction];
            $cursor = $cursor->addDay();
        }

        return $span;
    }

    /**
     * @return list<string>
     */
    private function absenceDates(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        if (preg_match('/^([A-Za-z]+)\.?\s*(\d{1,2})\s*-\s*(\d{1,2})\s+(\d{4})$/', $raw, $match) === 1) {
            try {
                $start = CarbonImmutable::parse($match[1].' '.$match[2].' '.$match[4])->startOfDay();
                $end = CarbonImmutable::parse($match[1].' '.$match[3].' '.$match[4])->startOfDay();
            } catch (\Throwable) {
                return [];
            }

            if ($end->lt($start)) {
                return [];
            }

            $dates = [];

            while ($start->lte($end) && count($dates) < 31) {
                $dates[] = $start->toDateString();
                $start = $start->addDay();
            }

            return $dates;
        }

        try {
            return [CarbonImmutable::parse($raw)->toDateString()];
        } catch (\Throwable) {
            return [];
        }
    }

    private function openRow(string $key, int $detailId): ?EmployeeRequestPayrollInclusion
    {
        $row = EmployeeRequestPayrollInclusion::query()->where('effect_key', $key)->first();

        if (
            $row !== null
            && $row->included_at !== null
            && $row->payroll_batch_detail_id !== null
            && (int) $row->payroll_batch_detail_id !== $detailId
        ) {
            return null;
        }

        return $row ?? new EmployeeRequestPayrollInclusion(['effect_key' => $key]);
    }

    /**
     * @param  array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}  $effect
     */
    private function mark(
        EmployeeRequestPayrollInclusion $row,
        array $effect,
        PayrollBatchDetail $detail,
        int $batchId,
        float $hours,
        float $amount,
    ): void {
        $row->fill([
            'employee_request_id' => $effect['request_id'],
            'employee_id' => $detail->employee_id,
            'effect_type' => $effect['type'],
            'effect_date' => $effect['date'],
            'payroll_batch_id' => $batchId,
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'hours' => round($hours, 4),
            'amount' => round($amount, 2),
            'included_at' => now(),
        ])->save();
    }

    /**
     * Hours are stored once so a later request for the same day pays only the added minutes.
     *
     * @param  list<array{effect: array{key: string, type: string, date: string, request_id: int, request_number: string, label: string, fraction: float}, row: EmployeeRequestPayrollInclusion}>  $open
     */
    private function markGroup(array $open, PayrollBatchDetail $detail, int $batchId, float $hours, float $amount): void
    {
        foreach ($open as $index => $item) {
            $this->mark(
                $item['row'],
                $item['effect'],
                $detail,
                $batchId,
                $index === 0 ? $hours : 0,
                $index === 0 ? $amount : 0,
            );
        }
    }

    private function salaryOn(int $employeeId, int $payTypeId, CarbonImmutable $date): ?EmployeeSalary
    {
        if ($payTypeId <= 0) {
            return null;
        }

        $salary = $this->salaryResolver->salaryEffectiveOnDate(
            $this->salaryResolver->salariesForPeriod($employeeId, $payTypeId, $date, $date),
            $date,
        );
        $salary?->loadMissing('incomes.incomeType');

        return $salary;
    }

    private function workedBasicHours(PayrollBatchDetail $detail, int $employeeId, CarbonImmutable $date): float
    {
        $session = $this->timeLogs->daySessionsForPeriod($employeeId, $date, $date)
            ->first(fn (array $row) => $row['date']->toDateString() === $date->toDateString());
        $timeIn = $session['time_in'] ?? null;
        $timeOut = $session['time_out'] ?? null;

        if ($timeIn === null || $timeOut === null || $timeIn === '' || $timeOut === '') {
            return 0.0;
        }

        $metrics = $this->attendanceView->metricsForTimes(
            $employeeId,
            $date,
            (string) $timeIn,
            (string) $timeOut,
            $detail->employee?->timekeepingSetup?->policy,
            $detail->employee?->timekeepingSetup?->shiftCode,
        );

        return round(max(0, (float) ($metrics['basic'] ?? 0)), 4);
    }

    private function paidBasicHours(int $employeeId, CarbonImmutable $date, int $excludeBatchId): float
    {
        return round((float) ($this->postedSnapshot($employeeId, $date, $excludeBatchId)?->basic ?? 0), 4);
    }

    private function paidOtHours(int $employeeId, CarbonImmutable $date, int $excludeBatchId): float
    {
        return round((float) ($this->postedSnapshot($employeeId, $date, $excludeBatchId)?->ot ?? 0), 4);
    }

    private function postedSnapshot(int $employeeId, CarbonImmutable $date, int $excludeBatchId): ?PayrollAttendanceDay
    {
        $batchId = DB::table('trn_payroll_batch_details as d')
            ->join('trn_payroll_batches as b', 'b.payroll_batch_id', '=', 'd.payroll_batch_id')
            ->join('tbl_payroll_calendar as c', 'c.payroll_calendar_id', '=', 'b.payroll_calendar_id')
            ->where('d.employee_id', $employeeId)
            ->where('b.payroll_batch_id', '!=', $excludeBatchId)
            ->where('b.payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->whereNull('b.deleted_at')
            ->whereDate('c.dt_from', '<=', $date->toDateString())
            ->whereDate('c.dt_to', '>=', $date->toDateString())
            ->orderByDesc('b.payroll_batch_id')
            ->value('b.payroll_batch_id');

        if ($batchId === null) {
            return null;
        }

        return PayrollAttendanceDay::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $date->toDateString())
            ->whereHas('payrollBatchDetail', fn ($query) => $query->where('payroll_batch_id', $batchId))
            ->first();
    }

    private function includedOvertimeHours(int $employeeId, CarbonImmutable $date, int $detailId): float
    {
        return (float) EmployeeRequestPayrollInclusion::query()
            ->where('employee_id', $employeeId)
            ->whereDate('effect_date', $date->toDateString())
            ->where('effect_type', 'overtime')
            ->whereNotNull('included_at')
            ->where(function ($query) use ($detailId): void {
                $query->whereNull('payroll_batch_detail_id')
                    ->orWhere('payroll_batch_detail_id', '!=', $detailId);
            })
            ->sum('hours');
    }

    private function createIncome(PayrollBatchDetail $detail, string $code, float $hours, float $amount, string $remarks): void
    {
        if ($amount <= 0) {
            return;
        }

        $incomeTypeId = IncomeType::query()->where('income_type_code', $code)->value('income_type_id');

        if ($incomeTypeId === null) {
            return;
        }

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $incomeTypeId,
            'hours' => $hours > 0 ? round($hours, 4) : null,
            'days' => null,
            'taxable' => round($amount, 2),
            'non_taxable' => 0,
            'orig_taxable' => round($amount, 2),
            'orig_non_taxable' => 0,
            'remarks' => mb_substr($remarks, 0, 100),
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ]);
    }

    private function createDeduction(PayrollBatchDetail $detail, float $hours, float $amount, CarbonImmutable $date, string $requestNumber): void
    {
        if ($amount <= 0) {
            return;
        }

        $deductionTypeId = DeductionType::query()->where('deduction_type_code', 'ABST')->value('deduction_type_id');

        if ($deductionTypeId === null) {
            return;
        }

        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => $deductionTypeId,
            'hours' => $hours > 0 ? round($hours, 4) : null,
            'employee_amount' => round($amount, 2),
            'employer_amount' => 0,
            'reference_number' => mb_substr($requestNumber, 0, 45),
            'dt_reference' => $date->toDateString(),
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
            'counts_toward_net' => true,
        ]);
    }

    private function remark(string $label, CarbonImmutable $date): string
    {
        return $label.' '.$date->format('M j, Y');
    }

    private function dateOnly(mixed $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match) !== 1) {
            return null;
        }

        return substr($match[0], 0, 10);
    }
}
