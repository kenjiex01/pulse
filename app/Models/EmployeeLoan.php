<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class EmployeeLoan extends Model
{
    use SoftDeletes;

    public const CUTOFF_FIRST = 1;

    public const CUTOFF_SECOND = 2;

    protected $table = 'tbl_employee_loans';

    protected $primaryKey = 'employee_loan_id';

    protected $fillable = [
        'employee_id',
        'loan_type_id',
        'payment_scheme_id',
        'dt_loan',
        'number_of_payments',
        'principal_loan_amount',
        'loan_amount',
        'amortization_amount',
        'loan_interest',
        'paid_from_previous',
        'deducted_from_new_loan',
        'loan_purpose',
        'dt_start_payment',
        'deduct_half_per_cutoff',
        'deduction_cutoff',
    ];

    protected function casts(): array
    {
        return [
            'dt_loan' => 'datetime',
            'dt_start_payment' => 'datetime',
            'number_of_payments' => 'integer',
            'principal_loan_amount' => 'decimal:2',
            'loan_amount' => 'decimal:2',
            'amortization_amount' => 'decimal:2',
            'loan_interest' => 'decimal:2',
            'paid_from_previous' => 'decimal:2',
            'deducted_from_new_loan' => 'decimal:2',
            'deduct_half_per_cutoff' => 'boolean',
            'deduction_cutoff' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id', 'loan_type_id');
    }

    public function paymentScheme(): BelongsTo
    {
        return $this->belongsTo(PaymentScheme::class, 'payment_scheme_id', 'payment_scheme_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollLoanPayment::class, 'employee_loan_id', 'employee_loan_id')
            ->orderBy('payroll_loan_payment_id');
    }

    /**
     * Balance before payroll batch payments (amount + interest − opening credits).
     */
    public function outstandingBeforePayroll(): float
    {
        return round(
            (float) $this->loan_amount
            + (float) ($this->loan_interest ?? 0)
            - (float) ($this->paid_from_previous ?? 0)
            - (float) ($this->deducted_from_new_loan ?? 0),
            2
        );
    }

    public function payrollPaymentsTotal(): float
    {
        if ($this->relationLoaded('payments')) {
            return round($this->payments->sum(fn (PayrollLoanPayment $payment) => $payment->amount()), 2);
        }

        return round((float) $this->payments()->sum(DB::raw('COALESCE(payment, 0) + COALESCE(penalty, 0)')), 2);
    }

    public function loanBalance(): float
    {
        return round($this->outstandingBeforePayroll() - $this->payrollPaymentsTotal(), 2);
    }

    /**
     * Balance shown on the loan form and the loans list.
     * Principal minus opening credits minus amounts already deducted in payroll.
     */
    public static function computeDisplayedBalance(
        float $principal,
        float $paidFromPrevious,
        float $deductedFromNewLoan,
        float $payrollPayments = 0,
    ): float {
        return round($principal - $paidFromPrevious - $deductedFromNewLoan - $payrollPayments, 2);
    }

    public static function computePrincipal(float $loanAmount, ?float $loanInterest): float
    {
        return round($loanAmount + (float) ($loanInterest ?? 0), 2);
    }

    /**
     * Principal shown on the loan form.
     * With a payment count: amortization × number of payments.
     * Otherwise: loan amount + interest.
     */
    public static function computePrincipalAmount(
        float $loanAmount,
        ?float $loanInterest,
        ?float $amortizationAmount,
        ?int $numberOfPayments,
    ): float {
        if ($numberOfPayments !== null && $numberOfPayments > 0 && $amortizationAmount !== null) {
            return round($amortizationAmount * $numberOfPayments, 2);
        }

        return self::computePrincipal($loanAmount, $loanInterest);
    }

    public static function computeLoanInterest(float $principalLoanAmount, float $loanAmount): float
    {
        return round($principalLoanAmount - $loanAmount, 2);
    }

    public static function computeAmortization(int $paymentSchemeId, float $loanAmount, ?int $numberOfPayments, ?float $amortizationAmount): ?float
    {
        if ($amortizationAmount !== null) {
            return round($amortizationAmount, 2);
        }

        if ($paymentSchemeId === PaymentScheme::BASED_ON_NUMBER_OF_PAYMENTS && $numberOfPayments && $numberOfPayments > 0) {
            return round($loanAmount / $numberOfPayments, 2);
        }

        return null;
    }
}
