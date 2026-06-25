<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryDeduction extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_salary_deductions';

    protected $primaryKey = 'employee_salary_deduction_id';

    protected $fillable = [
        'employee_salary_id',
        'deduction_type_id',
        'employee_amount',
        'employer_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'employee_amount' => 'decimal:2',
            'employer_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
