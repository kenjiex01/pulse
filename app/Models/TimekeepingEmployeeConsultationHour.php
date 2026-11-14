<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingEmployeeConsultationHour extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timekeeping_employee_consultation_hours';

    protected $primaryKey = 'timekeeping_employee_consultation_hour_id';

    protected $fillable = [
        'employee_id',
        'day_id',
        'time_start',
        'time_end',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'day_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(LuDay::class, 'day_id', 'day_id');
    }
}
