<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeachingLoadSession extends Model
{
    use SoftDeletes;

    protected $table = 'teaching_load_sessions';

    protected $primaryKey = 'teaching_load_session_id';

    protected $fillable = [
        'teaching_load_pull_batch_id',
        'employee_id',
        'session_date',
        'employee_number',
        'skolaris_offering_id',
        'subject_code',
        'subject_name',
        'section',
        'campus_name',
        'room',
        'schedule_day',
        'class_schedule',
        'time_in',
        'time_out',
        'total_hours',
        'total_render_hours',
        'status_code',
        'date_from',
        'date_to',
        'pulled_at',
        'pulled_by_id',
    ];

    protected function casts(): array
    {
        return [
            'teaching_load_pull_batch_id' => 'integer',
            'session_date' => 'date',
            'date_from' => 'date',
            'date_to' => 'date',
            'pulled_at' => 'datetime',
            'total_hours' => 'decimal:2',
            'total_render_hours' => 'decimal:2',
            'skolaris_offering_id' => 'integer',
        ];
    }

    public function pullBatch(): BelongsTo
    {
        return $this->belongsTo(TeachingLoadPullBatch::class, 'teaching_load_pull_batch_id', 'teaching_load_pull_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function pulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pulled_by_id');
    }
}
