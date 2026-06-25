<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBatchStatus extends Model
{
    public const PENDING = 1;

    public const LOCKED = 2;

    public const PROCESSING = 3;

    public const PROCESSED = 4;

    public const AWAITING_APPROVAL = 5;

    public const POSTING = 6;

    public const POSTED = 7;

    public $timestamps = false;

    protected $table = 'lu_payroll_batch_status';

    protected $primaryKey = 'payroll_batch_status_id';

    protected $fillable = [
        'payroll_batch_status',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(PayrollBatch::class, 'payroll_batch_status_id', 'payroll_batch_status_id');
    }
}
