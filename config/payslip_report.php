<?php

return [
    'company_name' => env('GOVERNMENT_REPORT_COMPANY_NAME', env('PAYROLL_REGISTER_COMPANY_NAME', 'ICCT COLLEGES FOUNDATION, INC.')),

    'faculty_income_labels' => [
        'BASC' => 'Basic Pay',
        'OVRT' => 'Job Order/Office Duty',
        'NDIF' => 'Night Differential',
        'HOLI' => 'Holiday',
        'LHOL' => 'Holiday',
        'HLDY' => 'Holiday',
        '13TH' => '13th Month Pay',
        'OTHR' => 'Other Income',
    ],

    'staff_income_labels' => [
        'BASC' => 'Basic Pay',
        'OVRT' => 'Overtime',
        'NDIF' => 'Night Differential',
        'HOLI' => 'Holiday',
        'LHOL' => 'Holiday',
        'HLDY' => 'Holiday',
        '13TH' => '13th Month Pay',
        'OTHR' => 'Other Income',
    ],

    'faculty_deduction_labels' => [
        'WHTX' => 'Withholding Tax',
        'SSSP' => 'SSS',
        'SSMP' => 'SSS Prov Fund',
        'PHIL' => 'PhilHealth',
        'PHIM' => 'PhilHealth',
        'PIBG' => 'Pag-ibig',
        'LTDE' => 'Tardiness',
        'UTDE' => 'Tardiness',
        'OTHR' => 'Other Deductions',
    ],

    'staff_deduction_labels' => [
        'WHTX' => 'Tax Withheld',
        'SSSP' => 'SSS',
        'SSMP' => 'SSS Prov Fund',
        'PHIL' => 'PhilHealth',
        'PHIM' => 'PhilHealth',
        'PIBG' => 'Pag-ibig',
        'LTDE' => 'Late/Undertime',
        'UTDE' => 'Late/Undertime',
        'OTHR' => 'Other Deductions',
    ],
];
