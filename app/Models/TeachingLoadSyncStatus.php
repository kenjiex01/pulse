<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingLoadSyncStatus extends Model
{
    use SoftDeletes;

    protected $table = 'teaching_load_sync_status';

    protected $primaryKey = 'teaching_load_sync_status_id';

    protected $fillable = [
        'employee_id',
        'last_pulled_at',
        'last_date_from',
        'last_date_to',
        'last_records_count',
        'last_pulled_by_id',
    ];

    protected function casts(): array
    {
        return [
            'last_pulled_at' => 'datetime',
            'last_date_from' => 'date',
            'last_date_to' => 'date',
            'last_records_count' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function lastPulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_pulled_by_id');
    }
}
