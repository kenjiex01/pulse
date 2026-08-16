<?php

return [
    'columns' => [
        ['alias' => 'employee_number', 'label' => 'Employee Number', 'required' => true],
        ['alias' => 'employment_slot', 'label' => 'Employment Slot (1=primary, 2=secondary/hybrid)', 'required' => true],
        ['alias' => 'date_effective_from', 'label' => 'Salary Effectivity From (YYYY-MM-DD or M/D/YYYY)', 'required' => true],
        ['alias' => 'date_effective_to', 'label' => 'Salary Effectivity To (YYYY-MM-DD or M/D/YYYY)'],
        ['alias' => 'pay_type', 'label' => 'Pay Type (ID or Daily/Weekly/Semi-Monthly/Monthly)', 'required' => true],
        ['alias' => 'basic_computation', 'label' => 'Basic Computation (ID or Time-In/Time-Out/Leaves)', 'required' => true],
        ['alias' => 'rate_group', 'label' => 'Rate Group (ID or description)', 'required' => true],
        ['alias' => 'nd_rate_group', 'label' => 'Night Diff. Rate Group (ID or description)'],
        ['alias' => 'days_per_period', 'label' => 'Days Per Period'],
        ['alias' => 'hours_per_day', 'label' => 'Hours Per Day'],
        ['alias' => 'use_basic_income_as_hourly_rate', 'label' => 'Use Basic Income as Hourly Rate (yes/no)'],
        ['alias' => 'is_above_minimum_wage_earner', 'label' => 'Is Above Minimum Wage Earner (yes/no)'],
        ['alias' => 'basic_taxable', 'label' => 'Basic Income Taxable'],
        ['alias' => 'basic_non_taxable', 'label' => 'Basic Income Non-Taxable'],
        ['alias' => 'incomes', 'label' => 'Other Incomes (CODE|taxable|non_taxable;...)'],
        ['alias' => 'deductions', 'label' => 'Deductions (CODE|employee|employer;...)'],
    ],

    'sample_row' => [
        'employee_number' => '2026-00099',
        'employment_slot' => '1',
        'date_effective_from' => '2026-01-01',
        'pay_type' => 'Daily',
        'basic_computation' => 'Time-In/Time-Out',
        'rate_group' => '1',
        'hours_per_day' => '8',
        'basic_taxable' => '25000',
    ],
];
