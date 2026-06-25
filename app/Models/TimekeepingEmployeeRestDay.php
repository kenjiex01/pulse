<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingEmployeeRestDay extends Model
{
    protected $table = 'tbl_timekeeping_employee_rest_days';

    protected $primaryKey = 'timekeeping_employee_rest_day_id';

    protected $fillable = [
        'employee_id',
        'day_id',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
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
