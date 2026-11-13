<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Timelogs DTR — hardcoded campus file layouts
    |--------------------------------------------------------------------------
    |
    | Each campus that supports Timelogs DTR upload has a fixed parser.
    | San Mateo (SA) and Washington (WR) use Card Report / Att.log (.xls).
    | Sumulong (SU) uses DTR Report (.xlsx).
    | Cainta (CA), 108 (O8), and most other campuses use Timesheet Report (.xls).
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
                'Exception Statistic Report',
            ],
            'skip_sheet_names' => [
                'Schedule Infor',
                'Att Stat',
                'Att.log report_orig',
                'Exception Stat',
            ],
        ],
        'SU' => [
            'file_extension' => 'xlsx',
            'parser' => 'sumulong_dtr_report',
        ],
        'CA' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        // Remaining campuses use the same Cainta Timesheet Report layout.
        'AG' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'UA' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'BI' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'CO' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'TA' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'GH' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'ND' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'WR' => [
            'file_extension' => 'xls',
            'parser' => 'san_mateo_card_report',
            'parse_sheet_marker' => 'Card Report',
            'skip_sheet_titles' => [
                'Schedule Information Report',
                'Statistical Report of Attendance',
                'Exception Statistic Report',
            ],
            'skip_sheet_names' => [
                'Schedule Infor',
                'Att Stat',
                'Att.log report_orig',
                'Exception Stat',
            ],
        ],
        'O8' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
        'F6' => [
            'file_extension' => 'xls',
            'parser' => 'cainta_timesheet_report',
        ],
    ],
];
