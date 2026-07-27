@php
    use App\Support\TimekeepingEmployeeProfile;

    $logId = $log->timekeeping_inandout_id;
    $formContext = 'edit-attendance-log-'.$logId;
    $isOpen = old('form_context') === $formContext && $errors->any();
    $dateValue = old('log_date', $log->dt_datetime?->format('Y-m-d'));
    $timeValue = old('log_time', $log->dt_datetime?->format('H:i'));
    $isInValue = old('is_in', $log->is_in ? '1' : '0');
@endphp

@include('partials.modal', [
    'id' => 'attendance-log-edit-'.$logId,
    'title' => 'Edit Attendance Log',
    'description' => 'Update date, time, or punch type for this row.',
    'open' => $isOpen,
    'panelClass' => 'max-w-lg',
    'body' => view('timekeeping.employee-profile._attendance-log-edit-form', [
        'employee' => $employee,
        'log' => $log,
        'formContext' => $formContext,
        'dateValue' => $dateValue,
        'timeValue' => $timeValue,
        'isInValue' => $isInValue,
        'attendancePage' => $attendancePage,
    ])->render(),
])
