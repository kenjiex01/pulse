<?php

return [
    'default_sub_tab' => 'holidays',

    'sub_tabs' => [
        'holidays' => [
            'label' => 'Holidays',
            'description' => 'Maintain master holiday definitions.',
            'log_table' => 'tbl_timekeeping_holidays',
            'create_label' => 'New Holiday',
        ],
        'groups' => [
            'label' => 'Groups',
            'description' => 'Group holidays for employee assignment.',
            'log_table' => 'tbl_timekeeping_holiday_groups',
            'create_label' => 'New Group',
        ],
        'year' => [
            'label' => 'Year',
            'description' => 'Manage calendar years and holiday schedules.',
            'log_table' => 'tbl_timekeeping_years',
            'create_label' => 'New Year',
        ],
    ],
];
