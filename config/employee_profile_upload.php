<?php

$employeeFields = [
    ['alias' => 'emp_num', 'label' => 'Employee No.', 'type' => 'employee_number', 'required' => true],
    [
        'alias' => 'full_name',
        'label' => 'Full Name',
        'type' => 'reference',
        'hint' => 'For reference only; not imported. Prefilled when you download the template.',
    ],
];

$restDayFields = [];

foreach ([
    1 => 'sun',
    2 => 'mon',
    3 => 'tue',
    4 => 'wed',
    5 => 'thu',
    6 => 'fri',
    7 => 'sat',
] as $dayId => $short) {
    $label = ucfirst($short);

    $restDayFields[] = [
        'alias' => "rest_{$short}",
        'label' => "{$label} Rest",
        'type' => 'boolean',
        'day_id' => $dayId,
        'hint' => '1 = rest day, 0 or blank = working day',
    ];
    $restDayFields[] = [
        'alias' => "rest_{$short}_paid",
        'label' => "{$label} Paid",
        'type' => 'boolean',
        'day_id' => $dayId,
        'hint' => '1 = paid rest day when rest day is 1, else 0',
    ];
}

return [
    'fields' => array_merge($employeeFields, [
        [
            'alias' => 'holiday_group_code',
            'label' => 'Holiday Group Code',
            'type' => 'holiday_group',
            'required' => true,
        ],
        [
            'alias' => 'policy_name',
            'label' => 'Policy Name',
            'type' => 'policy',
            'required' => true,
        ],
        [
            'alias' => 'shift_code',
            'label' => 'Shift Code',
            'type' => 'shift_code',
            'required' => true,
        ],
    ], $restDayFields, [
        [
            'alias' => 'is_leave',
            'label' => 'Enable Leave Cancellation',
            'type' => 'boolean',
            'hint' => '1 = enable cancellation of leaves, 0 or blank = disabled',
        ],
        [
            'alias' => 'is_populate',
            'label' => 'Auto Populate Attendance',
            'type' => 'boolean',
            'hint' => '1 = auto populate attendance, 0 or blank = disabled',
        ],
        [
            'alias' => 'is_auto_compute_excess_as_ot',
            'label' => 'Auto Compute Excess as OT',
            'type' => 'boolean',
            'hint' => '1 = compute excess regular hours as OT, 0 or blank = disabled',
        ],
    ]),
];
