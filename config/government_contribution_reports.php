<?php

return [
    'company_name' => env('GOVERNMENT_REPORT_COMPANY_NAME', env('PAYROLL_REGISTER_COMPANY_NAME', 'ICCT Colleges Foundation, Inc.')),
    'company_address' => env('GOVERNMENT_REPORT_COMPANY_ADDRESS', 'V.V. Soliven Avenue II, Cainta, Rizal'),
    'pagibig_employer_sss' => env('PAGIBIG_REPORT_EMPLOYER_SSS', '03-9096072-4'),

    'philhealth' => [
        'title_line_3' => 'PHILIPPINE HEALTH INSURANCE CORPORATION',
        'title_line_4' => 'CONTRIBUTION PAYMENT RETURN',
        'excel_sheet_title' => 'Philhealth',
        'prepared_by' => env('PHILHEALTH_REPORT_PREPARED_BY', 'JULIE ANG'),
        'noted_by' => env('PHILHEALTH_REPORT_NOTED_BY', 'VERONICA C. ONG'),
        'noted_by_title' => env('PHILHEALTH_REPORT_NOTED_BY_TITLE', 'VP-Chief Finance Officer'),
        'approved_by' => env('PHILHEALTH_REPORT_APPROVED_BY', 'Van William L. Co'),
        'approved_by_title' => env('PHILHEALTH_REPORT_APPROVED_BY_TITLE', 'VP Chief Operating Officer'),
    ],

    'pagibig' => [
        'title_line_3' => 'PAG-IBIG FUND CONTRIBUTION',
        'excel_sheet_title' => 'Pag-ibig',
        'prepared_by' => env('PAGIBIG_REPORT_PREPARED_BY', 'JULIE ANG'),
        'noted_by' => env('PAGIBIG_REPORT_NOTED_BY', 'Veronica L. Co'),
        'noted_by_title' => env('PAGIBIG_REPORT_NOTED_BY_TITLE', 'VP - Chief Finance Officer'),
        'approved_by' => env('PAGIBIG_REPORT_APPROVED_BY', 'Van William L. Co'),
        'approved_by_title' => env('PAGIBIG_REPORT_APPROVED_BY_TITLE', 'VP Chief Operating Officer'),
    ],
];
