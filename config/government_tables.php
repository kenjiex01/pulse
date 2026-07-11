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
            ['key' => 'salary_from', 'label' => 'Salary From', 'type' => 'decimal'],
            ['key' => 'salary_to', 'label' => 'Salary To', 'type' => 'decimal'],
            ['key' => 'contribution_base', 'label' => 'Contribution Base', 'type' => 'decimal'],
            ['key' => 'employee_share', 'label' => 'Employee Share', 'type' => 'decimal'],
            ['key' => 'employer_share', 'label' => 'Employer Share', 'type' => 'decimal'],
            ['key' => 'total_premium', 'label' => 'Total Premium', 'type' => 'computed_sum', 'operands' => ['employee_share', 'employer_share']],
        ],
        'fields' => [
            ['name' => 'salary_from', 'label' => 'Salary From', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'salary_to', 'label' => 'Salary To', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'contribution_base', 'label' => 'Contribution Base', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employee_share', 'label' => 'Employee Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_share', 'label' => 'Employer Share', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
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
            ['key' => 'salary_credit', 'label' => 'Salary Credit', 'type' => 'decimal'],
            ['key' => 'employee_sss', 'label' => 'Employee SSS', 'type' => 'decimal'],
            ['key' => 'employer_sss', 'label' => 'Employer SSS', 'type' => 'decimal'],
            ['key' => 'employer_ec', 'label' => 'Employer EC', 'type' => 'decimal'],
            ['key' => 'total_sss', 'label' => 'Total', 'type' => 'computed_sum', 'operands' => ['employer_sss', 'employee_sss']],
        ],
        'fields' => [
            ['name' => 'compensation_from', 'label' => 'Compensation From', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'compensation_to', 'label' => 'Compensation To', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'salary_credit', 'label' => 'Salary Credit', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employee_sss', 'label' => 'Employee SSS', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_sss', 'label' => 'Employer SSS', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'employer_ec', 'label' => 'Employer EC', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0']],
        ],
    ],
    'withholding-tax-2023' => [
        'name' => 'Withholding Tax',
        'type' => 'wtax2023',
        'log_table' => 'tbl_govt_table_wtax_2023',
    ],
];
