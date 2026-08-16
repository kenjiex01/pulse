@php
    use App\Support\TimekeepingEmployeeProfile;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm">
        <div>
            <span class="font-medium text-gray-900">{{ $day['day_type'] }}</span>
            <span class="text-gray-400">·</span>
            <span class="text-gray-600">{{ $day['shift_label'] }}</span>
        </div>
        <div class="text-xs text-gray-500">
            Time In: {{ $day['time_in'] }} · Time Out: {{ $day['time_out'] }}
            @if ($day['in_payroll_batch'])
                <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">In payroll batch</span>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Time</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Reference</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Batch</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($day['logs'] as $log)
                    <tr @class(['bg-amber-50/40' => $log->is_edited])>
                        <td class="px-3 py-2 text-gray-800 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span>{{ $log->dt_datetime?->format('g:i A') ?? '—' }}</span>
                                @if ($log->is_edited)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Edited</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            @if ($log->is_in)
                                <span class="badge-success">Time In</span>
                            @else
                                <span class="badge-muted">Time Out</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $log->reference_number ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">#{{ $log->timekeeping_transaction_id }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($log->is_edited)
                                    <button
                                        type="button"
                                        class="btn-icon"
                                        title="View original log"
                                        data-modal-stack
                                        data-modal-open="attendance-log-original-{{ $log->timekeeping_inandout_id }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                @endif
                                @can('employee-profile.update')
                                    <button
                                        type="button"
                                        class="btn-icon"
                                        title="Edit log"
                                        data-modal-stack
                                        data-modal-open="attendance-log-edit-{{ $log->timekeeping_inandout_id }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form
                                        method="POST"
                                        action="{{ route(TimekeepingEmployeeProfile::routeName('attendance-destroy'), [$employee->employee_id, $log->timekeeping_inandout_id]) }}"
                                        onsubmit="return confirm('Delete this attendance log?')"
                                        class="inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="view_tab" value="attendance">
                                        <input type="hidden" name="date_from" value="{{ $attendanceDateFrom ?? '' }}">
                                        <input type="hidden" name="date_to" value="{{ $attendanceDateTo ?? '' }}">
                                        <input type="hidden" name="year" value="{{ $attendanceYear }}">
                                        <input type="hidden" name="month" value="{{ $attendanceMonth }}">
                                        <input type="hidden" name="day" value="{{ $day['date'] }}">
                                        <input type="hidden" name="search" value="{{ request('search') }}">
                                        <input type="hidden" name="page" value="{{ request('page') }}">
                                        <button type="submit" class="btn-icon text-red-600 hover:bg-red-50" title="Delete log">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">No raw logs for this day yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('employee-profile.update')
        <div class="rounded-lg border border-dashed border-gray-300 p-4">
            <h4 class="mb-3 text-sm font-semibold text-gray-900">Add log</h4>
            <form
                method="POST"
                action="{{ route(TimekeepingEmployeeProfile::routeName('attendance-store'), $employee->employee_id) }}"
                class="grid gap-3 sm:grid-cols-4"
            >
                @csrf
                <input type="hidden" name="form_context" value="{{ $addContext }}">
                <input type="hidden" name="view_tab" value="attendance">
                <input type="hidden" name="date_from" value="{{ $attendanceDateFrom ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $attendanceDateTo ?? '' }}">
                <input type="hidden" name="year" value="{{ $attendanceYear }}">
                <input type="hidden" name="month" value="{{ $attendanceMonth }}">
                <input type="hidden" name="day" value="{{ $day['date'] }}">
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="page" value="{{ request('page') }}">

                <div>
                    <label class="form-label" for="add-log-date-{{ $day['date'] }}">Date</label>
                    <input
                        id="add-log-date-{{ $day['date'] }}"
                        type="date"
                        name="log_date"
                        class="form-input"
                        value="{{ old('form_context') === $addContext ? old('log_date', $day['date']) : $day['date'] }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="add-log-time-{{ $day['date'] }}">Time</label>
                    <input
                        id="add-log-time-{{ $day['date'] }}"
                        type="time"
                        name="log_time"
                        class="form-input"
                        value="{{ old('form_context') === $addContext ? old('log_time') : '' }}"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="add-log-type-{{ $day['date'] }}">Type</label>
                    <select id="add-log-type-{{ $day['date'] }}" name="is_in" class="form-input" required>
                        <option value="1" @selected(old('form_context') === $addContext ? old('is_in', '1') === '1' : true)>Time In</option>
                        <option value="0" @selected(old('form_context') === $addContext && old('is_in') === '0')>Time Out</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full sm:w-auto">Add Log</button>
                </div>
            </form>
            @if ($isAddOpen)
                @error('log_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('log_time')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('is_in')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @endif
        </div>
    @endcan

    @foreach ($day['logs'] as $log)
        @if ($log->is_edited)
            @include('timekeeping.employee-profile._attendance-log-original-modal', ['log' => $log])
        @endif
        @can('employee-profile.update')
            @include('timekeeping.employee-profile._attendance-log-edit-modal', [
                'employee' => $employee,
                'log' => $log,
                'attendancePage' => null,
                'viewTab' => 'attendance',
                'calendarYear' => $attendanceYear,
                'calendarMonth' => $attendanceMonth,
                'calendarDay' => $day['date'],
                'dateFrom' => $attendanceDateFrom ?? null,
                'dateTo' => $attendanceDateTo ?? null,
            ])
        @endcan
    @endforeach
</div>
