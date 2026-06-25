<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollBatch extends Model
{
    use SoftDeletes;

    protected $table = 'trn_payroll_batches';

    protected $primaryKey = 'payroll_batch_id';

    protected $fillable = [
        'payroll_calendar_id',
        'batch_no',
        'created_by_id',
        'dt_created',
        'locked_for_id',
        'dt_locked',
        'processed_by_id',
        'dt_processed',
        'payroll_batch_status_id',
        'progress_current',
        'progress_total',
        'withholding_tax_computation_id',
    ];

    protected function casts(): array
    {
        return [
            'batch_no' => 'integer',
            'dt_created' => 'datetime',
            'dt_locked' => 'datetime',
            'dt_processed' => 'datetime',
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'withholding_tax_computation_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PayrollBatch $batch) {
            if ($batch->isForceDeleting()) {
                return;
            }

            $batch->details()->delete();
        });
    }

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchStatus::class, 'payroll_batch_status_id', 'payroll_batch_status_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    public function withholdingTaxComputation(): BelongsTo
    {
        return $this->belongsTo(WithholdingTaxComputation::class, 'withholding_tax_computation_id', 'withholding_tax_computation_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollBatchDetail::class, 'payroll_batch_id', 'payroll_batch_id');
    }

    public function formattedBatchNo(): string
    {
        return str_pad((string) $this->batch_no, 4, '0', STR_PAD_LEFT);
    }
}
