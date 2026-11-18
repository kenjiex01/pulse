<?php

namespace App\Services;

use App\Models\EmployeeDeduction;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;

class EmployeeDeductionPayrollService
{
    /**
     * Replace auto deduction lines that come from employee deduction records
     * when that deduction type is checked on the pay period.
     */
    public function syncForDetail(PayrollBatchDetail $detail): int
    {
        $this->clearAutoLines($detail);

        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch.payrollCalendar')->payrollBatch;
        $calendar = $batch?->payrollCalendar;

        if ($calendar === null || $detail->employee_id === null || $calendar->dt_to === null) {
            return 0;
        }

        $calendar->loadMissing('deductions');
        $deductionTypeIds = $calendar->deductions
            ->pluck('deduction_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($deductionTypeIds === []) {
            return 0;
        }

        $records = EmployeeDeduction::query()
            ->where('employee_id', $detail->employee_id)
            ->whereIn('deduction_type_id', $deductionTypeIds)
            ->whereDate('dt_deduction', '<=', $calendar->dt_to)
            ->get();

        $manualIds = $detail->deductions()
            ->where('is_manual', true)
            ->whereNotNull('employee_deduction_id')
            ->pluck('employee_deduction_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $created = 0;

        foreach ($records as $record) {
            if (in_array((int) $record->employee_deduction_id, $manualIds, true)) {
                continue;
            }

            if ($this->alreadyDeductedOnCalendar($record, $calendar, (int) $detail->payroll_batch_detail_id)) {
                continue;
            }

            $payment = $this->paymentAmount($record, (int) $detail->payroll_batch_detail_id);

            if ($payment <= 0) {
                continue;
            }

            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $record->deduction_type_id,
                'employee_deduction_id' => $record->employee_deduction_id,
                'employee_amount' => $payment,
                'employer_amount' => 0,
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);
            $created++;
        }

        return $created;
    }

    public function clearAutoLines(PayrollBatchDetail $detail): void
    {
        $detail->deductions()
            ->whereNotNull('employee_deduction_id')
            ->where(function ($query) {
                $query->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->withTrashed()
            ->each(fn (PayrollDeduction $line) => $line->forceDelete());
    }

    public function paymentAmount(EmployeeDeduction $record, int $exceptDetailId): float
    {
        $amortization = round((float) ($record->amortization_amount ?? 0), 2);

        if ($amortization <= 0) {
            return 0.0;
        }

        $alreadyTaken = round((float) $record->payrollDeductions()
            ->where(function ($query) use ($exceptDetailId) {
                $query->where('payroll_batch_detail_id', '!=', $exceptDetailId)
                    ->orWhere('is_manual', true);
            })
            ->sum('employee_amount'), 2);

        $remaining = round($record->outstandingBeforePayroll() - $alreadyTaken, 2);

        if ($remaining <= 0) {
            return 0.0;
        }

        return round(min($amortization, $remaining), 2);
    }

    private function alreadyDeductedOnCalendar(EmployeeDeduction $record, PayrollCalendar $calendar, int $exceptDetailId): bool
    {
        return PayrollDeduction::query()
            ->where('employee_deduction_id', $record->employee_deduction_id)
            ->where('payroll_batch_detail_id', '!=', $exceptDetailId)
            ->where('employee_amount', '>', 0)
            ->whereHas('payrollBatchDetail.payrollBatch', function ($query) use ($calendar) {
                $query->where('payroll_calendar_id', $calendar->payroll_calendar_id);
            })
            ->exists();
    }
}
