<?php

return [
    /*
    | When local teaching loads are empty, memo may read Skolaris daily-loads (same cache
    | as Employee Load template) once per date range per request.
    */
    'faculty_use_skolaris_schedule' => (bool) env('TIMEKEEPING_MEMO_FACULTY_SKOLARIS', true),
];
