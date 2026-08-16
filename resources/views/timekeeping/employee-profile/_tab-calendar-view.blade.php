@php
    use App\Support\TimekeepingEmployeeProfile;

    $weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $selectedDate = is_string($selectedDate ?? null) && isset($calendar['days'][$selectedDate])
        ? $selectedDate
        : null;
@endphp

<div
    class="space-y-4"
    data-employee-profile-lazy-content
    data-attendance-calendar
>
    @if (! $employee->hasTimekeepingSetup())
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Complete <strong>Timekeeping Settings</strong> first before viewing attendance for this employee.
        </p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            First Time In and last Time Out per day. Click a day to see every raw log.
        </p>
        <div class="flex items-center gap-2">
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('calendar'), [
                    'employee' => $employee->employee_id,
                    'year' => $calendar['prev_year'],
                    'month' => $calendar['prev_month'],
                ]) }}"
                class="btn-secondary !px-2.5 !py-1.5 text-sm"
                data-live-table-page
                title="Previous month"
                aria-label="Previous month"
            >
                ←
            </a>
            <span class="min-w-[9rem] text-center text-sm font-semibold text-gray-900">{{ $calendar['label'] }}</span>
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('calendar'), [
                    'employee' => $employee->employee_id,
                    'year' => $calendar['next_year'],
                    'month' => $calendar['next_month'],
                ]) }}"
                class="btn-secondary !px-2.5 !py-1.5 text-sm"
                data-live-table-page
                title="Next month"
                aria-label="Next month"
            >
                →
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <div class="attendance-calendar-grid attendance-calendar-head">
            @foreach ($weekdayLabels as $label)
                <div class="attendance-calendar-weekday">{{ $label }}</div>
            @endforeach
        </div>

        @foreach ($calendar['weeks'] as $week)
            <div class="attendance-calendar-grid attendance-calendar-week">
                @foreach ($week as $cell)
                    @php
                        $clickable = $cell['has_logs'];
                    @endphp
                    <button
                        type="button"
                        class="attendance-calendar-day
                            {{ $cell['in_month'] ? '' : 'is-outside' }}
                            {{ $cell['is_today'] ? 'is-today' : '' }}
                            {{ $cell['has_logs'] ? 'has-logs' : '' }}
                            {{ $selectedDate === $cell['date'] ? 'is-selected' : '' }}"
                        @if ($clickable)
                            data-calendar-day="{{ $cell['date'] }}"
                            data-calendar-day-label="{{ $calendar['days'][$cell['date']]['label'] ?? $cell['date'] }}"
                            data-calendar-first-in="{{ $cell['first_in'] ?? '—' }}"
                            data-calendar-last-out="{{ $cell['last_out'] ?? '—' }}"
                        @else
                            disabled
                        @endif
                        aria-label="{{ $cell['date'] }}{{ $clickable ? ', '.$cell['log_count'].' logs' : '' }}"
                    >
                        <span class="attendance-calendar-day-num">{{ $cell['day'] }}</span>
                        @if ($cell['has_logs'])
                            <span class="attendance-calendar-punch is-in">
                                In {{ $cell['first_in'] ?? '—' }}
                            </span>
                            <span class="attendance-calendar-punch is-out">
                                Out {{ $cell['last_out'] ?? '—' }}
                            </span>
                        @else
                            <span class="attendance-calendar-empty">—</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endforeach
    </div>

    <div
        class="rounded-lg border border-gray-200 bg-white"
        data-calendar-day-detail
    >
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3" @if (! $selectedDate) hidden @endif data-calendar-day-detail-header>
            <div>
                <h4 class="text-sm font-semibold text-gray-900" data-calendar-day-detail-title>
                    @if ($selectedDate)
                        {{ $calendar['days'][$selectedDate]['label'] }}
                    @endif
                </h4>
                <p class="mt-0.5 text-xs text-gray-500" data-calendar-day-detail-summary>
                    @if ($selectedDate)
                        First In: {{ $calendar['days'][$selectedDate]['first_in'] ?? '—' }}
                        · Last Out: {{ $calendar['days'][$selectedDate]['last_out'] ?? '—' }}
                    @endif
                </p>
            </div>
            <button type="button" class="btn-secondary !px-2.5 !py-1 text-xs" data-calendar-day-detail-close>
                Close
            </button>
        </div>

        @if ($calendar['days'] === [])
            <p class="px-4 py-6 text-center text-sm text-gray-500">
                No attendance logs in this month.
            </p>
        @else
            @foreach ($calendar['days'] as $dateKey => $day)
                <div
                    class="overflow-hidden"
                    data-calendar-day-panel="{{ $dateKey }}"
                    @if ($selectedDate !== $dateKey) hidden @endif
                >
                    <div class="overflow-x-auto">
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
                                @foreach ($day['logs'] as $log)
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
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <p class="px-4 py-6 text-center text-sm text-gray-500" data-calendar-day-placeholder @if ($selectedDate) hidden @endif>
                Select a day with punches to list all logs.
            </p>
        @endif
    </div>

    @foreach ($calendar['days'] as $day)
        @foreach ($day['logs'] as $log)
            @if ($log->is_edited)
                @include('timekeeping.employee-profile._attendance-log-original-modal', ['log' => $log])
            @endif
            @can('employee-profile.update')
                @include('timekeeping.employee-profile._attendance-log-edit-modal', [
                    'employee' => $employee,
                    'log' => $log,
                    'attendancePage' => null,
                    'viewTab' => 'calendar',
                    'calendarYear' => $calendar['year'],
                    'calendarMonth' => $calendar['month'],
                    'calendarDay' => $day['date'],
                ])
            @endcan
        @endforeach
    @endforeach
</div>
