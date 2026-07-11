<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Employee Load upload template
    |--------------------------------------------------------------------------
    |
    | Column definitions for the downloadable / uploadable Employee Load CSV.
    | Order matters — it defines both the template header order and the parse
    | order on upload. `prefill` columns are populated from Skolaris data when
    | the template is generated; `editable` columns are filled by the user.
    |
    | `hidden` columns are appended after the visible columns and carry the
    | metadata needed to match rows back to their Skolaris source on upload.
    |
    */

    'columns' => [
        ['alias' => 'row_no', 'label' => 'No.', 'prefill' => true, 'editable' => false],
        ['alias' => 'faculty_name', 'label' => 'Faculty Name', 'prefill' => true, 'editable' => false],
        ['alias' => 'college', 'label' => 'College', 'prefill' => true, 'editable' => false],
        ['alias' => 'modality', 'label' => 'Modality', 'prefill' => true, 'editable' => false],
        ['alias' => 'subject', 'label' => 'Subject', 'prefill' => true, 'editable' => false],
        ['alias' => 'section', 'label' => 'Section', 'prefill' => true, 'editable' => false],
        ['alias' => 'load_date', 'label' => 'Date', 'prefill' => true, 'editable' => false],
        ['alias' => 'class_schedule', 'label' => 'Class Schedule', 'prefill' => true, 'editable' => false],
        ['alias' => 'time_in', 'label' => 'Time In', 'prefill' => false, 'editable' => true],
        ['alias' => 'time_out', 'label' => 'Time Out', 'prefill' => false, 'editable' => true],
        ['alias' => 'remarks', 'label' => 'Remarks', 'prefill' => false, 'editable' => true, 'max' => 255],
        ['alias' => 'comments', 'label' => 'Comments', 'prefill' => false, 'editable' => true, 'max' => 255],
        ['alias' => 'verification_remarks', 'label' => 'Verification Remarks', 'prefill' => false, 'editable' => true, 'max' => 255],
    ],

    // Hidden metadata columns used to re-match rows on upload.
    'hidden_columns' => [
        ['alias' => 'employee_number', 'label' => 'Employee No.'],
        ['alias' => 'skolaris_offering_id', 'label' => 'Offering ID'],
        ['alias' => 'session_date_iso', 'label' => 'Session Date (ISO)'],
    ],

    // Short modality labels for the template (falls back to raw code).
    'modality_labels' => [
        'LF' => 'LIMITED F2F',
        'TOC' => 'ONSITE',
        'OL' => 'ONLINE',
        'AL' => 'MODULAR',
        'OD' => 'ODEL',
        'TC' => 'ONSITE',
    ],

    'list_columns' => [
        ['key' => 'batch_no', 'label' => 'Batch No.', 'type' => 'number'],
        ['key' => 'enrollment_period_label', 'label' => 'Enrollment Period', 'type' => 'text'],
        ['key' => 'date_range', 'label' => 'Date Range', 'type' => 'text'],
        ['key' => 'records_count', 'label' => 'No. of Records', 'type' => 'number'],
        ['key' => 'uploaded_by_name', 'label' => 'Uploaded By', 'type' => 'text'],
        ['key' => 'dt_uploaded', 'label' => 'Date Uploaded', 'type' => 'datetime'],
        ['key' => 'filename', 'label' => 'File Name', 'type' => 'text'],
    ],
];
