<?php

$sharedListColumns = [
    ['key' => 'batch_no', 'label' => 'Batch No.', 'type' => 'number'],
    ['key' => 'pay_type', 'label' => 'Pay Type', 'type' => 'text'],
    ['key' => 'pay_period', 'label' => 'Pay Period', 'type' => 'text'],
    ['key' => 'pay_year', 'label' => 'Pay Year', 'type' => 'number'],
    ['key' => 'records_count', 'label' => 'No. of Records', 'type' => 'number'],
    ['key' => 'uploaded_by_name', 'label' => 'Uploaded By', 'type' => 'text'],
    ['key' => 'dt_uploaded', 'label' => 'Date Uploaded', 'type' => 'datetime'],
];

$employeeFields = [
    ['alias' => 'emp_num', 'label' => 'Employee No.', 'type' => 'employee_number'],
];

return [
    'upload_types' => [
        'incomes' => [
            'label' => 'Incomes',
            'description' => 'Income transactions contain income types, taxable, and non-taxable amounts.',
            'transaction_type_id' => 1,
            'is_adjustment' => false,
            'detail_relation' => 'incomeRecords',
            'search' => ['batch_no', 'filename'],
            'list_columns' => $sharedListColumns,
            'fields' => array_merge($employeeFields, [
                ['alias' => 'income_type', 'label' => 'Income Type Code', 'type' => 'income_type', 'required' => true],
                ['alias' => 'taxable', 'label' => 'Taxable', 'type' => 'decimal'],
                ['alias' => 'non_taxable', 'label' => 'Non-Taxable', 'type' => 'decimal'],
                ['alias' => 'amount', 'label' => 'Amount', 'type' => 'decimal'],
            ]),
        ],
        'deductions' => [
            'label' => 'Deductions',
            'description' => 'Deduction transactions contain deduction types, employee deductions, and employer deductions.',
            'transaction_type_id' => 3,
            'is_adjustment' => false,
            'detail_relation' => 'deductionRecords',
            'search' => ['batch_no', 'filename'],
            'list_columns' => $sharedListColumns,
            'fields' => array_merge($employeeFields, [
                ['alias' => 'deduction_type', 'label' => 'Deduction Type Code', 'type' => 'deduction_type', 'required' => true],
                ['alias' => 'hours', 'label' => 'Hours', 'type' => 'decimal', 'size' => 4, 'hint' => 'Required for LTDE (Late) and UTDE (Undertime). Leave blank for other deduction types.'],
                ['alias' => 'emp_amount', 'label' => 'Employee Amount', 'type' => 'decimal'],
                ['alias' => 'empr_amount', 'label' => 'Employer Share', 'type' => 'decimal'],
                ['alias' => 'amount', 'label' => 'Amount', 'type' => 'decimal'],
                ['alias' => 'reference_number', 'label' => 'Reference Number', 'type' => 'string', 'max' => 45],
                ['alias' => 'reference_date', 'label' => 'Reference Date', 'type' => 'date'],
            ]),
        ],
    ],
];
