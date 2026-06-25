<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawPayrollIncome extends Model
{
    protected $table = 'raw_payroll_incomes';

    protected $primaryKey = 'payroll_income_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_id',
        'employee_id',
        'income_type_id',
        'taxable',
        'non_taxable',
        'amount',
        'is_adjustment',
    ];

    protected function casts(): array
    {
        return [
            'taxable' => 'decimal:2',
            'non_taxable' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_adjustment' => 'boolean',
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

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id', 'income_type_id');
    }
}
