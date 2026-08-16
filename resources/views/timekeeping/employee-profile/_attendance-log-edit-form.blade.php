@php
    use App\Support\TimekeepingEmployeeProfile;
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeProfile::routeName('attendance-update'), [$employee->employee_id, $log->timekeeping_inandout_id]) }}"
    class="space-y-4"
>
    @csrf
    @method('PUT')

    <input type="hidden" name="form_context" value="{{ $formContext }}">
    <input type="hidden" name="search" value="{{ request('search') }}">
    <input type="hidden" name="page" value="{{ request('page') }}">
    <input type="hidden" name="attendance_page" value="{{ $attendancePage }}">
    <input type="hidden" name="view_tab" value="{{ $viewTab ?? 'attendance' }}">
    @if (! empty($calendarYear))
        <input type="hidden" name="year" value="{{ $calendarYear }}">
    @endif
    @if (! empty($calendarMonth))
        <input type="hidden" name="month" value="{{ $calendarMonth }}">
    @endif
    @if (! empty($dateFrom))
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
    @endif
    @if (! empty($dateTo))
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
    @endif
    @if (! empty($calendarDay))
        <input type="hidden" name="day" value="{{ $calendarDay }}">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="attendance-log-date-{{ $log->timekeeping_inandout_id }}" class="form-label">Date</label>
            <input
                id="attendance-log-date-{{ $log->timekeeping_inandout_id }}"
                type="date"
                name="log_date"
                class="form-input"
                value="{{ $dateValue }}"
                required
            >
            @error('log_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="attendance-log-time-{{ $log->timekeeping_inandout_id }}" class="form-label">Time</label>
            <input
                id="attendance-log-time-{{ $log->timekeeping_inandout_id }}"
                type="time"
                name="log_time"
                class="form-input"
                value="{{ $timeValue }}"
                required
            >
            @error('log_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="attendance-log-type-{{ $log->timekeeping_inandout_id }}" class="form-label">Type</label>
        <select id="attendance-log-type-{{ $log->timekeeping_inandout_id }}" name="is_in" class="form-input" required>
            <option value="1" @selected((string) $isInValue === '1')>Time In</option>
            <option value="0" @selected((string) $isInValue === '0')>Time Out</option>
        </select>
        @error('is_in')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    @if ($log->is_edited)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            This log was edited before. Saving again keeps the original upload values for reference.
        </p>
    @endif

    @include('partials.modal-form-actions', ['submitLabel' => 'Save Log'])
</form>
