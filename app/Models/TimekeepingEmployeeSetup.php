<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimekeepingEmployeeSetup extends Model
{
    protected $table = 'tbl_timekeeping_employee_setup';

    protected $primaryKey = 'timekeeping_employee_setup_id';

    protected $fillable = [
        'employee_id',
        'timekeeping_holiday_group_id',
        'shift_code_id',
        'timekeeping_policy_id',
        'is_leave',
        'is_populate',
        'is_auto_compute_excess_as_ot',
        'timekeeping_policy_team_setting_id',
    ];

    protected function casts(): array
    {
        return [
            'is_leave' => 'boolean',
            'is_populate' => 'boolean',
            'is_auto_compute_excess_as_ot' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function holidayGroup(): BelongsTo
    {
        return $this->belongsTo(TimekeepingHolidayGroup::class, 'timekeeping_holiday_group_id', 'timekeeping_holiday_group_id');
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(TimekeepingPolicy::class, 'timekeeping_policy_id', 'timekeeping_policy_id');
    }

    public function teamSetting(): BelongsTo
    {
        return $this->belongsTo(TimekeepingPolicyTeamSetting::class, 'timekeeping_policy_team_setting_id', 'timekeeping_policy_team_setting_id');
    }
}
