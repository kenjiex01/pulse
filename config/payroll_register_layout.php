<?php

return [
    'company_name' => env('PAYROLL_REGISTER_COMPANY_NAME', 'ICCT COLLEGES FOUNDATION, INC.'),
    'subtitle' => env('PAYROLL_REGISTER_SUBTITLE', 'PAYROLL REGISTER - FACULTY ONLINE CLASSES'),
    'header_row' => 5,
    'subheader_row' => 6,
    'data_start_row' => 8,
    'last_column' => 'AQ',

    'holiday_income_codes' => ['HOLI', 'LHOL', 'HLDY'],
    'adjustment_income_codes' => ['OTHR', '13TH'],
    'loan_deduction_codes' => [
        'BK' => ['SCAL'],
        'BL' => ['SSAL'],
        'BM' => ['PSAL'],
    ],

    /*
    | Excel download: one worksheet per campus. Codes not listed (and missing campus)
    | fall under the default sheet (Cainta).
    */
    'excel_campus_sheets' => [
        'Antipolo' => ['UA'],
        'Binangonan' => ['BI'],
        'Cogeo' => ['CO'],
        'San Mateo' => ['SA'],
        'Sumulong' => ['SU'],
        'Taytay' => ['TA'],
    ],
    'excel_campus_sheet_default' => 'Cainta',
    'excel_campus_sheet_order' => [
        'Antipolo',
        'Binangonan',
        'Cogeo',
        'San Mateo',
        'Sumulong',
        'Taytay',
        'Cainta',
    ],

    'columns' => json_decode(
        file_get_contents(__DIR__.'/payroll_register_layout_columns.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    ),
];
