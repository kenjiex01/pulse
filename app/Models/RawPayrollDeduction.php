<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollDeduction extends Model
{
    protected $table = 'raw_payroll_deductions';

    protected $primaryKey = 'payroll_deduction_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'deduction_type_id',
        'employee_amount',
        'employer_amount',
        'amount',
        'is_adjustment',
        'reference_number',
        'dt_reference',
    ];

    protected function casts(): array
    {
        return [
            'employee_amount' => 'decimal:2',
            'employer_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_adjustment' => 'boolean',
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

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
