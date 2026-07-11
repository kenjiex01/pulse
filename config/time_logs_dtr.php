<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Timelogs DTR — hardcoded campus file layouts
    |--------------------------------------------------------------------------
    |
    | Each campus that provides a DTR template has a fixed parser definition.
    | Cainta (CA) and San Mateo (SA) use .xls; Sumulong (SU) uses .xlsx.
    |
    */

    'campuses' => [
        'SA' => [
            'file_extension' => 'xls',
            'parser' => 'san_mateo_card_report',
            'parse_sheet_marker' => 'Card Report',
            'skip_sheet_titles' => [
                'Schedule Information Report',
                'Statistical Report of Attendance',
                'Attendance Record Report',
                'Exception Statistic Report',
            ],
            'skip_sheet_names' => [
                'Schedule Infor',
                'Att Stat',
                'Att log report',
                'Exception Stat',
            ],
        ],
        'SU' => [
            'file_extension' => 'xlsx',
            'parser' => 'flat',
            'columns' => ['employee_number', 'actual_date', 'time_in', 'time_out'],
            'labels' => ['Employee Number', 'Actual Date', 'Time In', 'Time Out'],
            'header_rows' => 2,
        ],
        'CA' => [
            'file_extension' => 'xls',
            'parser' => 'flat',
            'columns' => ['employee_number', 'actual_date', 'time_in', 'time_out'],
            'labels' => ['Employee Number', 'Actual Date', 'Time In', 'Time Out'],
            'header_rows' => 2,
        ],
    ],
];
