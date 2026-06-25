<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollLoanPayment extends Model
{
    protected $table = 'raw_payroll_loan_payments';

    protected $primaryKey = 'payroll_loan_payment_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'loan_type_id',
        'dt_loan',
        'payment',
        'penalty',
        'reference_number',
        'dt_reference',
    ];

    protected function casts(): array
    {
        return [
            'dt_loan' => 'datetime',
            'payment' => 'decimal:2',
            'penalty' => 'decimal:2',
            'dt_reference' => 'datetime',
        ];
    }

    public function payrollTransaction(): BelongsTo
    {
        return $this->belongsTo(RawPayrollTransaction::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id', 'loan_type_id');
    }
}
