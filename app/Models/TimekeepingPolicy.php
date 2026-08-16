<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TimekeepingPolicy extends Model
{
    protected $table = 'tbl_timekeeping_policies';

    protected $primaryKey = 'timekeeping_policy_id';

    protected $fillable = [
        'policy_code',
        'policy_name',
        'description',
        'is_active',
        'is_allow_flexi_time',
        'max_flexi_time',
        'grace_period',
        'is_deduct_grace_period',
        'tardiness_leave_type_id',
        'undertime_leave_type_id',
        'tardiness_rounding_id',
        'undertime_rounding_id',
        'is_ot_form_required',
        'is_consider_after_time',
        'is_consider_before_time',
        'excess_hour_id',
        'min_minutes',
        'overtime_rounding_id',
        'is_offset_undertime',
        'is_offset_lwop',
        'is_offset_absent_tardiness_with_ot',
        'special_ot_start',
        'special_ot_min_minutes',
        'break_computation',
        'break_deduct_tardiness',
        'break_grace_period',
        'is_break_deduct_grace_period',
        'break_tardiness_leave_type_id',
        'break_tardiness_rounding_id',
        'awol_leave_type_id',
        'nd_deduct_break',
        'night_diff_start',
        'night_diff_end',
        'leave_processing_mode',
        'validity_of_late_file',
        'hide_negative_leaves',
        'enable_attendance_approval',
        'enable_employee_validation_for_rest_days',
        'max_rest_days_per_week',
        'min_hours_rendered_per_week',
        'non_regular_hours_computation_basis',
        'enable_notification',
        'notif_for_process',
        'is_fix_break',
        'buffer_time_in',
        'buffer_time_out',
        'timekeeping_policy_team_setting_id',
        'enable_toil',
        'exp_days',
        'min_toil_hours',
        'max_toil_hours',
        'enable_logs_tagging',
        'raw_logs_tag',
        'edited_logs_tag',
        'filed_logs_tag',
        'auto_logs_tag',
        'raw_logs_desc',
        'edited_logs_desc',
        'filed_logs_desc',
        'auto_logs_desc',
        'default_shift_tag',
        'planned_shift_tag',
        'filed_shift_tag',
        'edited_shift_tag',
        'default_shift_desc',
        'planned_shift_desc',
        'filed_shift_desc',
        'edited_shift_desc',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_allow_flexi_time' => 'boolean',
            'max_flexi_time' => 'decimal:4',
            'grace_period' => 'decimal:4',
            'is_deduct_grace_period' => 'boolean',
            'tardiness_leave_type_id' => 'integer',
            'undertime_leave_type_id' => 'integer',
            'tardiness_rounding_id' => 'integer',
            'undertime_rounding_id' => 'integer',
            'is_ot_form_required' => 'integer',
            'is_consider_after_time' => 'boolean',
            'is_consider_before_time' => 'boolean',
            'excess_hour_id' => 'integer',
            'min_minutes' => 'decimal:4',
            'overtime_rounding_id' => 'integer',
            'is_offset_undertime' => 'boolean',
            'is_offset_lwop' => 'boolean',
            'is_offset_absent_tardiness_with_ot' => 'boolean',
            'special_ot_min_minutes' => 'decimal:4',
            'break_computation' => 'integer',
            'break_deduct_tardiness' => 'boolean',
            'break_grace_period' => 'decimal:4',
            'is_break_deduct_grace_period' => 'boolean',
            'break_tardiness_leave_type_id' => 'integer',
            'break_tardiness_rounding_id' => 'integer',
            'awol_leave_type_id' => 'integer',
            'nd_deduct_break' => 'boolean',
            'leave_processing_mode' => 'integer',
            'validity_of_late_file' => 'integer',
            'hide_negative_leaves' => 'boolean',
            'enable_attendance_approval' => 'boolean',
            'enable_employee_validation_for_rest_days' => 'boolean',
            'max_rest_days_per_week' => 'integer',
            'min_hours_rendered_per_week' => 'decimal:4',
            'non_regular_hours_computation_basis' => 'integer',
            'enable_notification' => 'boolean',
            'is_fix_break' => 'boolean',
            'buffer_time_in' => 'decimal:2',
            'buffer_time_out' => 'decimal:2',
            'timekeeping_policy_team_setting_id' => 'integer',
            'enable_toil' => 'boolean',
            'exp_days' => 'integer',
            'min_toil_hours' => 'decimal:4',
            'max_toil_hours' => 'decimal:4',
            'enable_logs_tagging' => 'boolean',
        ];
    }

    public function teamSetting()
    {
        return $this->belongsTo(TimekeepingPolicyTeamSetting::class, 'timekeeping_policy_team_setting_id', 'timekeeping_policy_team_setting_id');
    }

    public function excessHour()
    {
        return $this->belongsTo(LuExcessHour::class, 'excess_hour_id', 'excess_hour_id');
    }

    public function dayCodes(): HasOne
    {
        return $this->hasOne(TimekeepingPolicyDayCode::class, 'timekeeping_policy_id', 'timekeeping_policy_id');
    }

    public function tardinessEquivalents(): HasMany
    {
        return $this->hasMany(TimekeepingPolicyTardiness::class, 'timekeeping_policy_id', 'timekeeping_policy_id');
    }
}
