<?php

use App\Models\TimekeepingPolicyBreak;
use App\Models\TimekeepingPolicyNd;
use App\Models\TimekeepingPolicyOvertime;
use App\Models\TimekeepingPolicyTardiness;
use App\Models\TimekeepingPolicyUndertime;

return [
    'module_tabs' => [
        'policy' => [
            'label' => 'Policy',
            'description' => 'Setup tardiness/undertime, overtime and other policies.',
        ],
        'shift-codes' => [
            'label' => 'Shift Code',
            'description' => 'Setup employee shift codes.',
        ],
        'time-capturing-settings' => [
            'label' => 'Time Capturing Settings',
            'description' => 'Setup time capture formats for device uploads.',
        ],
        'holiday-settings' => [
            'label' => 'Holiday Settings',
            'description' => 'Manage list of holiday per year.',
        ],
    ],

    'settings_tabs' => [
        'tardiness-undertime' => 'Tardiness and Undertime',
        'overtime' => 'Overtime',
        'breaks' => 'Breaks',
        'night-differential' => 'Night Differential',
        'general' => 'General',
    ],

    'equivalents' => [
        'tardiness' => [
            'name' => 'Tardiness Equivalent (Late)',
            'tab' => 'tardiness-undertime',
            'model' => TimekeepingPolicyTardiness::class,
            'primary_key' => 'timekeeping_policy_tardiness_id',
            'log_table' => 'tbl_timekeeping_policy_tardiness',
            'order' => ['time_from' => 'asc'],
            'overlap_check' => true,
            'requires_leave_type' => false,
            'supports_marks_absent' => true,
        ],
        'undertime' => [
            'name' => 'Undertime Equivalent',
            'tab' => 'tardiness-undertime',
            'model' => TimekeepingPolicyUndertime::class,
            'primary_key' => 'timekeeping_policy_undertime_id',
            'log_table' => 'tbl_timekeeping_policy_undertime',
            'order' => ['time_from' => 'asc'],
            'overlap_check' => false,
            'requires_leave_type' => false,
        ],
        'overtime' => [
            'name' => 'Overtime Equivalent',
            'tab' => 'overtime',
            'model' => TimekeepingPolicyOvertime::class,
            'primary_key' => 'timekeeping_policy_overtime_id',
            'log_table' => 'tbl_timekeeping_policy_overtime',
            'order' => ['time_from' => 'asc'],
            'overlap_check' => true,
            'requires_leave_type' => false,
        ],
        'breaks' => [
            'name' => 'Break Tardiness Equivalent',
            'tab' => 'breaks',
            'model' => TimekeepingPolicyBreak::class,
            'primary_key' => 'timekeeping_policy_break_id',
            'log_table' => 'tbl_timekeeping_policy_breaks',
            'order' => ['time_from' => 'asc'],
            'overlap_check' => false,
            'requires_leave_type' => false,
        ],
        'nd' => [
            'name' => 'Night Differential Equivalent',
            'tab' => 'night-differential',
            'model' => TimekeepingPolicyNd::class,
            'primary_key' => 'timekeeping_policy_nd_id',
            'log_table' => 'tbl_timekeeping_policy_nd',
            'order' => ['time_from' => 'asc'],
            'overlap_check' => true,
            'requires_leave_type' => false,
        ],
    ],

    'list_columns' => [
        ['key' => 'policy_code', 'label' => 'Code'],
        ['key' => 'policy_name', 'label' => 'Name'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
    ],

    'non_regular_hours_bases' => [
        1 => 'Work Date',
        2 => 'Hours Rendered',
    ],

    'logs_tagging_fields' => [
        'raw_logs_tag' => 'Raw Logs Tag',
        'raw_logs_desc' => 'Raw Logs Description',
        'edited_logs_tag' => 'Edited Logs Tag',
        'edited_logs_desc' => 'Edited Logs Description',
        'filed_logs_tag' => 'Filed Logs Tag',
        'filed_logs_desc' => 'Filed Logs Description',
        'auto_logs_tag' => 'Auto Logs Tag',
        'auto_logs_desc' => 'Auto Logs Description',
        'default_shift_tag' => 'Default Shift Tag',
        'default_shift_desc' => 'Default Shift Description',
        'planned_shift_tag' => 'Planned Shift Tag',
        'planned_shift_desc' => 'Planned Shift Description',
        'filed_shift_tag' => 'Filed Shift Tag',
        'filed_shift_desc' => 'Filed Shift Description',
        'edited_shift_tag' => 'Edited Shift Tag',
        'edited_shift_desc' => 'Edited Shift Description',
    ],
];
