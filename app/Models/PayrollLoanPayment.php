<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollLoanPayment extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_loan_payments';

    protected $primaryKey = 'payroll_loan_payment_id';

    protected $fillable = [
        'payroll_batch_detail_id',
        'employee_loan_id',
        'payment',
        'penalty',
        'is_editable',
        'is_deletable',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'payment' => 'decimal:2',
            'penalty' => 'decimal:2',
            'is_editable' => 'boolean',
            'is_deletable' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id', 'employee_loan_id');
    }

    public function amount(): float
    {
        return round((float) $this->payment + (float) ($this->penalty ?? 0), 2);
    }
}
