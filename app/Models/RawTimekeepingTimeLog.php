<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawTimekeepingTimeLog extends Model
{
    protected $table = 'raw_timekeeping_time_logs';

    protected $primaryKey = 'timekeeping_time_log_id';

    public $timestamps = false;

    protected $fillable = [
        'timekeeping_transaction_id',
        'employee_id',
        'time_in',
        'time_out',
        'date_out',
        'time_code_id',
    ];

    protected function casts(): array
    {
        return [
            'date_out' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RawTimekeepingTransaction::class, 'timekeeping_transaction_id', 'timekeeping_transaction_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
