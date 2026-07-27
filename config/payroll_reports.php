<?php

return [
    'detail_columns' => [
        'employee_number' => 'Employee No.',
        'employee_name' => 'Employee Name',
        'department' => 'Department',
        'college' => 'College',
        'program' => 'Program',
        'tax_status' => 'Tax Status',
        'employment_status' => 'Employment Status',
    ],

    'sort_columns' => [
        'employee_number' => 'Employee No.',
        'employee_name' => 'Employee Name',
        'department' => 'Department',
        'college' => 'College',
        'program' => 'Program',
        'tax_status' => 'Tax Status',
        'employment_status' => 'Employment Status',
    ],

    'group_columns' => [
        'department' => 'Department',
        'college' => 'College',
        'program' => 'Program',
        'tax_status' => 'Tax Status',
        'employment_status' => 'Employment Status',
    ],

    'options' => [
        'payreg' => [
            'label' => 'Payroll Register',
            'view' => 'payroll.reports.options.payreg',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'detail_columns' => ['nullable', 'array'],
                'detail_columns.*' => ['string'],
                'group_by' => ['nullable', 'string'],
                'sort_by' => ['nullable', 'string'],
                'output_format' => ['required', 'in:html,excel'],
            ],
        ],
        'sss' => [
            'label' => 'SSS Monthly Contribution',
            'view' => 'payroll.reports.options.sss',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'output_format' => ['required', 'in:html,excel'],
            ],
        ],
    ],

    'generators' => [
        'payreg' => App\Services\Reports\PayrollRegisterReportService::class,
        'sss' => App\Services\Reports\SssContributionReportService::class,
    ],
];
