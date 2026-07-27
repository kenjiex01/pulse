<?php

use App\Models\GovtTablePagibig;
use App\Models\GovtTablePhilhealth;
use App\Models\GovtTablePhilhealthMinimum;
use App\Models\GovtTableSss;

return [
    'pag-ibig' => [
        'name' => 'Pag-IBIG',
        'model' => GovtTablePagibig::class,
        'primary_key' => 'govt_table_pagibig_id',
        'log_table' => 'tbl_govt_table_pagibig',
        'order' => ['salary_cap' => 'asc'],
        'search' => ['salary_cap'],
        'columns' => [
            ['key' => 'salary_cap', 'label' => 'Salary Not Over', 'type' => 'decimal'],
            ['key' => 'employee_contribution', 'label' => 'Employee Amount', 'type' => 'decimal'],
            ['key' => 'employer_contribution', 'label' => 'Employer Amount', 'type' => 'decimal'],
        ],
        'fields' => [
            ['name' => 'salary_cap', 'label' => 'Salary Not Over', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employee_contribution', 'label' => 'Employee Amount', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_contribution', 'label' => 'Employer Amount', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
        ],
    ],
    'philhealth' => [
        'name' => 'PhilHealth',
        'model' => GovtTablePhilhealth::class,
        'primary_key' => 'govt_table_philhealth_id',
        'log_table' => 'tbl_govt_table_philhealth',
        'order' => ['salary_from' => 'asc'],
        'search' => ['salary_from', 'salary_to'],
        'columns' => [
            ['key' => 'govt_table_philhealth_id', 'label' => 'MSB'],
            ['key' => 'salary_from', 'label' => 'Salary From', 'type' => 'decimal'],
            ['key' => 'salary_to', 'label' => 'Salary To', 'type' => 'decimal'],
            ['key' => 'is_percent', 'label' => 'Is Percent', 'type' => 'yes_no'],
            ['key' => 'percentage', 'label' => 'Percentage', 'type' => 'decimal'],
            ['key' => 'employee_share', 'label' => 'Employee Share', 'type' => 'decimal'],
            ['key' => 'employer_share', 'label' => 'Employer Share', 'type' => 'decimal'],
            ['key' => 'total_premium', 'label' => 'Total Premium', 'type' => 'computed_sum', 'operands' => ['employee_share', 'employer_share']],
            ['key' => 'is_active', 'label' => 'Is Active?', 'type' => 'yes_no'],
        ],
        'fields' => [
            ['name' => 'salary_from', 'label' => 'Salary From', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'salary_to', 'label' => 'Salary To', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'is_percent', 'label' => 'Is Percent', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
            ['name' => 'percentage', 'label' => 'Percentage', 'type' => 'number', 'rules' => ['nullable', 'numeric', 'min:0', 'max:100']],
            ['name' => 'employee_share', 'label' => 'Employee Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_share', 'label' => 'Employer Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'is_active', 'label' => 'Is Active?', 'type' => 'checkbox', 'rules' => ['nullable', 'boolean']],
        ],
    ],
    'philhealth-minimum' => [
        'name' => 'Philhealth Minimum',
        'model' => GovtTablePhilhealthMinimum::class,
        'primary_key' => 'govt_table_philhealth_minimum_id',
        'log_table' => 'tbl_govt_table_philhealth_minimum',
        'allow_create' => false,
        'allow_delete' => false,
        'order' => ['govt_table_philhealth_minimum_id' => 'asc'],
        'search' => ['employee_amount', 'employer_amount'],
        'columns' => [
            ['key' => 'employee_amount', 'label' => 'Employee Amount', 'type' => 'decimal'],
            ['key' => 'employer_amount', 'label' => 'Employer Amount', 'type' => 'decimal'],
        ],
        'fields' => [
            ['name' => 'employee_amount', 'label' => 'Employee Amount', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_amount', 'label' => 'Employer Amount', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
        ],
    ],
    'sss' => [
        'name' => 'SSS',
        'model' => GovtTableSss::class,
        'primary_key' => 'govt_table_sss_id',
        'log_table' => 'tbl_govt_table_sss',
        'order' => ['compensation_from' => 'asc'],
        'search' => ['compensation_from', 'compensation_to'],
        'columns' => [
            ['key' => 'compensation_from', 'label' => 'Compensation From', 'type' => 'decimal'],
            ['key' => 'compensation_to', 'label' => 'Compensation To', 'type' => 'decimal'],
            ['key' => 'salary_credit', 'label' => 'Regular SS', 'type' => 'decimal'],
            ['key' => 'mpf_salary_credit', 'label' => 'MPF Salary Credit', 'type' => 'decimal'],
            ['key' => 'total_salary_credit', 'label' => 'Total Salary Credit', 'type' => 'computed_sum', 'operands' => ['salary_credit', 'mpf_salary_credit']],
            ['key' => 'employer_sss', 'label' => 'Employer Regular SS', 'type' => 'decimal'],
            ['key' => 'employer_mpf_share', 'label' => 'Employer MPF Share', 'type' => 'decimal'],
            ['key' => 'employer_ec', 'label' => 'Employer EC', 'type' => 'decimal'],
            ['key' => 'employee_sss', 'label' => 'Employee Regular SS', 'type' => 'decimal'],
            ['key' => 'employee_mpf_share', 'label' => 'Employee MPF Share', 'type' => 'decimal'],
            ['key' => 'total_sss', 'label' => 'Total Contribution', 'type' => 'computed_sum', 'operands' => ['employer_sss', 'employer_mpf_share', 'employer_ec', 'employee_sss', 'employee_mpf_share']],
        ],
        'fields' => [
            ['name' => 'compensation_from', 'label' => 'Compensation From', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'compensation_to', 'label' => 'Compensation To', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'salary_credit', 'label' => 'Regular SS (MSC)', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'mpf_salary_credit', 'label' => 'MPF Salary Credit', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_sss', 'label' => 'Employer Regular SS', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_mpf_share', 'label' => 'Employer MPF Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_ec', 'label' => 'Employer EC', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employee_sss', 'label' => 'Employee Regular SS', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employee_mpf_share', 'label' => 'Employee MPF Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
        ],
    ],
    'withholding-tax-2023' => [
        'name' => 'Withholding Tax',
        'type' => 'wtax2023',
        'log_table' => 'tbl_govt_table_wtax_2023',
    ],
];
