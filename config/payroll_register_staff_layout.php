<?php

return [
    'company_name' => env('PAYROLL_REGISTER_COMPANY_NAME', 'ICCT COLLEGES FOUNDATION, INC.'),
    'subtitle_staff' => 'PAYROLL REGISTER - STAFF',
    'subtitle_admin' => 'PAYROLL REGISTER - ADMIN',
    'header_row' => 5,
    'subheader_row' => 6,
    'data_start_row' => 8,
    'last_column' => 'AT',

    'holiday_income_codes' => ['HOLI', 'LHOL', 'HLDY'],
    'special_holiday_income_codes' => ['SPHL', 'SHOL'],
    'holiday_duty_income_codes' => ['HDTY', 'HDUT'],
    'hol_ot_income_codes' => ['HOT', 'HOTR'],
    'overtime_rd_income_codes' => ['OTRD', 'OTSU'],

    'columns' => json_decode(
        file_get_contents(__DIR__.'/payroll_register_staff_layout_columns.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    ),
];
