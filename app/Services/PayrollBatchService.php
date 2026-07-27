<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\IncomeType;
use App\Models\PayrollLeave;
use App\Models\PayrollSettingOther;
use App\Models\PayType;
use App\Models\RawPayrollDeduction;
use App\Models\RawPayrollIncome;
use App\Models\RawPayrollLeave;
use App\Models\RawPayrollTransaction;
use App\Models\User;
use App\Models\WithholdingTaxComputation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollBatchService
{
    public function __construct(
        private readonly EmployeeLoadPayrollService $employeeLoadPayroll,
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly FacultyLoadPayrollService $facultyLoadPayroll,
        private readonly PayrollAttendanceLeaveService $attendanceLeavePayroll,
        private readonly PayrollOvertimeService $overtimePayroll,
        private readonly PayrollHoursWorkedPayrollService $hoursWorkedPayroll,
        private readonly GovernmentDeductionPayrollService $governmentDeductionPayroll,
        private readonly EmployeeSalaryResolverService $salaryResolver,
    ) {}
    /**
     * @return array{
     *     payTypes: \Illuminate\Database\Eloquent\Collection<int, PayType>,
     *     taxComputations: \Illuminate\Database\Eloquent\Collection<int, WithholdingTaxComputation>,
     *     suggestedBatchNo: int,
     *     yearsByPayType: array<int|string, list<int>>,
     *     periodsByPayType: array<int|string, array<int|string, list<array{id: int, label: string}>>>
     * }
     */
    public function formData(bool $forUpload = false): array
    {
        $payTypes = PayType::query()->orderBy('pay_type_id')->get();
        $taxComputations = WithholdingTaxComputation::query()
            ->orderBy('withholding_tax_computation_id')
            ->get();
        $suggestedBatchNo = $this->suggestedBatchNo();

        $calendarPeriods = PayrollCalendar::query()
            ->when($forUpload, fn (Builder $query) => $query->whereNotIn(
                'payroll_calendar_id',
                $this->postedPayrollCalendarIds(),
            ))
            ->orderByDesc('pay_year')
            ->orderBy('pay_period')
            ->get();

        $yearsByPayType = $calendarPeriods
            ->groupBy('pay_type_id')
            ->map(fn (Collection $periods) => $periods
                ->pluck('pay_year')
                ->map(fn ($year) => (int) $year)
                ->unique()
                ->sortDesc()
                ->values()
                ->all())
            ->all();

        $periodsByPayType = $calendarPeriods
            ->groupBy('pay_type_id')
            ->map(function (Collection $periods) {
                return $periods
                    ->groupBy(fn (PayrollCalendar $period) => (int) $period->pay_year)
                    ->map(function (Collection $yearPeriods) {
                        return $yearPeriods
                            ->map(fn (PayrollCalendar $period) => [
                                'id' => $period->payroll_calendar_id,
                                'label' => $period->formattedPayPeriod()
                                    .' ('.$period->dt_from->format('M j')
                                    .' – '.$period->dt_to->format('M j, Y').')',
                            ])
                            ->values()
                            ->all();
                    })
                    ->all();
            })
            ->all();

        return compact('payTypes', 'taxComputations', 'suggestedBatchNo', 'yearsByPayType', 'periodsByPayType');
    }

    public function assertCalendarOpenForUpload(int $calendarId): void
    {
        if (in_array($calendarId, $this->postedPayrollCalendarIds(), true)) {
            throw ValidationException::withMessages([
                'payroll_calendar_id' => 'The selected pay period has a posted payroll batch and cannot accept uploads.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function postedPayrollCalendarIds(): array
    {
        return PayrollBatch::query()
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->distinct()
            ->pluck('payroll_calendar_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function suggestedBatchNo(): int
    {
        $lastBatchNo = (int) PayrollSettingOther::settings()->last_batch_no;

        return max(1, $lastBatchNo + 1);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validate(array $input): array
    {
        $calendarId = (int) ($input['payroll_calendar_id'] ?? 0);

        return validator($input, [
            'batch_no' => [
                'required',
                'integer',
                'min:1',
                'max:99999999',
                Rule::unique('trn_payroll_batches', 'batch_no')
                    ->where(fn ($query) => $query
                        ->where('payroll_calendar_id', $calendarId)
                        ->whereNull('deleted_at')),
            ],
            'pay_type_id' => ['required', Rule::exists('lu_pay_types', 'pay_type_id')],
            'pay_year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::exists('tbl_payroll_calendar', 'pay_year')
                    ->where(fn ($query) => $query->where('pay_type_id', (int) ($input['pay_type_id'] ?? 0))),
            ],
            'payroll_calendar_id' => ['required', Rule::exists('tbl_payroll_calendar', 'payroll_calendar_id')],
            'withholding_tax_computation_id' => [
                'required',
                Rule::exists('lu_withholding_tax_computations', 'withholding_tax_computation_id'),
            ],
            'include_all_employees' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(User $user, array $validated): PayrollBatch
    {
        $calendar = PayrollCalendar::query()->findOrFail($validated['payroll_calendar_id']);

        if ((int) $calendar->pay_type_id !== (int) $validated['pay_type_id']) {
            throw ValidationException::withMessages([
                'payroll_calendar_id' => 'The selected pay period does not match the pay type.',
            ]);
        }

        if ((int) $calendar->pay_year !== (int) $validated['pay_year']) {
            throw ValidationException::withMessages([
                'payroll_calendar_id' => 'The selected pay period does not match the pay year.',
            ]);
        }

        return DB::transaction(function () use ($user, $validated, $calendar) {
            $batch = PayrollBatch::query()->create([
                'payroll_calendar_id' => $calendar->payroll_calendar_id,
                'batch_no' => (int) $validated['batch_no'],
                'created_by_id' => $user->id,
                'dt_created' => now(),
                'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
                'withholding_tax_computation_id' => (int) $validated['withholding_tax_computation_id'],
            ]);

            if (! empty($validated['include_all_employees'])) {
                $batch->load('payrollCalendar');
                $this->attachEligibleEmployees($batch);
            }

            PayrollSettingOther::settings()->update([
                'last_batch_no' => (int) $validated['batch_no'],
            ]);

            return $batch->fresh(['payrollCalendar.payType', 'status', 'createdBy']);
        });
    }

    public function isBatchEditable(PayrollBatch $batch): bool
    {
        return in_array((int) $batch->payroll_batch_status_id, [
            PayrollBatchStatus::PENDING,
            PayrollBatchStatus::LOCKED,
            PayrollBatchStatus::PROCESSED,
        ], true);
    }

    public function canProcessBatch(PayrollBatch $batch): bool
    {
        return (int) $batch->payroll_batch_status_id === PayrollBatchStatus::PENDING
            && $batch->details()->exists();
    }

    public function canReprocessBatch(PayrollBatch $batch): bool
    {
        return (int) $batch->payroll_batch_status_id === PayrollBatchStatus::PROCESSED
            && $batch->details()->exists();
    }

    public function canPostBatch(PayrollBatch $batch): bool
    {
        return (int) $batch->payroll_batch_status_id === PayrollBatchStatus::PROCESSED
            && $batch->details()->exists();
    }

    public function canUnpostBatch(PayrollBatch $batch): bool
    {
        return (int) $batch->payroll_batch_status_id === PayrollBatchStatus::POSTED;
    }

    public function detailHasPayrollData(PayrollBatchDetail $detail): bool
    {
        return $detail->incomes()->exists() || $detail->deductions()->exists();
    }

    /**
     * @param  array{income_type_id: int, taxable?: float|null, non_taxable?: float|null}  $validated
     */
    public function addIncomeToDetail(PayrollBatch $batch, PayrollBatchDetail $detail, array $validated): PayrollIncome
    {
        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }

        if (! $this->isBatchEditable($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be modified.',
            ]);
        }

        if (! $this->detailHasPayrollData($detail)) {
            throw ValidationException::withMessages([
                'batch' => 'Process the payroll batch first before adding income lines.',
            ]);
        }

        $taxable = round((float) ($validated['taxable'] ?? 0), 2);
        $nonTaxable = round((float) ($validated['non_taxable'] ?? 0), 2);

        if ($taxable <= 0 && $nonTaxable <= 0) {
            throw ValidationException::withMessages([
                'taxable' => 'Enter a taxable and/or non-taxable amount greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($batch, $detail, $validated, $taxable, $nonTaxable) {
            $income = PayrollIncome::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'income_type_id' => (int) $validated['income_type_id'],
                'taxable' => $taxable,
                'non_taxable' => $nonTaxable,
                'orig_taxable' => $taxable,
                'orig_non_taxable' => $nonTaxable,
                'is_manual' => true,
                'is_editable' => true,
                'is_deletable' => true,
            ]);

            $detail->load('incomes');
            $this->governmentDeductionPayroll->refreshGovernmentDeductionsForDetail($detail, $batch);

            return $income->load('incomeType');
        });
    }

    /**
     * @param  array{
     *     deduction_type_id: int,
     *     hours?: float|null,
     *     employee_amount?: float|null,
     *     employer_amount?: float|null,
     *     reference_number?: string|null,
     *     reference_date?: string|null
     * }  $validated
     */
    public function addDeductionToDetail(PayrollBatch $batch, PayrollBatchDetail $detail, array $validated): PayrollDeduction
    {
        if ((int) $detail->payroll_batch_id !== (int) $batch->payroll_batch_id) {
            abort(404);
        }

        if (! $this->isBatchEditable($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be modified.',
            ]);
        }

        if (! $this->detailHasPayrollData($detail)) {
            throw ValidationException::withMessages([
                'batch' => 'Process the payroll batch first before adding deduction lines.',
            ]);
        }

        $deductionType = DeductionType::query()->find((int) $validated['deduction_type_id']);

        if (! $deductionType || $deductionType->is_valid_govt_deduction) {
            throw ValidationException::withMessages([
                'deduction_type_id' => 'Government deductions cannot be added manually. They are computed automatically.',
            ]);
        }

        $employeeAmount = round((float) ($validated['employee_amount'] ?? 0), 2);
        $employerAmount = round((float) ($validated['employer_amount'] ?? 0), 2);
        $hours = isset($validated['hours']) ? round((float) $validated['hours'], 4) : null;
        $code = $deductionType->deduction_type_code;

        if (in_array($code, ['LTDE', 'UTDE'], true)) {
            if ($hours === null || $hours <= 0) {
                throw ValidationException::withMessages([
                    'hours' => 'Hours is required and must be greater than zero for Late (LTDE) and Undertime (UTDE).',
                ]);
            }
        } else {
            $hours = null;
        }

        if ($employeeAmount <= 0 && $employerAmount <= 0) {
            throw ValidationException::withMessages([
                'employee_amount' => 'Enter an employee amount and/or employer share greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($detail, $validated, $deductionType, $employeeAmount, $employerAmount, $hours) {
            return PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $deductionType->deduction_type_id,
                'hours' => $hours,
                'employee_amount' => $employeeAmount,
                'employer_amount' => $employerAmount,
                'reference_number' => $validated['reference_number'] ?? null,
                'dt_reference' => ! empty($validated['reference_date'])
                    ? Carbon::parse((string) $validated['reference_date'])
                    : null,
                'is_manual' => true,
                'is_editable' => true,
                'is_deletable' => true,
            ])->load('deductionType');
        });
    }

    public function processBatch(User $user, PayrollBatch $batch): int
    {
        if (! $this->canProcessBatch($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be processed.',
            ]);
        }

        $details = PayrollBatchDetail::query()
            ->where('payroll_batch_id', $batch->payroll_batch_id)
            ->with(['payrollBatch.payrollCalendar', 'employee.timekeepingSetup.policy', 'employee.timekeepingSetup.shiftCode'])
            ->get();

        $processed = 0;

        DB::transaction(function () use ($user, $batch, $details, &$processed) {
            foreach ($details as $detail) {
                $this->prepareDetailTransactions($detail);
                $processed++;
            }

            $batch->update([
                'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
                'processed_by_id' => $user->id,
                'dt_processed' => now(),
                'progress_total' => $details->count(),
                'progress_current' => $details->count(),
            ]);
        });

        return $processed;
    }

    public function reprocessBatch(User $user, PayrollBatch $batch): int
    {
        if (! $this->canReprocessBatch($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be re-processed.',
            ]);
        }

        $details = PayrollBatchDetail::query()
            ->where('payroll_batch_id', $batch->payroll_batch_id)
            ->with(['payrollBatch.payrollCalendar', 'employee.timekeepingSetup.policy', 'employee.timekeepingSetup.shiftCode'])
            ->get();

        $processed = 0;

        DB::transaction(function () use ($user, $batch, $details, &$processed) {
            foreach ($details as $detail) {
                $this->clearDetailTransactions($detail, preserveManualLines: true);
                $this->prepareDetailTransactions($detail);
                $processed++;
            }

            $batch->update([
                'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
                'processed_by_id' => $user->id,
                'dt_processed' => now(),
                'progress_total' => $details->count(),
                'progress_current' => $details->count(),
            ]);
        });

        return $processed;
    }

    public function postBatch(User $user, PayrollBatch $batch): void
    {
        if (! $this->canPostBatch($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be posted.',
            ]);
        }

        $batch->update([
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
            'posted_by_id' => $user->id,
            'dt_posted' => now(),
        ]);
    }

    public function unpostBatch(User $user, PayrollBatch $batch): void
    {
        if (! $this->canUnpostBatch($batch)) {
            throw ValidationException::withMessages([
                'batch' => 'This payroll batch cannot be unposted.',
            ]);
        }

        $batch->update([
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
            'posted_by_id' => null,
            'dt_posted' => null,
        ]);
    }

    public function loadBatchForView(int $batchId): ?PayrollBatch
    {
        return PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'status',
                'createdBy',
                'processedBy',
                'postedBy',
                'withholdingTaxComputation',
            ])
            ->find($batchId);
    }

    public function loadBatchDetailForView(int $detailId, int $batchId): ?PayrollBatchDetail
    {
        return PayrollBatchDetail::query()
            ->with([
                'employee',
                'payrollBatch.payrollCalendar.payType',
                'payrollBatch.status',
                'incomes.incomeType',
                'deductions.deductionType',
                'leaves.leaveType',
            ])
            ->where('payroll_batch_id', $batchId)
            ->find($detailId);
    }

    public function prepareDetailTransactions(PayrollBatchDetail $detail): void
    {
        if ($detail->incomes()->where(function (Builder $query) {
            $query->where('is_manual', false)->orWhereNull('is_manual');
        })->exists()) {
            return;
        }

        $batch = $detail->payrollBatch;
        $calendar = $batch?->payrollCalendar;
        $payTypeId = $calendar?->pay_type_id;

        if (! $payTypeId || ! $calendar) {
            return;
        }

        $salaries = $this->salaryResolver->salariesForPeriod(
            (int) $detail->employee_id,
            (int) $payTypeId,
            $calendar->dt_from,
            $calendar->dt_to,
        );

        if ($salaries->isEmpty()) {
            return;
        }

        $salary = $salaries->last();

        if (! $salary) {
            return;
        }

        $employee = $detail->employee?->loadMissing(['timekeepingSetup.policy', 'timekeepingSetup.shiftCode.breaks']);
        $policy = $employee?->timekeepingSetup?->policy;
        $shiftCode = $employee?->timekeepingSetup?->shiftCode;
        $isFaculty = $employee?->isFaculty() ?? false;
        // Faculty always use teaching-load attendance (even when salary Basic Computation is Leaves).
        $usesAttendancePayroll = $isFaculty || $this->employeeLoadPayroll->usesEmployeeLoad($salary);
        $loadPayroll = $usesAttendancePayroll
            ? $this->resolveAttendancePayroll(
                $salaries,
                $employee,
                (int) $detail->employee_id,
                $employee?->employee_number,
                $calendar->dt_from,
                $calendar->dt_to,
                $policy,
                $shiftCode?->time_in,
                $shiftCode?->time_out,
                $shiftCode,
            )
            : null;

        $leaveRecords = ($policy && $this->employeeLoadPayroll->usesEmployeeLoad($salary))
            ? $this->resolveAttendanceLeaveRecords(
                $salary,
                (int) $detail->employee_id,
                $employee?->employee_number,
                $calendar->dt_from,
                $calendar->dt_to,
                $policy,
                $shiftCode?->time_in,
                $shiftCode?->time_out,
                $shiftCode,
            )
            : [];

        $hoursWorkedIncomes = $this->hoursWorkedPayroll->computeIncomeTotalsForDetail($detail, $salary);

        DB::transaction(function () use ($detail, $salaries, $salary, $loadPayroll, $leaveRecords, $batch, $hoursWorkedIncomes, $calendar, $usesAttendancePayroll) {
            $salaryIncomeTypeIds = $salaries
                ->flatMap(fn (EmployeeSalary $salaryRecord) => $salaryRecord->incomes->pluck('income_type_id'))
                ->unique()
                ->values()
                ->all();
            $proratedIncomeLines = $this->proratedIncomeLines($salaries, $calendar->dt_from, $calendar->dt_to);

            foreach ($proratedIncomeLines as $incomeLine) {
                $taxable = (float) $incomeLine['taxable'];
                $nonTaxable = (float) $incomeLine['non_taxable'];
                $isBasicIncome = ($incomeLine['incomeType']?->is_default_basic ?? false)
                    || ($incomeLine['incomeType']?->income_type_code ?? null) === 'BASC';

                if ($hoursWorkedIncomes !== null && $isBasicIncome) {
                    $amounts = $hoursWorkedIncomes['by_income_type'][$incomeLine['income_type_id']] ?? [
                        'taxable' => 0.0,
                        'non_taxable' => 0.0,
                        'hours' => 0.0,
                    ];
                    $taxable = $amounts['taxable'];
                    $nonTaxable = $amounts['non_taxable'];
                    $hours = $this->incomeHoursFromAmounts($amounts);
                } elseif ($loadPayroll !== null && $isBasicIncome) {
                    $taxable = $loadPayroll['basic_taxable'];
                    $nonTaxable = $loadPayroll['basic_non_taxable'];
                    $hours = $this->incomeHoursFromAmounts([
                        'hours' => (float) ($loadPayroll['computed_hours'] ?? 0),
                    ]);
                } elseif ($usesAttendancePayroll && $isBasicIncome) {
                    // Time-In/Time-Out (or faculty load path) with no punches / loads / hours-worked
                    // → do not fall back to the fixed salary BASC amount.
                    $taxable = 0.0;
                    $nonTaxable = 0.0;
                    $hours = null;
                } else {
                    $hours = null;
                }

                PayrollIncome::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'income_type_id' => $incomeLine['income_type_id'],
                    'hours' => $hours,
                    'taxable' => $taxable,
                    'non_taxable' => $nonTaxable,
                    'orig_taxable' => $taxable,
                    'orig_non_taxable' => $nonTaxable,
                    'is_manual' => false,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
            }

            if ($hoursWorkedIncomes !== null) {
                foreach ($hoursWorkedIncomes['by_income_type'] as $incomeTypeId => $amounts) {
                    if (in_array($incomeTypeId, $salaryIncomeTypeIds, true)) {
                        continue;
                    }

                    if (($amounts['taxable'] ?? 0) <= 0 && ($amounts['non_taxable'] ?? 0) <= 0) {
                        continue;
                    }

                    PayrollIncome::query()->create([
                        'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                        'income_type_id' => $incomeTypeId,
                        'hours' => $this->incomeHoursFromAmounts($amounts),
                        'taxable' => $amounts['taxable'],
                        'non_taxable' => $amounts['non_taxable'],
                        'orig_taxable' => $amounts['taxable'],
                        'orig_non_taxable' => $amounts['non_taxable'],
                        'is_manual' => false,
                        'is_editable' => true,
                        'is_deletable' => true,
                    ]);
                }
            }

            foreach ($this->proratedDeductionLines($salaries, $calendar->dt_from, $calendar->dt_to) as $deductionLine) {
                if ($deductionLine['deductionType']?->is_valid_govt_deduction) {
                    continue;
                }

                PayrollDeduction::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'deduction_type_id' => $deductionLine['deduction_type_id'],
                    'employee_amount' => $deductionLine['employee_amount'],
                    'employer_amount' => $deductionLine['employer_amount'],
                    'is_manual' => false,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
            }

            // Persist tardiness before PhilHealth so percent brackets use Basic − Tardiness.
            if ($loadPayroll !== null) {
                $lateMinutes = (int) ($loadPayroll['late_minutes'] ?? 0) + (int) ($loadPayroll['break_late_minutes'] ?? 0);
                $lateDeduction = round((float) ($loadPayroll['late_deduction'] ?? 0) + (float) ($loadPayroll['break_late_deduction'] ?? 0), 2);

                if ($lateDeduction > 0) {
                    $lateDeductionTypeId = $this->timeLogsPayroll->lateDeductionTypeId()
                        ?? $this->employeeLoadPayroll->lateDeductionTypeId();

                    if ($lateDeductionTypeId !== null) {
                        PayrollDeduction::query()->create([
                            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                            'deduction_type_id' => $lateDeductionTypeId,
                            'hours' => round($lateMinutes / 60, 4),
                            'employee_amount' => $lateDeduction,
                            'employer_amount' => 0,
                            'is_manual' => false,
                            'is_editable' => true,
                            'is_deletable' => true,
                        ]);
                    }
                }
            }

            if ($loadPayroll !== null && ($loadPayroll['undertime_deduction'] ?? 0) > 0) {
                $undertimeDeductionTypeId = $this->timeLogsPayroll->undertimeDeductionTypeId()
                    ?? $this->employeeLoadPayroll->undertimeDeductionTypeId();

                if ($undertimeDeductionTypeId !== null) {
                    PayrollDeduction::query()->create([
                        'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                        'deduction_type_id' => $undertimeDeductionTypeId,
                        'hours' => round(((int) ($loadPayroll['undertime_minutes'] ?? 0)) / 60, 4),
                        'employee_amount' => $loadPayroll['undertime_deduction'],
                        'employer_amount' => 0,
                        'is_manual' => false,
                        'is_editable' => true,
                        'is_deletable' => true,
                    ]);
                }
            }

            if ($loadPayroll !== null && ($loadPayroll['overtime_pay'] ?? 0) > 0 && ! ($hoursWorkedIncomes['has_overtime'] ?? false)) {
                $this->persistOvertimeIncome(
                    $detail,
                    (float) $loadPayroll['overtime_pay'],
                    round(((int) ($loadPayroll['overtime_minutes'] ?? 0)) / 60, 4),
                );
            }

            $detail->load('incomes');
            $batch->loadMissing('payrollCalendar.deductions.deductionType');
            $this->governmentDeductionPayroll->persistLines(
                $detail,
                $this->governmentDeductionPayroll->computeForDetail($detail, $batch),
            );

            $this->persistAttendanceLeaveRecords($detail, $leaveRecords);
            $this->applyUploadedDeductionsToDetail($detail);
            $this->applyUploadedIncomesToDetail($detail);
            $this->applyUploadedLeavesToDetail($detail);
        });

        $detail->load(['incomes.incomeType', 'deductions.deductionType', 'leaves.leaveType']);
    }

    /**
     * Apply raw uploaded deduction rows for this employee's calendar onto a batch detail.
     * Prefer emp_amount / empr_amount; fall back to amount as employee share when those are blank.
     */
    public function applyUploadedDeductionsToDetail(PayrollBatchDetail $detail): int
    {
        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch')->payrollBatch;
        $calendarId = $batch?->payroll_calendar_id;

        if ($calendarId === null) {
            return 0;
        }

        $rows = RawPayrollDeduction::query()
            ->where('employee_id', $detail->employee_id)
            ->whereHas(
                'payrollTransaction',
                fn (Builder $query) => $query->where('payroll_calendar_id', $calendarId),
            )
            ->orderBy('payroll_deduction_id')
            ->get();

        return $this->persistUploadedDeductionRows($detail, $rows);
    }

    /**
     * Apply raw uploaded income rows for this employee's calendar onto a batch detail.
     */
    public function applyUploadedIncomesToDetail(PayrollBatchDetail $detail): int
    {
        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch')->payrollBatch;
        $calendarId = $batch?->payroll_calendar_id;

        if ($calendarId === null) {
            return 0;
        }

        $rows = RawPayrollIncome::query()
            ->where('employee_id', $detail->employee_id)
            ->whereHas(
                'payrollTransaction',
                fn (Builder $query) => $query->where('payroll_calendar_id', $calendarId),
            )
            ->orderBy('payroll_income_id')
            ->get();

        return $this->persistUploadedIncomeRows($detail, $rows);
    }

    /**
     * Recompute income lines from uploaded hours worked for an already-processed batch detail.
     */
    public function applyUploadedHoursWorkedToDetail(PayrollBatchDetail $detail): int
    {
        if (! $this->detailHasPayrollData($detail)) {
            return 0;
        }

        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch')->payrollBatch;
        $payTypeId = $batch?->payrollCalendar?->pay_type_id;

        if ($payTypeId === null) {
            return 0;
        }

        $salary = EmployeeSalary::query()
            ->where('pay_type_id', $payTypeId)
            ->whereHas('employmentInformation', fn ($query) => $query->where('employee_id', $detail->employee_id))
            ->with(['incomes.incomeType'])
            ->orderByDesc('date_effective_from')
            ->orderByDesc('employee_salary_id')
            ->first();

        if ($salary === null) {
            return 0;
        }

        $hoursWorkedIncomes = $this->hoursWorkedPayroll->computeIncomeTotalsForDetail($detail, $salary);

        if ($hoursWorkedIncomes === null) {
            return 0;
        }

        $incomeTypeIds = array_map('intval', array_keys($hoursWorkedIncomes['by_income_type']));

        if ($incomeTypeIds === []) {
            return 0;
        }

        DB::transaction(function () use ($detail, $salary, $hoursWorkedIncomes, $incomeTypeIds, $batch): void {
            PayrollIncome::query()
                ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
                ->whereIn('income_type_id', $incomeTypeIds)
                ->where(function (Builder $query) {
                    $query->where('is_manual', false)->orWhereNull('is_manual');
                })
                ->delete();

            $applied = $this->persistHoursWorkedIncomeTotals($detail, $salary, $hoursWorkedIncomes);

            if ($applied > 0) {
                $detail->load('incomes');
                $batch->loadMissing('payrollCalendar.deductions.deductionType');
                $this->governmentDeductionPayroll->persistLines(
                    $detail,
                    $this->governmentDeductionPayroll->computeForDetail($detail, $batch),
                );
            }
        });

        return count($hoursWorkedIncomes['by_income_type']);
    }

    /**
     * Immediately merge a newly committed upload into PROCESSED (or editable) batches
     * for the same payroll calendar.
     */
    public function applyRawUploadToOpenBatches(RawPayrollTransaction $transaction): int
    {
        $transaction->loadMissing(['deductionRecords', 'incomeRecords', 'hoursWorkedRecords']);
        $calendarId = (int) $transaction->payroll_calendar_id;

        if ($calendarId <= 0) {
            return 0;
        }

        $employeeIds = $transaction->deductionRecords
            ->pluck('employee_id')
            ->merge($transaction->incomeRecords->pluck('employee_id'))
            ->merge($transaction->hoursWorkedRecords->pluck('employee_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($employeeIds === []) {
            return 0;
        }

        $batches = PayrollBatch::query()
            ->where('payroll_calendar_id', $calendarId)
            ->whereIn('payroll_batch_status_id', [
                PayrollBatchStatus::PENDING,
                PayrollBatchStatus::PROCESSED,
            ])
            ->with(['details' => fn ($query) => $query->whereIn('employee_id', $employeeIds)])
            ->get();

        $applied = 0;

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! $this->detailHasPayrollData($detail)) {
                    continue;
                }

                $deductionRows = $transaction->deductionRecords
                    ->where('employee_id', $detail->employee_id)
                    ->values();

                $incomeRows = $transaction->incomeRecords
                    ->where('employee_id', $detail->employee_id)
                    ->values();

                $hoursWorkedRows = $transaction->hoursWorkedRecords
                    ->where('employee_id', $detail->employee_id)
                    ->values();

                $applied += $this->persistUploadedDeductionRows($detail, $deductionRows);
                $applied += $this->persistUploadedIncomeRows($detail, $incomeRows);

                if ($hoursWorkedRows->isNotEmpty()) {
                    $applied += $this->applyUploadedHoursWorkedToDetail($detail);
                }
            }
        }

        return $applied;
    }

    /**
     * @param  Collection<int, RawPayrollDeduction>  $rows
     */
    private function persistUploadedDeductionRows(PayrollBatchDetail $detail, Collection $rows): int
    {
        $applied = 0;

        foreach ($rows as $row) {
            $employeeAmount = $row->employee_amount !== null ? (float) $row->employee_amount : 0.0;
            $employerAmount = $row->employer_amount !== null ? (float) $row->employer_amount : 0.0;

            if ($employeeAmount <= 0 && $employerAmount <= 0 && $row->amount !== null) {
                $employeeAmount = (float) $row->amount;
            }

            if ($employeeAmount <= 0 && $employerAmount <= 0) {
                continue;
            }

            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $row->deduction_type_id,
                'hours' => $row->hours !== null ? (float) $row->hours : null,
                'employee_amount' => round($employeeAmount, 2),
                'employer_amount' => round($employerAmount, 2),
                'reference_number' => $row->reference_number,
                'dt_reference' => $row->dt_reference,
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);

            $applied++;
        }

        return $applied;
    }

    /**
     * @param  Collection<int, RawPayrollIncome>  $rows
     */
    private function persistUploadedIncomeRows(PayrollBatchDetail $detail, Collection $rows): int
    {
        $applied = 0;

        foreach ($rows as $row) {
            $taxable = $row->taxable !== null ? (float) $row->taxable : 0.0;
            $nonTaxable = $row->non_taxable !== null ? (float) $row->non_taxable : 0.0;

            if ($taxable <= 0 && $nonTaxable <= 0 && $row->amount !== null) {
                $taxable = (float) $row->amount;
            }

            if ($taxable <= 0 && $nonTaxable <= 0) {
                continue;
            }

            PayrollIncome::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'income_type_id' => $row->income_type_id,
                'taxable' => round($taxable, 2),
                'non_taxable' => round($nonTaxable, 2),
                'orig_taxable' => round($taxable, 2),
                'orig_non_taxable' => round($nonTaxable, 2),
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);

            $applied++;
        }

        return $applied;
    }

    /**
     * @param  array{
     *     by_income_type: array<int, array{taxable: float, non_taxable: float, hours?: float}>,
     *     has_overtime: bool
     * }  $hoursWorkedIncomes
     */
    private function persistHoursWorkedIncomeTotals(
        PayrollBatchDetail $detail,
        EmployeeSalary $salary,
        array $hoursWorkedIncomes,
    ): int {
        $applied = 0;
        $salaryIncomeTypeIds = $salary->incomes->pluck('income_type_id')->all();

        foreach ($hoursWorkedIncomes['by_income_type'] as $incomeTypeId => $amounts) {
            if (($amounts['taxable'] ?? 0) <= 0 && ($amounts['non_taxable'] ?? 0) <= 0) {
                continue;
            }

            if (in_array($incomeTypeId, $salaryIncomeTypeIds, true)) {
                PayrollIncome::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'income_type_id' => $incomeTypeId,
                    'hours' => $this->incomeHoursFromAmounts($amounts),
                    'taxable' => $amounts['taxable'],
                    'non_taxable' => $amounts['non_taxable'],
                    'orig_taxable' => $amounts['taxable'],
                    'orig_non_taxable' => $amounts['non_taxable'],
                    'is_manual' => false,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
                $applied++;

                continue;
            }

            PayrollIncome::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'income_type_id' => $incomeTypeId,
                'hours' => $this->incomeHoursFromAmounts($amounts),
                'taxable' => $amounts['taxable'],
                'non_taxable' => $amounts['non_taxable'],
                'orig_taxable' => $amounts['taxable'],
                'orig_non_taxable' => $amounts['non_taxable'],
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);
            $applied++;
        }

        return $applied;
    }

    /**
     * @return array{
     *     worked_days: int,
     *     basic_taxable: float,
     *     basic_non_taxable: float,
     *     late_minutes: int,
     *     late_deduction: float,
     *     undertime_minutes: int,
     *     undertime_deduction: float,
     *     overtime_minutes: int,
     *     overtime_pay: float,
     *     break_late_minutes: int,
     *     break_late_deduction: float,
     *     absent_sessions: int
     * }|null
     */
    private function resolveAttendancePayroll(
        Collection $salaries,
        ?Employee $employee,
        int $employeeId,
        ?string $employeeNumber,
        \Carbon\CarbonInterface $from,
        \Carbon\CarbonInterface $to,
        ?\App\Models\TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode = null,
    ): ?array {
        $salary = $salaries->last();

        if ($salary === null) {
            return null;
        }

        $result = null;
        $overtimeMinutes = 0;
        $breakLateMinutes = 0;

        if ($employee !== null && $this->facultyLoadPayroll->shouldUseFacultyLoadPath($employee, $salary, $from, $to)) {
            // Always apply faculty result (including zeros) so Leaves/fixed BASC is never paid
            // when there are no teaching loads / no logs for load days.
            $result = $this->facultyLoadPayroll->computeForPeriod(
                $salary,
                $employee,
                $from,
                $to,
                $policy,
                $shiftCode,
            );
        } elseif ($this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
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
                );

            if (! ($shiftCode?->is_flexi_time)) {
                $overtimeMinutes = $this->overtimePayroll->totalBillableMinutes(
                    $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to),
                    $policy,
                    $scheduleStart,
                    $scheduleEnd,
                );
            }

            $breakLateMinutes = ($shiftCode?->is_flexi_time)
                ? 0
                : $this->timeLogsPayroll->totalBreakLateMinutes(
                    $employeeId,
                    $from,
                    $to,
                    $policy,
                    $shiftCode,
                );
        } else {
            $loadPayroll = $salaries->count() > 1
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

            if (
                $loadPayroll['worked_days'] > 0
                || $loadPayroll['late_minutes'] > 0
                || $loadPayroll['undertime_minutes'] > 0
            ) {
                $result = $loadPayroll;
                $overtimeMinutes = $this->overtimePayroll->totalBillableMinutesFromEntries(
                    $this->employeeLoadPayroll->entriesForEmployeeInPeriod($employeeId, $employeeNumber, $from, $to),
                    $policy,
                );
            }
        }

        if ($result === null) {
            return null;
        }

        $result['overtime_minutes'] = $overtimeMinutes;
        $result['overtime_pay'] = $this->overtimePayroll->overtimePay($salary, $overtimeMinutes);
        $result['break_late_minutes'] = $breakLateMinutes;
        $hourlyRate = $salary->hourlyRate();
        $result['break_late_deduction'] = ($breakLateMinutes > 0 && $hourlyRate !== null)
            ? round(($breakLateMinutes / 60) * $hourlyRate, 2)
            : 0.0;

        return $result;
    }

    /**
     * @param  array{hours?: float}  $amounts
     */
    private function incomeHoursFromAmounts(array $amounts): ?float
    {
        $hours = (float) ($amounts['hours'] ?? 0);

        return $hours > 0 ? round($hours, 4) : null;
    }

    private function persistOvertimeIncome(PayrollBatchDetail $detail, float $overtimePay, ?float $hours = null): void
    {
        $overtimeIncomeTypeId = IncomeType::query()
            ->where('income_type_code', 'OVRT')
            ->value('income_type_id');

        if ($overtimeIncomeTypeId === null) {
            return;
        }

        $existing = PayrollIncome::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('income_type_id', $overtimeIncomeTypeId)
            ->first();

        $payload = [
            'hours' => $hours,
            'taxable' => round($overtimePay, 2),
            'non_taxable' => 0.0,
            'orig_taxable' => round($overtimePay, 2),
            'orig_non_taxable' => 0.0,
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $overtimeIncomeTypeId,
            ...$payload,
        ]);
    }

    /**
     * @return list<array{
     *     leave_type_id: int,
     *     dt_from: \Carbon\CarbonInterface,
     *     dt_to: \Carbon\CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>
     */
    private function resolveAttendanceLeaveRecords(
        EmployeeSalary $salary,
        int $employeeId,
        ?string $employeeNumber,
        \Carbon\CarbonInterface $from,
        \Carbon\CarbonInterface $to,
        ?\App\Models\TimekeepingPolicy $policy,
        ?string $scheduleStart,
        ?string $scheduleEnd,
        ?\App\Models\ShiftCode $shiftCode = null,
    ): array {
        if ($policy === null) {
            return [];
        }

        if ($shiftCode?->is_flexi_time) {
            return [];
        }

        if ($this->timeLogsPayroll->hasPunchesInPeriod($employeeId, $from, $to)) {
            $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to);

            return array_merge(
                $this->attendanceLeavePayroll->buildFromTimeLogSessions(
                    $sessions,
                    $policy,
                    $scheduleStart,
                    $scheduleEnd,
                    $salary,
                ),
                $this->attendanceLeavePayroll->buildBreakTardinessFromTimeLogPunches(
                    $employeeId,
                    $from,
                    $to,
                    $policy,
                    $shiftCode,
                ),
            );
        }

        $entries = $this->employeeLoadPayroll->entriesForEmployeeInPeriod($employeeId, $employeeNumber, $from, $to);

        if ($entries->isEmpty()) {
            return [];
        }

        return $this->attendanceLeavePayroll->buildFromEmployeeLoadEntries($entries, $policy, $salary);
    }

    /**
     * @param  list<array{
     *     leave_type_id: int,
     *     dt_from: \Carbon\CarbonInterface,
     *     dt_to: \Carbon\CarbonInterface,
     *     leave_hours: float,
     *     reason: string|null
     * }>  $leaveRecords
     */
    private function persistAttendanceLeaveRecords(PayrollBatchDetail $detail, array $leaveRecords): void
    {
        $detail->leaves()
            ->where(function (Builder $query) {
                $query->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->withTrashed()
            ->each(fn (PayrollLeave $leave) => $leave->forceDelete());

        foreach ($leaveRecords as $record) {
            if (($record['leave_hours'] ?? 0) <= 0) {
                continue;
            }

            PayrollLeave::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'leave_type_id' => $record['leave_type_id'],
                'dt_from' => $record['dt_from'],
                'dt_to' => $record['dt_to'],
                'leave_hours' => $record['leave_hours'],
                'reason' => $record['reason'] ?? null,
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);
        }
    }

    public function applyUploadedLeavesToDetail(PayrollBatchDetail $detail): int
    {
        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch')->payrollBatch;
        $calendarId = $batch?->payroll_calendar_id;

        if ($calendarId === null) {
            return 0;
        }

        $rows = RawPayrollLeave::query()
            ->where('employee_id', $detail->employee_id)
            ->whereHas(
                'payrollTransaction',
                fn (Builder $query) => $query->where('payroll_calendar_id', $calendarId),
            )
            ->orderBy('payroll_leave_id')
            ->get();

        $applied = 0;

        foreach ($rows as $row) {
            PayrollLeave::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'leave_type_id' => $row->leave_type_id,
                'dt_from' => $row->dt_from,
                'dt_to' => $row->dt_to,
                'leave_hours' => $row->leave_hours,
                'reason' => $row->reason,
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);

            $applied++;
        }

        return $applied;
    }

    public function clearDetailTransactions(PayrollBatchDetail $detail, bool $preserveManualLines = false): void
    {
        if ($preserveManualLines) {
            $detail->incomes()
                ->where(function (Builder $query) {
                    $query->where('is_manual', false)->orWhereNull('is_manual');
                })
                ->withTrashed()
                ->each(fn (PayrollIncome $income) => $income->forceDelete());

            $detail->deductions()
                ->where(function (Builder $query) {
                    $query->where('is_manual', false)->orWhereNull('is_manual');
                })
                ->withTrashed()
                ->each(fn (PayrollDeduction $deduction) => $deduction->forceDelete());

            $detail->leaves()
                ->where(function (Builder $query) {
                    $query->where('is_manual', false)->orWhereNull('is_manual');
                })
                ->withTrashed()
                ->each(fn (PayrollLeave $leave) => $leave->forceDelete());
        } else {
            $detail->incomes()->withTrashed()->each(fn (PayrollIncome $income) => $income->forceDelete());
            $detail->deductions()->withTrashed()->each(fn (PayrollDeduction $deduction) => $deduction->forceDelete());
            $detail->leaves()->withTrashed()->each(fn (PayrollLeave $leave) => $leave->forceDelete());
        }
    }

    /**
     * @return Builder<PayrollBatchDetail>
     */
    public function batchEmployeesQuery(PayrollBatch $batch, ?string $search = null): Builder
    {
        return PayrollBatchDetail::query()
            ->with('employee')
            ->where('trn_payroll_batch_details.payroll_batch_id', $batch->payroll_batch_id)
            ->join('tbl_employees', 'tbl_employees.employee_id', '=', 'trn_payroll_batch_details.employee_id')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function (Builder $employeeQuery) use ($term) {
                    $employeeQuery
                        ->where('tbl_employees.employee_number', 'like', $term)
                        ->orWhere('tbl_employees.first_name', 'like', $term)
                        ->orWhere('tbl_employees.middle_name', 'like', $term)
                        ->orWhere('tbl_employees.last_name', 'like', $term);
                });
            })
            ->orderBy('tbl_employees.last_name')
            ->orderBy('tbl_employees.first_name')
            ->select('trn_payroll_batch_details.*');
    }

    /**
     * @return Builder<Employee>
     */
    public function employeesWithPayTypeQuery(PayrollBatch $batch): Builder
    {
        $calendar = $batch->payrollCalendar;

        if (! $calendar) {
            return Employee::query()->whereRaw('1 = 0');
        }

        return Employee::query()
            ->where('is_active', true)
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->whereHas('employmentInformations.salaries', function ($query) use ($calendar) {
                $query->where('pay_type_id', $calendar->pay_type_id)
                    ->where('date_effective_from', '<=', $calendar->dt_to)
                    ->where(function ($overlap) use ($calendar) {
                        $overlap->whereNull('date_effective_to')
                            ->orWhere('date_effective_to', '>=', $calendar->dt_from);
                    });
            });
    }

    public function addEmployeesEmptyMessage(PayrollBatch $batch, bool $hasSearch): string
    {
        if ($hasSearch) {
            return 'No eligible employees match your search.';
        }

        $calendar = $batch->payrollCalendar;

        if (! $calendar) {
            return 'Payroll calendar not found for this batch.';
        }

        $payTypeLabel = $calendar->payType?->pay_type ?? 'this pay type';

        if ($this->employeesWithPayTypeQuery($batch)->count() === 0) {
            return 'No active employees have an employee salary with pay type '.$payTypeLabel
                .'. Check Employee → Employment → Employee Salary.';
        }

        return 'All eligible employees with pay type '.$payTypeLabel
            .' are already assigned to a batch for this pay period.';
    }

    /**
     * @return Builder<Employee>
     */
    public function eligibleEmployeesQuery(PayrollBatch $batch, ?string $search = null): Builder
    {
        $calendar = $batch->payrollCalendar;

        if (! $calendar) {
            return Employee::query()->whereRaw('1 = 0');
        }

        $alreadyAssigned = PayrollBatchDetail::query()
            ->whereHas('payrollBatch', fn ($query) => $query->where('payroll_calendar_id', $calendar->payroll_calendar_id))
            ->pluck('employee_id');

        return Employee::query()
            ->where('is_active', true)
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->whereHas('employmentInformations.salaries', function ($query) use ($calendar) {
                $query->where('pay_type_id', $calendar->pay_type_id)
                    ->where('date_effective_from', '<=', $calendar->dt_to)
                    ->where(function ($overlap) use ($calendar) {
                        $overlap->whereNull('date_effective_to')
                            ->orWhere('date_effective_to', '>=', $calendar->dt_from);
                    });
            })
            ->when($alreadyAssigned->isNotEmpty(), fn ($query) => $query->whereNotIn('employee_id', $alreadyAssigned))
            ->when($search !== null && $search !== '', fn ($query) => $query->search($search))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function addEmployees(PayrollBatch $batch, array $employeeIds, bool $includeAll = false): int
    {
        if (! $this->isBatchEditable($batch)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'This payroll batch can no longer be modified.',
            ]);
        }

        $calendar = $batch->payrollCalendar;

        if (! $calendar) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Payroll calendar not found for this batch.',
            ]);
        }

        if ($includeAll) {
            $employeeIds = $this->eligibleEmployeesQuery($batch)->pluck('employee_id')->all();
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Please select at least one employee.',
            ]);
        }

        $eligibleIds = $this->eligibleEmployeesQuery($batch)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->all();

        if (count($eligibleIds) !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'One or more selected employees are not eligible for this batch.',
            ]);
        }

        $added = 0;

        DB::transaction(function () use ($batch, $eligibleIds, &$added) {
            foreach ($eligibleIds as $employeeId) {
                if ($this->assignEmployeeToBatch($batch, (int) $employeeId)) {
                    $added++;
                }
            }

            if ($added > 0 && (int) $batch->payroll_batch_status_id !== PayrollBatchStatus::PENDING) {
                $batch->update(['payroll_batch_status_id' => PayrollBatchStatus::PENDING]);
            }
        });

        return $added;
    }

    /**
     * @param  list<int>  $detailIds
     */
    public function removeEmployees(PayrollBatch $batch, array $detailIds): int
    {
        if (! $this->isBatchEditable($batch)) {
            throw ValidationException::withMessages([
                'detail_ids' => 'This payroll batch can no longer be modified.',
            ]);
        }

        $detailIds = array_values(array_unique(array_map('intval', $detailIds)));

        if ($detailIds === []) {
            throw ValidationException::withMessages([
                'detail_ids' => 'Please select at least one employee to remove.',
            ]);
        }

        $details = PayrollBatchDetail::query()
            ->where('payroll_batch_id', $batch->payroll_batch_id)
            ->whereIn('payroll_batch_detail_id', $detailIds)
            ->with('employee')
            ->get();

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'detail_ids' => 'The selected employees may have already been removed.',
            ]);
        }

        $removed = 0;

        DB::transaction(function () use ($details, &$removed) {
            foreach ($details as $detail) {
                $detail->delete();
                $removed++;
            }
        });

        return $removed;
    }

    /**
     * Employee numbers assigned to any payroll batch for the given pay period.
     *
     * @return list<string>
     */
    public function employeeNumbersForCalendar(int $calendarId): array
    {
        return Employee::query()
            ->whereIn('employee_id', PayrollBatchDetail::query()
                ->whereHas('payrollBatch', fn (Builder $query) => $query->where('payroll_calendar_id', $calendarId))
                ->select('employee_id'))
            ->orderBy('employee_number')
            ->pluck('employee_number')
            ->map(fn ($number) => (string) $number)
            ->unique()
            ->values()
            ->all();
    }

    private function attachEligibleEmployees(PayrollBatch $batch): void
    {
        $employeeIds = $this->eligibleEmployeesQuery($batch)->pluck('employee_id');

        foreach ($employeeIds as $employeeId) {
            $this->assignEmployeeToBatch($batch, (int) $employeeId);
        }
    }

    private function assignEmployeeToBatch(PayrollBatch $batch, int $employeeId): bool
    {
        $detail = PayrollBatchDetail::query()->firstOrCreate([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employeeId,
        ]);

        return $detail->wasRecentlyCreated;
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return list<array{income_type_id: int, taxable: float, non_taxable: float, incomeType: mixed}>
     */
    private function proratedIncomeLines(Collection $salaries, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $lines = [];

        foreach ($salaries as $salaryRecord) {
            foreach ($salaryRecord->incomes as $income) {
                $incomeTypeId = (int) $income->income_type_id;

                if (! isset($lines[$incomeTypeId])) {
                    $lines[$incomeTypeId] = [
                        'income_type_id' => $incomeTypeId,
                        'taxable' => 0.0,
                        'non_taxable' => 0.0,
                        'incomeType' => $income->incomeType,
                    ];
                }

                $lines[$incomeTypeId]['taxable'] += $this->salaryResolver->prorateAmount(
                    (float) $income->taxable,
                    $salaryRecord,
                    $from,
                    $to,
                );
                $lines[$incomeTypeId]['non_taxable'] += $this->salaryResolver->prorateAmount(
                    (float) $income->non_taxable,
                    $salaryRecord,
                    $from,
                    $to,
                );
            }
        }

        return array_values($lines);
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return list<array{deduction_type_id: int, employee_amount: float, employer_amount: float, deductionType: mixed}>
     */
    private function proratedDeductionLines(Collection $salaries, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $lines = [];

        foreach ($salaries as $salaryRecord) {
            foreach ($salaryRecord->deductions as $deduction) {
                $deductionTypeId = (int) $deduction->deduction_type_id;

                if (! isset($lines[$deductionTypeId])) {
                    $lines[$deductionTypeId] = [
                        'deduction_type_id' => $deductionTypeId,
                        'employee_amount' => 0.0,
                        'employer_amount' => 0.0,
                        'deductionType' => $deduction->deductionType,
                    ];
                }

                $lines[$deductionTypeId]['employee_amount'] += $this->salaryResolver->prorateAmount(
                    (float) $deduction->employee_amount,
                    $salaryRecord,
                    $from,
                    $to,
                );
                $lines[$deductionTypeId]['employer_amount'] += $this->salaryResolver->prorateAmount(
                    (float) $deduction->employer_amount,
                    $salaryRecord,
                    $from,
                    $to,
                );
            }
        }

        return array_values($lines);
    }
}
