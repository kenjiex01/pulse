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
                'employee_type' => ['required', 'string', 'in:staff,admin'],
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'detail_columns' => ['nullable', 'array'],
                'detail_columns.*' => ['string'],
                'group_by' => ['nullable', 'string'],
                'sort_by' => ['nullable', 'string'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'sss' => [
            'label' => 'SSS Monthly Contribution',
            'view' => 'payroll.reports.options.sss',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'phil' => [
            'label' => 'PhilHealth Contribution',
            'view' => 'payroll.reports.options.phil',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'pagibig' => [
            'label' => 'Pag-IBIG Contribution',
            'view' => 'payroll.reports.options.pagibig',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'bir-tax' => [
            'label' => "BIR Employees' Tax Withheld",
            'view' => 'payroll.reports.options.bir-tax',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'payslip' => [
            'label' => 'Payslip',
            'view' => 'payroll.reports.options.payslip',
            'rules' => [
                'payroll_batch_id' => ['required', 'integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'employee_ids' => ['required', 'array', 'min:1'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'bir-1601c' => [
            'label' => 'BIR Form 1601-C',
            'view' => 'payroll.reports.options.bir-1601c',
            'rules' => [
                'payroll_batch_ids' => ['required', 'array', 'min:1'],
                'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
                'employee_ids' => ['required', 'array', 'min:1'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'include_annual_13th_month' => ['sometimes', 'boolean'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'bir-2316' => [
            'label' => 'BIR Form 2316',
            'view' => 'payroll.reports.options.bir-2316',
            'rules' => [
                'pay_year' => ['required', 'integer', 'min:1000', 'max:9999'],
                'employee_ids' => ['required', 'array', 'min:1'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'alphalist' => [
            'label' => 'Alphalist',
            'view' => 'payroll.reports.options.alphalist',
            'rules' => [
                'pay_year' => ['required', 'integer', 'min:1000', 'max:9999'],
                'schedules' => ['required', 'array', 'min:1'],
                'schedules.*' => ['string', 'in:7.1,7.3,7.4,7.5'],
                'day_factor' => ['nullable', 'numeric', 'min:1', 'max:366'],
                'output_format' => ['required', 'in:excel'],
            ],
        ],
        'historical-data' => [
            'label' => 'Historical Data',
            'view' => 'payroll.reports.options.historical-data',
            'rules' => [
                'employee_ids' => ['nullable', 'array'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'actions' => ['nullable', 'array'],
                'actions.*' => ['string', 'in:create,update,delete'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'employee-credentials' => [
            'label' => 'Employee',
            'view' => 'payroll.reports.options.employee-credentials',
            'rules' => [
                'employee_ids' => ['nullable', 'array'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
        'attendance-view' => [
            'label' => 'Attendance View',
            'view' => 'payroll.reports.options.attendance-view',
            'rules' => [
                'date_from' => ['required', 'date'],
                'date_to' => ['required', 'date', 'after_or_equal:date_from'],
                'employee_ids' => ['required', 'array', 'min:1'],
                'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
                'output_format' => ['required', 'in:html,excel,pdf'],
            ],
        ],
    ],

    'generators' => [
        'payreg' => App\Services\Reports\PayrollRegisterReportService::class,
        'sss' => App\Services\Reports\SssContributionReportService::class,
        'phil' => App\Services\Reports\PhilhealthContributionReportService::class,
        'pagibig' => App\Services\Reports\PagibigContributionReportService::class,
        'bir-tax' => App\Services\Reports\BirTaxWithheldReportService::class,
        'payslip' => App\Services\Reports\PayslipReportService::class,
        'bir-1601c' => App\Services\Reports\Bir1601cReportService::class,
        'bir-2316' => App\Services\Reports\Bir2316ReportService::class,
        'alphalist' => App\Services\Reports\AlphalistReportService::class,
        'historical-data' => App\Services\Reports\HistoricalDataReportService::class,
        'employee-credentials' => App\Services\Reports\EmployeeCredentialsReportService::class,
        'attendance-view' => App\Services\Reports\AttendanceViewReportService::class,
    ],
];
