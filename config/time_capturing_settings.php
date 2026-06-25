<?php

return [
    'date_types' => [
        'actual_date' => 'Date (Actual Date)',
        'date_out' => 'Date Out',
        'workdate' => 'Work Date',
    ],

    'time_in_types' => [
        'time_in' => 'Time In',
        'worktime' => 'Time (same column for In/Out)',
    ],

    'biometric_defaults' => [
        'employee_id_type' => 'biometric_id',
        'employee_id_column' => 1,
        'date_type' => 'actual_date',
        'date_column' => 2,
        'time_in_type' => 'worktime',
        'time_in_column' => '',
        'worktime_column' => 3,
        'same_column_indicator' => true,
        'indicator_column' => 4,
        'time_in_identifier' => '1',
        'time_out_identifier' => '0',
    ],

    'employee_id_types' => [
        'employee_number' => 'Employee Number',
        'biometric_id' => 'Employee Biometric ID',
    ],

    'reserved_custom_field_names' => [
        'employee_number',
        'biometric_id',
        'tmp_employee_number',
        'actual_date',
        'date_out',
        'workdate',
        'reason',
        'time_in',
        'worktime',
        'time_out',
        'indicator',
    ],
];
