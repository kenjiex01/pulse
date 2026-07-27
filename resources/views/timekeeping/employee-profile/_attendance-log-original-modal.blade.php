@include('partials.modal', [
    'id' => 'attendance-log-original-'.$log->timekeeping_inandout_id,
    'title' => 'Original Attendance Log',
    'description' => 'Values from the upload before this row was edited.',
    'panelClass' => 'max-w-md',
    'body' => view('timekeeping.employee-profile._attendance-log-original-content', [
        'log' => $log,
    ])->render(),
])
