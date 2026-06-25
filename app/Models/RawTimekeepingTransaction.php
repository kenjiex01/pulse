<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawTimekeepingTransaction extends Model
{
    public const TYPE_TIME_IN_OUT = 1;

    protected $table = 'raw_timekeeping_transactions';

    protected $primaryKey = 'timekeeping_transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'timekeeping_transaction_type_id',
        'dt_from',
        'dt_to',
        'uploaded_by_id',
        'dt_uploaded',
        'batch_no',
        'filename',
        'timecapture_format_id',
    ];

    protected function casts(): array
    {
        return [
            'dt_from' => 'datetime',
            'dt_to' => 'datetime',
            'dt_uploaded' => 'datetime',
            'batch_no' => 'integer',
            'timekeeping_transaction_type_id' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function timeCaptureFormat(): BelongsTo
    {
        return $this->belongsTo(TimeCaptureFormat::class, 'timecapture_format_id', 'timecapture_format_id');
    }

    public function inAndOutRecords(): HasMany
    {
        return $this->hasMany(RawTimekeepingInandout::class, 'timekeeping_transaction_id', 'timekeeping_transaction_id');
    }

    public function timeLogRecords(): HasMany
    {
        return $this->hasMany(RawTimekeepingTimeLog::class, 'timekeeping_transaction_id', 'timekeeping_transaction_id');
    }
}
