<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBatchDetail extends Model
{
    protected $table = 'trn_payroll_batch_details';

    protected $primaryKey = 'payroll_batch_detail_id';

    protected $fillable = [
        'payroll_batch_id',
        'employee_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PayrollBatchDetail $detail) {
            $detail->incomes()->withTrashed()->each(fn (PayrollIncome $income) => $income->forceDelete());
            $detail->deductions()->withTrashed()->each(fn (PayrollDeduction $deduction) => $deduction->forceDelete());
        });
    }

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id', 'payroll_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(PayrollIncome::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }
}
