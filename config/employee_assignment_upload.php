<?php

return [
    'columns' => [
        ['alias' => 'employee_number', 'label' => 'Employee Number', 'required' => true],
        ['alias' => 'employee_name', 'label' => 'Employee Name'],
        ['alias' => 'campus_code', 'label' => 'Main Campus Code', 'required' => true],
        ['alias' => 'campus_name', 'label' => 'Main Campus Name'],
    ],

    'sample_row' => [
        'employee_number' => '2026-00001',
        'employee_name' => 'Dela Cruz, Juan',
        'campus_code' => 'CA',
        'campus_name' => 'ICCT Colleges Cainta Main Campus',
    ],
];
