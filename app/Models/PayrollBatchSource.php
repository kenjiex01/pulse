<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollBatchSource extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'trn_payroll_batch_sources';

    protected $primaryKey = 'payroll_batch_source_id';

    protected $fillable = [
        'payroll_batch_id',
        'source_payroll_batch_id',
    ];

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id', 'payroll_batch_id');
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'source_payroll_batch_id', 'payroll_batch_id');
    }
}
