<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayrollSettingOther;
use App\Models\PayType;
use App\Models\User;
use App\Models\WithholdingTaxComputation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollBatchService
{
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

    public function loadBatchForView(int $batchId): ?PayrollBatch
    {
        return PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'status',
                'createdBy',
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
            ])
            ->where('payroll_batch_id', $batchId)
            ->find($detailId);
    }

    public function prepareDetailTransactions(PayrollBatchDetail $detail): void
    {
        if ($detail->incomes()->exists()) {
            return;
        }

        $batch = $detail->payrollBatch;
        $payTypeId = $batch?->payrollCalendar?->pay_type_id;

        if (! $payTypeId) {
            return;
        }

        $salary = EmployeeSalary::query()
            ->where('pay_type_id', $payTypeId)
            ->whereHas('employmentInformation', fn ($query) => $query->where('employee_id', $detail->employee_id))
            ->with(['incomes.incomeType', 'deductions.deductionType'])
            ->orderByDesc('date_effective')
            ->orderByDesc('employee_salary_id')
            ->first();

        if (! $salary) {
            return;
        }

        DB::transaction(function () use ($detail, $salary) {
            foreach ($salary->incomes as $income) {
                PayrollIncome::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'income_type_id' => $income->income_type_id,
                    'taxable' => $income->taxable,
                    'non_taxable' => $income->non_taxable,
                    'orig_taxable' => $income->taxable,
                    'orig_non_taxable' => $income->non_taxable,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
            }

            foreach ($salary->deductions as $deduction) {
                PayrollDeduction::query()->create([
                    'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                    'deduction_type_id' => $deduction->deduction_type_id,
                    'employee_amount' => $deduction->employee_amount,
                    'employer_amount' => $deduction->employer_amount,
                    'is_editable' => true,
                    'is_deletable' => true,
                ]);
            }
        });

        $detail->load(['incomes.incomeType', 'deductions.deductionType']);
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
            ->whereHas('employmentInformations.salary', fn ($query) => $query->where('pay_type_id', $calendar->pay_type_id))
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
                $created = PayrollBatchDetail::query()->firstOrCreate([
                    'payroll_batch_id' => $batch->payroll_batch_id,
                    'employee_id' => $employeeId,
                ]);

                if ($created->wasRecentlyCreated) {
                    $added++;
                    $this->prepareDetailTransactions($created->load(['payrollBatch.payrollCalendar', 'employee']));
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
            $detail = PayrollBatchDetail::query()->create([
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $employeeId,
            ]);

            $this->prepareDetailTransactions($detail->load(['payrollBatch.payrollCalendar', 'employee']));
        }
    }
}
