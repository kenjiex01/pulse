@php
    use App\Support\TimekeepingEmployeeProfile;

    $dateKey = $day['date'];
    $modalId = 'attendance-day-'.$dateKey;
    $addContext = 'create-attendance-log-'.$dateKey;
    $isAddOpen = old('form_context') === $addContext && $errors->any();
    $open = ($autoOpen ?? false) || $isAddOpen;
@endphp

@include('partials.modal', [
    'id' => $modalId,
    'title' => 'Attendance Logs — '.$day['label'],
    'description' => 'Add, edit, or delete raw time in / time out punches for this day.',
    'open' => $open,
    'panelClass' => 'max-w-3xl',
    'body' => view('timekeeping.employee-profile._attendance-day-content', [
        'employee' => $employee,
        'day' => $day,
        'attendanceYear' => $attendanceYear,
        'attendanceMonth' => $attendanceMonth,
        'attendanceDateFrom' => $attendanceDateFrom ?? null,
        'attendanceDateTo' => $attendanceDateTo ?? null,
        'addContext' => $addContext,
        'isAddOpen' => $isAddOpen,
    ])->render(),
])
