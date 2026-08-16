<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollDeduction extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_deductions';

    protected $primaryKey = 'payroll_deduction_id';

    protected $fillable = [
        'payroll_batch_detail_id',
        'deduction_type_id',
        'hours',
        'days',
        'employee_amount',
        'employer_amount',
        'reference_number',
        'dt_reference',
        'is_editable',
        'is_deletable',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:4',
            'days' => 'decimal:4',
            'employee_amount' => 'decimal:2',
            'employer_amount' => 'decimal:2',
            'dt_reference' => 'datetime',
            'is_editable' => 'boolean',
            'is_deletable' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }
}
