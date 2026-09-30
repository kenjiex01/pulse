<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingEmployeeWeeklyShift extends Model
{
    protected $table = 'tbl_timekeeping_employee_weekly_shifts';

    protected $primaryKey = 'timekeeping_employee_weekly_shift_id';

    protected $fillable = [
        'employee_id',
        'day_id',
        'shift_code_id',
    ];

    protected function casts(): array
    {
        return [
            'day_id' => 'integer',
            'shift_code_id' => 'integer',
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

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }
}
