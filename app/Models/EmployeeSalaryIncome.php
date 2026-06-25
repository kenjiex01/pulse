<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryIncome extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_salary_incomes';

    protected $primaryKey = 'employee_salary_income_id';

    protected $fillable = [
        'employee_salary_id',
        'income_type_id',
        'taxable',
        'non_taxable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'taxable' => 'decimal:2',
            'non_taxable' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id', 'income_type_id');
    }
}
