<?php

return [
    'content_max_length' => 1000,

    /**
     * Merge placeholders available per notification type (lu_template.template_id).
     * Matches paths-mvc timekeeping template form.
     */
    'placeholders' => [
        1 => ['APPROVER_NAME', 'CURRENT_STATUS', 'EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'LINK', 'STATUS'],
        2 => ['APPROVER_NAME', 'EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'LINK', 'STATUS'],
        3 => ['APPROVER_NAME', 'EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'LINK', 'STATUS'],
        4 => ['EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'LINK', 'STATUS'],
        5 => ['APPROVER_NAME', 'LINK', 'EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'STATUS'],
        6 => ['APPROVER_NAME', 'EMPLOYEE_ID', 'EMPLOYEE_NAME', 'FORM_TYPE', 'LINK', 'STATUS'],
    ],
];
