<?php

return [
    'tabs' => [
        'time-in-out' => [
            'label' => 'Time In / Time Out',
            'transaction_type_id' => 1,
            'log_table' => 'raw_timekeeping_transactions',
            'name' => 'Time In / Time Out transaction',
            'search' => ['batch_no', 'filename'],
            'columns' => [
                ['key' => 'batch_no', 'label' => 'Batch No.', 'type' => 'number'],
                ['key' => 'records_count', 'label' => 'No. of Records', 'type' => 'number'],
                ['key' => 'uploaded_by_name', 'label' => 'Uploaded By', 'type' => 'text'],
                ['key' => 'dt_uploaded', 'label' => 'Date Uploaded', 'type' => 'datetime'],
                ['key' => 'filename', 'label' => 'File Name', 'type' => 'text'],
            ],
        ],
        'timelogs-dtr' => [
            'label' => 'Timelogs DTR',
            'transaction_type_id' => 2,
            'requires_campus' => true,
            'log_table' => 'raw_timekeeping_transactions',
            'name' => 'Timelogs DTR transaction',
            'search' => ['batch_no', 'filename'],
            'columns' => [
                ['key' => 'batch_no', 'label' => 'Batch No.', 'type' => 'number'],
                ['key' => 'records_count', 'label' => 'No. of Records', 'type' => 'number'],
                ['key' => 'uploaded_by_name', 'label' => 'Uploaded By', 'type' => 'text'],
                ['key' => 'dt_uploaded', 'label' => 'Date Uploaded', 'type' => 'datetime'],
                ['key' => 'filename', 'label' => 'File Name', 'type' => 'text'],
            ],
        ],
        'teaching-loads' => [
            'label' => 'Teaching Loads',
            'type' => 'skolaris_pull',
            'log_table' => 'teaching_load_pull_batches',
            'name' => 'Teaching Loads pull history',
            'search' => ['batch_no'],
            'columns' => [
                ['key' => 'batch_no', 'label' => 'Pull Batch', 'type' => 'text'],
                ['key' => 'pulled_at', 'label' => 'Date Pulled', 'type' => 'datetime'],
                ['key' => 'pulled_by_name', 'label' => 'Pulled By', 'type' => 'text'],
                ['key' => 'employee_count', 'label' => 'Employees Pulled', 'type' => 'number'],
                ['key' => 'records_count', 'label' => 'Load Rows', 'type' => 'number'],
                ['key' => 'date_range', 'label' => 'Date Range', 'type' => 'text'],
            ],
        ],
    ],
];
