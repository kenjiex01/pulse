<?php

namespace App\Services;

use App\Models\EmployeeLoan;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollLoanPayment;

class LoanPayrollService
{
    /**
     * Replace auto loan deductions on this batch detail from employee loans
     * scheduled on the pay period.
     */
    public function syncForDetail(PayrollBatchDetail $detail): int
    {
        $this->clearAutoPayments($detail);

        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch.payrollCalendar')->payrollBatch;
        $calendar = $batch?->payrollCalendar;

        if ($calendar === null || $detail->employee_id === null) {
            return 0;
        }

        $calendar->loadMissing('loans');
        $loanTypeIds = $calendar->loans->pluck('loan_type_id')->map(fn ($id) => (int) $id)->all();

        if ($loanTypeIds === []) {
            return 0;
        }

        $loans = EmployeeLoan::query()
            ->where('employee_id', $detail->employee_id)
            ->whereIn('loan_type_id', $loanTypeIds)
            ->whereNotNull('dt_start_payment')
            ->whereDate('dt_start_payment', '<=', $calendar->dt_to)
            ->get();

        $created = 0;

        foreach ($loans as $loan) {
            if ($this->deductedOnAnotherBatch($loan, (int) $detail->payroll_batch_detail_id)) {
                continue;
            }

            $payment = $this->paymentForBalance(
                $loan->outstandingBeforePayroll(),
                0,
                (float) ($loan->amortization_amount ?? 0),
            );

            if ($payment <= 0) {
                continue;
            }

            PayrollLoanPayment::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'employee_loan_id' => $loan->employee_loan_id,
                'payment' => $payment,
                'penalty' => 0,
                'is_editable' => true,
                'is_deletable' => true,
                'is_manual' => false,
            ]);
            $created++;
        }

        return $created;
    }

    public function clearAutoPayments(PayrollBatchDetail $detail): void
    {
        $detail->loanPayments()
            ->where(function ($query) {
                $query->where('is_manual', false)->orWhereNull('is_manual');
            })
            ->withTrashed()
            ->each(fn (PayrollLoanPayment $payment) => $payment->forceDelete());
    }

    /**
     * PATHS PREPARE_LOAN_PAYMENT_TRANSACTIONS amortization cap.
     */
    public function paymentForBalance(float $balance, float $previousPayments, float $amortization): float
    {
        $balance = round($balance, 2);
        $previousPayments = round($previousPayments, 2);
        $amortization = round($amortization, 2);

        if ($previousPayments < $balance) {
            if ($previousPayments + $amortization > $balance) {
                $amortization = $balance - $previousPayments;

                if ($amortization < 1) {
                    $amortization = 0;
                }
            } elseif ((int) ($balance - $amortization) < (int) $amortization) {
                $amortization = $balance > $amortization ? $amortization : $balance;
            }
        } else {
            $amortization = 0;
        }

        return $amortization > 0 ? round($amortization, 2) : 0.0;
    }

    /**
     * One employee loan is deducted on a single batch only.
     * A later pay period still checked on the calendar does not deduct it again.
     */
    private function deductedOnAnotherBatch(EmployeeLoan $loan, int $exceptDetailId): bool
    {
        return PayrollLoanPayment::query()
            ->where('employee_loan_id', $loan->employee_loan_id)
            ->where('payroll_batch_detail_id', '!=', $exceptDetailId)
            ->where(function ($query) {
                $query->where('payment', '>', 0)->orWhere('penalty', '>', 0);
            })
            ->exists();
    }
}
