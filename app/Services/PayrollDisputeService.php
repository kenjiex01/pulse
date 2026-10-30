<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollDeduction;
use App\Models\PayrollDispute;
use App\Models\PayrollIncome;
use App\Models\ShiftCode;
use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll disputes filed on a current batch for a day paid in an earlier posted batch.
 */
class PayrollDisputeService
{
    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
        private readonly EmployeeShiftResolver $shiftResolver,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly PayrollOvertimeService $overtimePayroll,
        private readonly EmployeeSalaryResolverService $salaryResolver,
        private readonly HolidayPayService $holidayPay,
    ) {}

    public function canEditDisputes(PayrollBatch $batch): bool
    {
        return in_array((int) $batch->payroll_batch_status_id, [
            PayrollBatchStatus::PENDING,
            PayrollBatchStatus::PROCESSED,
        ], true);
    }

    /**
     * Values to pre-fill the dispute form for a work date + category.
     *
     * @return array<string, mixed>
     */
    public function prefill(PayrollBatchDetail $detail, string $workDate, string $category, ?int $ignoreDisputeId = null): array
    {
        $context = $this->dayContext($detail, $workDate);

        if ($context['error'] !== null) {
            return ['ok' => false, 'message' => $context['error']];
        }

        $shift = $context['shift'];
        $recordedIn = $this->shortTime($context['recorded_time_in']);
        $recordedOut = $this->shortTime($context['recorded_time_out']);
        if ($recordedOut !== null && $recordedOut === $recordedIn) {
            $recordedOut = null;
        }
        $shiftStart = $this->shortTime($shift?->time_in);
        $shiftEnd = $this->shortTime($shift?->time_out);

        $values = match ($category) {
            PayrollDispute::UNPAID_DAYS => [
                'shift_code_id' => $shift?->shift_code_id,
                'time_in' => $recordedIn ?? $shiftStart,
                'time_out' => $recordedOut ?? $shiftEnd,
            ],
            PayrollDispute::UNPAID_OVERTIME => [
                'ot_start' => $shiftEnd,
                'ot_end' => ($recordedOut !== null && $shiftEnd !== null && $recordedOut > $shiftEnd) ? $recordedOut : null,
            ],
            PayrollDispute::OVER_DEDUCTION_UNDERTIME => [
                'shift_code_id' => $shift?->shift_code_id,
                'time_in' => $recordedIn ?? $shiftStart,
                'time_out' => $recordedOut,
            ],
            PayrollDispute::OVER_DEDUCTION_LATE => [
                'time_in' => $recordedIn,
            ],
            PayrollDispute::OVER_PAYMENT_DAYS => [
                'shift_code_id' => $shift?->shift_code_id,
                'time_in' => $recordedOut !== null ? $recordedIn : null,
                'time_out' => $recordedIn !== null ? $recordedOut : null,
            ],
            PayrollDispute::OVER_PAYMENT_OVERTIME => ($recordedOut !== null && $shiftEnd !== null && $recordedOut > $shiftEnd)
                ? ['ot_start' => $shiftEnd, 'ot_end' => $recordedOut]
                : ['ot_start' => null, 'ot_end' => null],
            default => [],
        };

        $conflict = $this->conflictFor($detail, $workDate, $category, $ignoreDisputeId);

        return [
            'ok' => true,
            'work_date' => $workDate,
            'category' => $category,
            'values' => $values,
            'shift' => $shift ? [
                'shift_code_id' => $shift->shift_code_id,
                'label' => $this->shiftLabel($shift),
            ] : null,
            'recorded' => [
                'time_in' => $recordedIn,
                'time_out' => $recordedOut,
            ],
            'paid' => $this->paidSummary($context),
            'already_filed' => $conflict,
        ];
    }

    /**
     * Single source of dispute amounts (preview, save, upload, batch processing).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(PayrollBatchDetail $detail, array $input): array
    {
        $category = (string) ($input['category'] ?? '');
        $workDate = (string) ($input['work_date'] ?? '');
        $context = $this->dayContext($detail, $workDate, isset($input['shift_code_id']) ? (int) $input['shift_code_id'] : null);

        $result = [
            'ok' => false,
            'message' => null,
            'warnings' => [],
            'category' => $category,
            'hours' => 0.0,
            'amount' => 0.0,
            'ot_hours' => 0.0,
            'ot_amount' => 0.0,
            'total' => 0.0,
            'breakdown' => '',
            'paid' => null,
            'source_payroll_batch_id' => null,
        ];

        if ($context['error'] !== null) {
            $result['message'] = $context['error'];

            return $result;
        }

        if (! array_key_exists($category, PayrollDispute::CATEGORIES)) {
            $result['message'] = 'Select a dispute type.';

            return $result;
        }

        $result['paid'] = $this->paidSummary($context);
        $result['source_payroll_batch_id'] = $context['source_batch']?->payroll_batch_id;

        $salary = $context['salary'];
        $sourceCalendar = $context['source_batch']?->payrollCalendar
            ?? $context['source_batch']?->loadMissing('payrollCalendar')->payrollCalendar;
        $rateDays = ($salary !== null && $sourceCalendar?->dt_from !== null && $sourceCalendar?->dt_to !== null)
            ? \App\Support\PayrollCalendarWorkingDays::forEmployeeSalary(
                $salary,
                (int) $detail->employee_id,
                $sourceCalendar->dt_from,
                $sourceCalendar->dt_to,
            )
            : null;
        $hourlyRate = $salary?->hourlyRate($rateDays);

        if ($salary === null || $hourlyRate === null) {
            $result['ok'] = true;
            $result['warnings'][] = 'The employee has no salary rate on this work date, so the expected pay is 0.00.';
            $result['breakdown'] = 'No salary rate on this date.';

            return $result;
        }

        $date = $context['date'];
        $shift = $context['shift'];
        $policy = $context['policy'];
        $paid = $context['paid_hours'];
        $employeeId = (int) $detail->employee_id;
        $timeIn = $this->longTime($input['time_in'] ?? null);
        $timeOut = $this->longTime($input['time_out'] ?? null);

        switch ($category) {
            case PayrollDispute::UNPAID_DAYS:
                if ($timeIn === null || $timeOut === null) {
                    $result['message'] = 'Time In and Time Out are required.';

                    return $result;
                }

                $metrics = $this->attendanceView->metricsForTimes($employeeId, $date, $timeIn, $timeOut, $policy, $shift);
                $hours = max(0.0, round((float) ($metrics['basic'] ?? 0) - $paid['basic'], 2));
                $otMinutes = max(0, $this->excessOvertimeMinutes($date, $timeIn, $timeOut, $shift, $policy, $employeeId) - (int) round($paid['ot'] * 60));

                $result['hours'] = $hours;
                $result['amount'] = round($hours * $hourlyRate, 2);
                $result['ot_hours'] = round($otMinutes / 60, 2);
                $result['ot_amount'] = $this->overtimePayroll->overtimePay($salary, $otMinutes, $rateDays);
                $result['breakdown'] = sprintf(
                    'Basic %s h = %s, OT %s h = %s, less already paid basic %s h / OT %s h',
                    number_format((float) ($metrics['basic'] ?? 0), 2),
                    number_format(round((float) ($metrics['basic'] ?? 0) * $hourlyRate, 2), 2),
                    number_format(($otMinutes + (int) round($paid['ot'] * 60)) / 60, 2),
                    number_format($this->overtimePayroll->overtimePay($salary, $otMinutes + (int) round($paid['ot'] * 60), $rateDays), 2),
                    number_format($paid['basic'], 2),
                    number_format($paid['ot'], 2),
                );
                break;

            case PayrollDispute::UNPAID_OVERTIME:
                $otStart = $this->longTime($input['ot_start'] ?? null);
                $otEnd = $this->longTime($input['ot_end'] ?? null);

                if ($otStart === null || $otEnd === null) {
                    $result['message'] = 'OT Start and OT End are required.';

                    return $result;
                }

                $startAt = $date->setTimeFromTimeString($otStart);
                $endAt = $date->setTimeFromTimeString($otEnd);

                if ($endAt->lessThanOrEqualTo($startAt)) {
                    $endAt = $endAt->addDay();
                }

                $filedMinutes = (int) $startAt->diffInMinutes($endAt);
                $otMinutes = max(0, $filedMinutes - (int) round($paid['ot'] * 60));

                $result['ot_hours'] = round($otMinutes / 60, 2);
                $result['ot_amount'] = $this->overtimePayroll->overtimePay($salary, $otMinutes, $rateDays);
                $result['breakdown'] = sprintf(
                    'OT %d min filed - %d min already paid = %d min x rate = %s',
                    $filedMinutes,
                    (int) round($paid['ot'] * 60),
                    $otMinutes,
                    number_format($result['ot_amount'], 2),
                );
                break;

            case PayrollDispute::OVER_DEDUCTION_UNDERTIME:
                if ($timeIn === null || $timeOut === null) {
                    $result['message'] = 'Time In and Time Out are required.';

                    return $result;
                }

                $metrics = $this->attendanceView->metricsForTimes($employeeId, $date, $timeIn, $timeOut, $policy, $shift);
                $correctedMinutes = (int) round((float) ($metrics['undertime'] ?? 0) * 60);
                $paidMinutes = (int) round($paid['undertime'] * 60);
                $refundMinutes = max(0, $paidMinutes - $correctedMinutes);

                $result['hours'] = round($refundMinutes / 60, 2);
                $result['amount'] = round(($refundMinutes / 60) * $hourlyRate, 2);
                $result['breakdown'] = sprintf(
                    'Undertime deducted %d min - corrected %d min = %d min x rate = %s',
                    $paidMinutes,
                    $correctedMinutes,
                    $refundMinutes,
                    number_format($result['amount'], 2),
                );
                break;

            case PayrollDispute::OVER_DEDUCTION_LATE:
                if ($timeIn === null) {
                    $result['message'] = 'Actual Time In is required.';

                    return $result;
                }

                $timeOutForLate = $this->longTime($context['recorded_time_out']) ?? $this->longTime($shift?->time_out);
                $metrics = $this->attendanceView->metricsForTimes($employeeId, $date, $timeIn, $timeOutForLate, $policy, $shift);
                $correctedMinutes = (int) round((float) ($metrics['late'] ?? 0) * 60);
                $paidMinutes = (int) round($paid['late'] * 60);
                $refundMinutes = max(0, $paidMinutes - $correctedMinutes);

                $result['hours'] = round($refundMinutes / 60, 2);
                $result['amount'] = round(($refundMinutes / 60) * $hourlyRate, 2);
                $result['breakdown'] = sprintf(
                    'Late deducted %d min - corrected %d min = %d min x rate = %s',
                    $paidMinutes,
                    $correctedMinutes,
                    $refundMinutes,
                    number_format($result['amount'], 2),
                );
                break;

            case PayrollDispute::OVER_PAYMENT_DAYS:
                if (($timeIn === null) !== ($timeOut === null)) {
                    $result['message'] = 'Enter both Time In and Time Out, or leave both blank if the employee was absent.';

                    return $result;
                }

                $actualBasic = $timeIn === null
                    ? 0.0
                    : round((float) ($this->attendanceView->metricsForTimes($employeeId, $date, $timeIn, $timeOut, $policy, $shift)['basic'] ?? 0), 2);
                $hours = max(0.0, round($paid['basic'] - $actualBasic, 2));

                $result['hours'] = $hours;
                $result['amount'] = round($hours * $hourlyRate, 2);
                $result['breakdown'] = sprintf(
                    'Paid basic %s h - actual %s h%s = %s h x rate = %s to deduct',
                    number_format($paid['basic'], 2),
                    number_format($actualBasic, 2),
                    $timeIn === null ? ' (absent)' : '',
                    number_format($hours, 2),
                    number_format($result['amount'], 2),
                );
                break;

            case PayrollDispute::OVER_PAYMENT_OVERTIME:
                $otStart = $this->longTime($input['ot_start'] ?? null);
                $otEnd = $this->longTime($input['ot_end'] ?? null);

                if (($otStart === null) !== ($otEnd === null)) {
                    $result['message'] = 'Enter both OT Start and OT End, or leave both blank if no overtime was rendered.';

                    return $result;
                }

                $actualMinutes = 0;

                if ($otStart !== null) {
                    $startAt = $date->setTimeFromTimeString($otStart);
                    $endAt = $date->setTimeFromTimeString($otEnd);

                    if ($endAt->lessThanOrEqualTo($startAt)) {
                        $endAt = $endAt->addDay();
                    }

                    $actualMinutes = (int) $startAt->diffInMinutes($endAt);
                }

                $paidMinutes = (int) round($paid['ot'] * 60);
                $overMinutes = max(0, $paidMinutes - $actualMinutes);

                $result['ot_hours'] = round($overMinutes / 60, 2);
                $result['ot_amount'] = $this->overtimePayroll->overtimePay($salary, $overMinutes, $rateDays);
                $result['breakdown'] = sprintf(
                    'OT paid %d min - actual %d min = %d min x rate = %s to deduct',
                    $paidMinutes,
                    $actualMinutes,
                    $overMinutes,
                    number_format($result['ot_amount'], 2),
                );
                break;
        }

        $result['ok'] = true;
        $result['total'] = round($result['amount'] + $result['ot_amount'], 2);

        if ($result['total'] <= 0) {
            $result['warnings'][] = PayrollDispute::isOverPaymentCategory($category)
                ? 'No adjustment (0.00): no overpayment found for this day.'
                : 'No adjustment (0.00): nothing more is owed for this day.';
        }

        return $result;
    }

    /**
     * Validate and normalize a dispute payload; returns attributes + calculation.
     *
     * @param  array<string, mixed>  $input
     * @return array{attributes: array<string, mixed>, calculation: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function validateForStore(PayrollBatchDetail $detail, array $input, ?int $ignoreDisputeId = null): array
    {
        $category = (string) ($input['category'] ?? '');
        $workDate = (string) ($input['work_date'] ?? '');

        if (! array_key_exists($category, PayrollDispute::CATEGORIES)) {
            throw ValidationException::withMessages(['category' => 'Select a valid dispute type.']);
        }

        $context = $this->dayContext($detail, $workDate);

        if ($context['error'] !== null) {
            throw ValidationException::withMessages(['work_date' => $context['error']]);
        }

        $conflict = $this->conflictFor($detail, $workDate, $category, $ignoreDisputeId);

        if ($conflict !== null) {
            throw ValidationException::withMessages(['work_date' => $conflict['message']]);
        }

        $required = match ($category) {
            PayrollDispute::UNPAID_DAYS, PayrollDispute::OVER_DEDUCTION_UNDERTIME => ['time_in' => 'Time In', 'time_out' => 'Time Out'],
            PayrollDispute::UNPAID_OVERTIME => ['ot_start' => 'OT Start', 'ot_end' => 'OT End'],
            PayrollDispute::OVER_DEDUCTION_LATE => ['time_in' => 'Actual Time In'],
            PayrollDispute::OVER_PAYMENT_DAYS, PayrollDispute::OVER_PAYMENT_OVERTIME => [],
        };

        $errors = [];

        foreach ($required as $field => $label) {
            if ($this->shortTime($input[$field] ?? null) === null) {
                $errors[$field] = $label.' is required (HH:MM).';
            }
        }

        $optionalPair = match ($category) {
            PayrollDispute::OVER_PAYMENT_DAYS => ['time_in' => 'Time In', 'time_out' => 'Time Out'],
            PayrollDispute::OVER_PAYMENT_OVERTIME => ['ot_start' => 'OT Start', 'ot_end' => 'OT End'],
            default => [],
        };

        if ($optionalPair !== []) {
            [$firstField, $secondField] = array_keys($optionalPair);
            $first = $this->shortTime($input[$firstField] ?? null);
            $second = $this->shortTime($input[$secondField] ?? null);

            if (($first === null) !== ($second === null)) {
                $missing = $first === null ? $firstField : $secondField;
                $errors[$missing] = $optionalPair[$missing].' is required when '
                    .$optionalPair[$missing === $firstField ? $secondField : $firstField].' is entered. Leave both blank for none.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $usesShift = in_array($category, [PayrollDispute::UNPAID_DAYS, PayrollDispute::OVER_DEDUCTION_UNDERTIME, PayrollDispute::OVER_PAYMENT_DAYS], true);
        $usesTimeOut = $usesShift;
        $usesOt = in_array($category, [PayrollDispute::UNPAID_OVERTIME, PayrollDispute::OVER_PAYMENT_OVERTIME], true);

        $shiftCodeId = $usesShift
            ? ((int) ($input['shift_code_id'] ?? 0) ?: $context['shift']?->shift_code_id)
            : null;

        $attributes = [
            'category' => $category,
            'work_date' => $workDate,
            'shift_code_id' => $shiftCodeId,
            'time_in' => $usesOt ? null : $this->shortTime($input['time_in'] ?? null),
            'time_out' => $usesTimeOut ? $this->shortTime($input['time_out'] ?? null) : null,
            'ot_start' => $usesOt ? $this->shortTime($input['ot_start'] ?? null) : null,
            'ot_end' => $usesOt ? $this->shortTime($input['ot_end'] ?? null) : null,
            'remarks' => filled($input['remarks'] ?? null) ? mb_substr(trim((string) $input['remarks']), 0, 255) : null,
        ];

        $calculation = $this->calculate($detail, $attributes);

        if (! $calculation['ok']) {
            throw ValidationException::withMessages(['work_date' => $calculation['message'] ?? 'Unable to compute this dispute.']);
        }

        if ($category === PayrollDispute::UNPAID_DAYS && $calculation['ot_amount'] > 0) {
            $uot = $this->activeDisputesForDate((int) $detail->employee_id, $workDate, $ignoreDisputeId)
                ->firstWhere('category', PayrollDispute::UNPAID_OVERTIME);

            if ($uot !== null) {
                throw ValidationException::withMessages([
                    'time_out' => 'An Unpaid Overtime dispute already exists for this work date (Batch #'
                        .$this->batchNoFor($uot).'). Remove the OT from this Unpaid Days dispute or delete that filing first.',
                ]);
            }
        }

        $attributes['source_payroll_batch_id'] = $calculation['source_payroll_batch_id'];
        $attributes['expected_pay'] = $calculation['amount'];
        $attributes['expected_ot_pay'] = $calculation['ot_amount'];

        return ['attributes' => $attributes, 'calculation' => $calculation];
    }

    /**
     * Rebuild dispute income lines on a batch detail during Process / Reprocess.
     */
    public function applyToDetail(PayrollBatchDetail $detail): int
    {
        $disputes = PayrollDispute::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->orderBy('work_date')
            ->orderBy('payroll_dispute_id')
            ->get();

        $applied = 0;

        foreach ($disputes as $dispute) {
            $calculation = $this->calculate($detail, [
                'category' => $dispute->category,
                'work_date' => $dispute->work_date?->toDateString(),
                'shift_code_id' => $dispute->shift_code_id,
                'time_in' => $dispute->time_in,
                'time_out' => $dispute->time_out,
                'ot_start' => $dispute->ot_start,
                'ot_end' => $dispute->ot_end,
            ]);

            $remarks = $dispute->work_date?->format('M j, Y');
            $incomeId = null;
            $otIncomeId = null;

            if ($dispute->isOverPayment()) {
                $deductionId = $calculation['ok'] && $calculation['total'] > 0
                    ? $this->createDeduction(
                        $detail,
                        PayrollDispute::DEDUCTION_TYPE_CODES[$dispute->category],
                        $calculation['hours'] + $calculation['ot_hours'],
                        $calculation['total'],
                        $dispute->work_date?->toDateString(),
                    )
                    : null;

                $dispute->forceFill([
                    'source_payroll_batch_id' => $calculation['source_payroll_batch_id'] ?? $dispute->source_payroll_batch_id,
                    'computed_hours' => $calculation['hours'],
                    'computed_amount' => $calculation['amount'],
                    'computed_ot_hours' => $calculation['ot_hours'],
                    'computed_ot_amount' => $calculation['ot_amount'],
                    'payroll_income_id' => null,
                    'ot_payroll_income_id' => null,
                ])->saveQuietly();

                if ($deductionId !== null) {
                    $applied++;
                }

                continue;
            }

            if ($calculation['ok'] && $calculation['amount'] > 0) {
                $incomeId = $this->createIncome(
                    $detail,
                    PayrollDispute::INCOME_TYPE_CODES[$dispute->category] ?? null,
                    $calculation['hours'],
                    $calculation['amount'],
                    $remarks,
                );
            }

            if ($calculation['ok'] && $calculation['ot_amount'] > 0) {
                $otIncomeId = $this->createIncome(
                    $detail,
                    $dispute->category === PayrollDispute::UNPAID_DAYS
                        ? PayrollDispute::UNPAID_DAYS_OT_INCOME_TYPE_CODE
                        : PayrollDispute::INCOME_TYPE_CODES[$dispute->category] ?? null,
                    $calculation['ot_hours'],
                    $calculation['ot_amount'],
                    $remarks,
                );
            }

            $dispute->forceFill([
                'source_payroll_batch_id' => $calculation['source_payroll_batch_id'] ?? $dispute->source_payroll_batch_id,
                'computed_hours' => $calculation['hours'],
                'computed_amount' => $calculation['amount'],
                'computed_ot_hours' => $calculation['ot_hours'],
                'computed_ot_amount' => $calculation['ot_amount'],
                'payroll_income_id' => $incomeId,
                'ot_payroll_income_id' => $otIncomeId,
            ])->saveQuietly();

            if ($incomeId !== null || $otIncomeId !== null) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * @return array{message: string, batch_no: string, payroll_dispute_id: int}|null
     */
    public function conflictFor(PayrollBatchDetail $detail, string $workDate, string $category, ?int $ignoreDisputeId = null): ?array
    {
        $existing = $this->activeDisputesForDate((int) $detail->employee_id, $workDate, $ignoreDisputeId);
        $same = $existing->firstWhere('category', $category);

        if ($same !== null) {
            return [
                'message' => 'An '.PayrollDispute::categoryLabel($category).' dispute for this work date was already filed in Batch #'
                    .$this->batchNoFor($same).' on '.($same->created_at?->format('M j, Y') ?? '—').'.',
                'batch_no' => $this->batchNoFor($same),
                'payroll_dispute_id' => (int) $same->payroll_dispute_id,
            ];
        }

        if ($category === PayrollDispute::UNPAID_OVERTIME) {
            $upd = $existing->first(
                fn (PayrollDispute $dispute) => $dispute->category === PayrollDispute::UNPAID_DAYS
                    && (float) $dispute->expected_ot_pay > 0
            );

            if ($upd !== null) {
                return [
                    'message' => 'The Unpaid Days dispute for this work date (Batch #'.$this->batchNoFor($upd)
                        .') already includes overtime, so Unpaid Overtime cannot be filed for the same day.',
                    'batch_no' => $this->batchNoFor($upd),
                    'payroll_dispute_id' => (int) $upd->payroll_dispute_id,
                ];
            }
        }

        $opposites = [
            PayrollDispute::UNPAID_DAYS => PayrollDispute::OVER_PAYMENT_DAYS,
            PayrollDispute::OVER_PAYMENT_DAYS => PayrollDispute::UNPAID_DAYS,
            PayrollDispute::UNPAID_OVERTIME => PayrollDispute::OVER_PAYMENT_OVERTIME,
            PayrollDispute::OVER_PAYMENT_OVERTIME => PayrollDispute::UNPAID_OVERTIME,
        ];
        $opposite = isset($opposites[$category]) ? $existing->firstWhere('category', $opposites[$category]) : null;

        if ($opposite !== null) {
            return [
                'message' => 'An '.$opposite->categoryName().' dispute for this work date was already filed in Batch #'
                    .$this->batchNoFor($opposite).', so '.PayrollDispute::categoryLabel($category).' cannot be filed for the same day.',
                'batch_no' => $this->batchNoFor($opposite),
                'payroll_dispute_id' => (int) $opposite->payroll_dispute_id,
            ];
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PayrollDispute>
     */
    private function activeDisputesForDate(int $employeeId, string $workDate, ?int $ignoreDisputeId = null): \Illuminate\Support\Collection
    {
        return PayrollDispute::query()
            ->with('payrollBatch')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->when($ignoreDisputeId !== null, fn ($query) => $query->where('payroll_dispute_id', '!=', $ignoreDisputeId))
            ->get();
    }

    private function batchNoFor(PayrollDispute $dispute): string
    {
        return $dispute->payrollBatch?->formattedBatchNo() ?? (string) $dispute->payroll_batch_id;
    }

    /**
     * @return array{
     *     error: string|null,
     *     date: CarbonImmutable|null,
     *     employee: Employee|null,
     *     shift: ShiftCode|null,
     *     policy: TimekeepingPolicy|null,
     *     salary: EmployeeSalary|null,
     *     recorded_time_in: string|null,
     *     recorded_time_out: string|null,
     *     source_batch: PayrollBatch|null,
     *     snapshot: PayrollAttendanceDay|null,
     *     paid_hours: array{basic: float, ot: float, late: float, undertime: float}
     * }
     */
    private function dayContext(PayrollBatchDetail $detail, string $workDate, ?int $shiftCodeId = null): array
    {
        $context = [
            'error' => null,
            'date' => null,
            'employee' => null,
            'shift' => null,
            'policy' => null,
            'salary' => null,
            'recorded_time_in' => null,
            'recorded_time_out' => null,
            'source_batch' => null,
            'snapshot' => null,
            'paid_hours' => ['basic' => 0.0, 'ot' => 0.0, 'late' => 0.0, 'undertime' => 0.0],
        ];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) !== 1) {
            $context['error'] = 'Select a valid work date.';

            return $context;
        }

        $detail->loadMissing(['payrollBatch.payrollCalendar', 'employee.timekeepingSetup.policy', 'employee.timekeepingSetup.shiftCode.breaks']);
        $calendar = $detail->payrollBatch?->payrollCalendar;
        $employee = $detail->employee;
        $date = CarbonImmutable::parse($workDate)->startOfDay();

        if ($calendar?->dt_from === null || $employee === null) {
            $context['error'] = 'Pay period dates are missing for this batch.';

            return $context;
        }

        if ($date->gte(CarbonImmutable::parse($calendar->dt_from->toDateString()))) {
            $context['error'] = 'Work date must be before this pay period ('.$calendar->dt_from->format('M j, Y').'). Disputes are for days paid in an earlier batch.';

            return $context;
        }

        $sourceBatch = $this->postedBatchCoveringDate((int) $employee->employee_id, $date, (int) $detail->payroll_batch_id);

        if ($sourceBatch === null) {
            $context['error'] = 'No posted payroll batch for this employee covers '.$date->format('M j, Y').'.';

            return $context;
        }

        $employeeId = (int) $employee->employee_id;
        $defaultShift = $employee->timekeepingSetup?->shiftCode;
        $this->shiftResolver->loadOverridesForRange($employeeId, $date, $date);
        $shift = $shiftCodeId
            ? ShiftCode::query()->with('breaks')->find($shiftCodeId)
            : $this->shiftResolver->forDate($employeeId, $date, $defaultShift);

        $session = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $date, $date)
            ->first(fn (array $row) => $row['date']->toDateString() === $date->toDateString());

        $snapshot = PayrollAttendanceDay::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $date->toDateString())
            ->whereHas('payrollBatchDetail', fn ($query) => $query->where('payroll_batch_id', $sourceBatch->payroll_batch_id))
            ->first();

        $payTypeId = (int) ($calendar->pay_type_id ?? 0);
        $salaries = $payTypeId > 0
            ? $this->salaryResolver->salariesForPeriod($employeeId, $payTypeId, $date, $date)
            : collect();

        $context['date'] = $date;
        $context['employee'] = $employee;
        $context['shift'] = $shift;
        $context['policy'] = $employee->timekeepingSetup?->policy;
        $context['salary'] = $salaries->isNotEmpty()
            ? ($this->salaryResolver->salaryEffectiveOnDate($salaries, $date) ?? $salaries->last())
            : null;
        $context['recorded_time_in'] = $session['time_in'] ?? null;
        $context['recorded_time_out'] = $session['time_out'] ?? null;
        $context['source_batch'] = $sourceBatch;
        $context['snapshot'] = $snapshot;
        $context['paid_hours'] = [
            'basic' => round((float) ($snapshot?->basic ?? 0), 2),
            'ot' => round((float) ($snapshot?->ot ?? 0), 2),
            'late' => round((float) ($snapshot?->late ?? 0), 2),
            'undertime' => round((float) ($snapshot?->undertime ?? 0), 2),
        ];

        return $context;
    }

    private function postedBatchCoveringDate(int $employeeId, CarbonImmutable $date, int $excludeBatchId): ?PayrollBatch
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

        return $batchId ? PayrollBatch::query()->find($batchId) : null;
    }

    /**
     * OT minutes beyond the shift for entered punches. The dispute counts as the OT filing,
     * so excess is billable even when the policy requires filing.
     */
    private function excessOvertimeMinutes(
        CarbonImmutable $date,
        string $timeIn,
        string $timeOut,
        ?ShiftCode $shift,
        ?TimekeepingPolicy $policy,
        int $employeeId,
    ): int {
        $scheduleStart = $shift?->time_in;
        $scheduleEnd = $shift?->time_out;

        if (! filled($scheduleStart) || ! filled($scheduleEnd)) {
            return 0;
        }

        $isRegularDay = $this->holidayPay->isRegularWorkDay($employeeId, $date);

        if ($policy !== null && TimekeepingPolicySupport::allowsAutomaticOvertimeFromExcess($policy, $isRegularDay)) {
            $breakdown = $this->overtimePayroll->billableMinutesBreakdownForSession(
                $date,
                $timeIn,
                $timeOut,
                $scheduleStart,
                $scheduleEnd,
                $policy,
                $isRegularDay,
            );

            return (int) ($breakdown['regular_minutes'] ?? 0) + (int) ($breakdown['special_minutes'] ?? 0);
        }

        $startAt = $date->setTimeFromTimeString($scheduleStart);
        $endAt = $date->setTimeFromTimeString($scheduleEnd);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        $inAt = $date->setTimeFromTimeString($timeIn);
        $outAt = $date->setTimeFromTimeString($timeOut);

        if ($outAt->lessThanOrEqualTo($inAt)) {
            $outAt = $outAt->addDay();
        }

        $after = $outAt->gt($endAt) ? (int) $endAt->diffInMinutes($outAt) : 0;
        $before = ($policy?->is_consider_before_time && $inAt->lt($startAt)) ? (int) $inAt->diffInMinutes($startAt) : 0;
        $minMinutes = (int) round((float) ($policy?->min_minutes ?? 0));
        $minutes = 0;

        foreach ([$before, $after] as $part) {
            if ($part > 0 && $part >= $minMinutes) {
                $minutes += $part;
            }
        }

        return TimekeepingPolicySupport::applyRoundingMinutes($minutes, $policy?->overtime_rounding_id);
    }

    private function createIncome(PayrollBatchDetail $detail, ?string $incomeTypeCode, float $hours, float $amount, ?string $remarks): ?int
    {
        if ($incomeTypeCode === null) {
            return null;
        }

        $incomeTypeId = IncomeType::query()->where('income_type_code', $incomeTypeCode)->value('income_type_id');

        if ($incomeTypeId === null) {
            return null;
        }

        $income = PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $incomeTypeId,
            'hours' => $hours > 0 ? round($hours, 4) : null,
            'days' => null,
            'taxable' => round($amount, 2),
            'non_taxable' => 0,
            'orig_taxable' => round($amount, 2),
            'orig_non_taxable' => 0,
            'remarks' => $remarks,
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ]);

        return (int) $income->payroll_income_id;
    }

    private function createDeduction(PayrollBatchDetail $detail, string $deductionTypeCode, float $hours, float $amount, ?string $workDate): ?int
    {
        $deductionTypeId = DeductionType::query()->where('deduction_type_code', $deductionTypeCode)->value('deduction_type_id');

        if ($deductionTypeId === null) {
            return null;
        }

        $deduction = PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => $deductionTypeId,
            'hours' => $hours > 0 ? round($hours, 4) : null,
            'employee_amount' => round($amount, 2),
            'employer_amount' => 0,
            'reference_number' => 'Dispute',
            'dt_reference' => $workDate,
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ]);

        return (int) $deduction->payroll_deduction_id;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function paidSummary(array $context): ?array
    {
        if ($context['source_batch'] === null) {
            return null;
        }

        return [
            'batch_no' => $context['source_batch']->formattedBatchNo(),
            'has_snapshot' => $context['snapshot'] !== null,
            'basic_hours' => $context['paid_hours']['basic'],
            'ot_hours' => $context['paid_hours']['ot'],
            'late_minutes' => (int) round($context['paid_hours']['late'] * 60),
            'undertime_minutes' => (int) round($context['paid_hours']['undertime'] * 60),
        ];
    }

    private function shiftLabel(ShiftCode $shift): string
    {
        $schedule = filled($shift->time_in) && filled($shift->time_out)
            ? ' ('.$this->shortTime($shift->time_in).'–'.$this->shortTime($shift->time_out).')'
            : '';

        return trim(($shift->shift_code ?? '').$schedule);
    }

    private function shortTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function longTime(mixed $value): ?string
    {
        $short = $this->shortTime($value);

        return $short === null ? null : $short.':00';
    }
}
