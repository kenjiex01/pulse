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

    /*
    | Pre-filled template uses Skolaris daily-loads (Loading Attendance schedules)
    | with fallback to faculty overview + batch offering details when daily-loads
    | is empty. Large ranges can take longer than the default 30s PHP / HTTP limits.
    */
    'build_time_limit_seconds' => (int) env('EMPLOYEE_LOAD_BUILD_TIME_LIMIT', 600),
    'pull_step_time_limit_seconds' => (int) env('EMPLOYEE_LOAD_PULL_STEP_TIME_LIMIT', 900),
    'pull_employees_per_step' => (int) env('EMPLOYEE_LOAD_PULL_EMPLOYEES_PER_STEP', 25),
    // Uploaded PDF + day-by-day Attendance Checker scans (minutes per employee). Off by default — use Employee Attendance API.
    'pull_slow_fallbacks' => filter_var(env('EMPLOYEE_LOAD_PULL_SLOW_FALLBACKS', false), FILTER_VALIDATE_BOOL),
    'pull_uploaded_faculty_loading' => filter_var(env('EMPLOYEE_LOAD_PULL_UPLOADED_FACULTY', true), FILTER_VALIDATE_BOOL),
    'skolaris_api_timeout_seconds' => (int) env('EMPLOYEE_LOAD_SKOLARIS_TIMEOUT', 180),
    'overview_cache_minutes' => (int) env('EMPLOYEE_LOAD_OVERVIEW_CACHE_MINUTES', 10),
    // Typical payroll cutoffs span ~5–6 weeks; 45 days covers common monthly downloads.
    'max_template_days' => (int) env('EMPLOYEE_LOAD_MAX_TEMPLATE_DAYS', 45),
    /*
    | bulk — one Skolaris daily-loads call for the whole date range (fastest for monthly).
    | chunked_parallel — many smaller calls in parallel (lower peak memory, slower).
    */
    'daily_loads_fetch' => env('EMPLOYEE_LOAD_DAILY_LOADS_FETCH', 'bulk'),
    'daily_loads_chunk_size' => (int) env('EMPLOYEE_LOAD_DAILY_LOADS_CHUNK_SIZE', 100),
    'daily_loads_parallel_requests' => (int) env('EMPLOYEE_LOAD_DAILY_LOADS_PARALLEL', 8),
    'daily_loads_response_cache_minutes' => (int) env('EMPLOYEE_LOAD_DAILY_LOADS_CACHE_MINUTES', 15),
    'restrict_to_local_faculty' => filter_var(env('EMPLOYEE_LOAD_RESTRICT_LOCAL_FACULTY', true), FILTER_VALIDATE_BOOL),
    'template_memory_limit' => (string) env('EMPLOYEE_LOAD_TEMPLATE_MEMORY_LIMIT', '768M'),

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
