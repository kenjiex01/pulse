<?php

return [
    'company_name' => env('GOVERNMENT_REPORT_COMPANY_NAME', env('PAYROLL_REGISTER_COMPANY_NAME', 'ICCT Colleges Foundation, Inc.')),
    'company_address' => env('GOVERNMENT_REPORT_COMPANY_ADDRESS', 'V.V. Soliven Avenue II, Cainta, Rizal'),
    'company_tin' => env('BIR_COMPANY_TIN', ''),
    'company_rdo_code' => env('BIR_COMPANY_RDO_CODE', ''),
    'company_zip' => env('BIR_COMPANY_ZIP', '1900'),
    'signatory_name' => env('BIR_REPORT_SIGNATORY_NAME', 'VERONICA C. ONG'),
    'signatory_title' => env('BIR_REPORT_SIGNATORY_TITLE', 'VP-Chief Finance Officer'),

    // Regular employment compensation ATC (BIR withholding tax table).
    'compensation_atc' => env('BIR_COMPENSATION_ATC', 'WI010'),

    // Default statutory minimum wage display on Form 2316 items 9–10.
    'smw_rate_per_day' => (float) env('BIR_SMW_RATE_PER_DAY', 600),
    'smw_rate_per_month' => (float) env('BIR_SMW_RATE_PER_MONTH', 15650),
];
