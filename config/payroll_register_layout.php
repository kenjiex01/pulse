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
    | Excel download: one worksheet per campus using the employee Main assignment.
    | Missing campus falls under the default sheet. Unmapped codes still get their
    | own tab from the campus name.
    */
    'excel_campus_sheets' => [
        'Angono' => ['AG'],
        'Antipolo' => ['UA'],
        'Binangonan' => ['BI'],
        'Cainta' => ['CA'],
        'Cogeo' => ['CO'],
        'Digital' => ['DC'],
        'San Mateo' => ['SA'],
        'Sumulong' => ['SU'],
        'Taytay' => ['TA'],
        'Greenhills' => ['GH'],
        'N. Domingo' => ['ND'],
        'Washington Residences' => ['WR'],
        'Bldg 108' => ['O8'],
        '225 6th Floor' => ['F6'],
    ],
    'excel_campus_sheet_default' => 'Cainta',
    'excel_campus_sheet_order' => [
        'Angono',
        'Antipolo',
        'Binangonan',
        'Cogeo',
        'San Mateo',
        'Sumulong',
        'Taytay',
        'Digital',
        'Greenhills',
        'N. Domingo',
        'Washington Residences',
        'Bldg 108',
        '225 6th Floor',
        'Cainta',
    ],

    'columns' => json_decode(
        file_get_contents(__DIR__.'/payroll_register_layout_columns.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    ),
];
