<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollIncome extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_incomes';

    protected $primaryKey = 'payroll_income_id';

    protected $fillable = [
        'payroll_batch_detail_id',
        'income_type_id',
        'hours',
        'taxable',
        'non_taxable',
        'is_editable',
        'is_deletable',
        'orig_taxable',
        'orig_non_taxable',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:4',
            'taxable' => 'decimal:2',
            'non_taxable' => 'decimal:2',
            'orig_taxable' => 'decimal:2',
            'orig_non_taxable' => 'decimal:2',
            'is_manual' => 'boolean',
            'is_editable' => 'boolean',
            'is_deletable' => 'boolean',
        ];
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id', 'income_type_id');
    }
}
