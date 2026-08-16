@php
    use App\Support\TimeLogs;
    use App\Support\TimekeepingEmployeeProfile;

    $fmt = function (?float $value): string {
        return $value === null ? '—' : number_format($value, 2, '.', '');
    };

    $selectedRaw = is_string($selectedDate ?? null) ? $selectedDate : null;
    $selectedMatch = $selectedRaw
        ? collect($attendance['days'])->firstWhere('date', $selectedRaw)
        : null;
    $selectedDate = is_array($selectedMatch) ? ($selectedMatch['date'] ?? null) : null;
    $dateFrom = (string) ($attendance['date_from'] ?? '');
    $dateTo = (string) ($attendance['date_to'] ?? '');
@endphp

<div class="space-y-4" data-employee-profile-lazy-content>
    @if (! $employee->hasTimekeepingSetup())
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Complete <strong>Timekeeping Settings</strong> first before viewing attendance for this employee.
        </p>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-sm text-gray-600">
                Daily attendance summary. OT, late, undertime, and night differential appear when the day is covered by a processed payroll batch.
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Use the pencil on a day to add, edit, or delete raw time logs for that date.
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            @can('time-logs.viewAny')
                <a href="{{ route(TimeLogs::routeName('index')) }}" class="mb-1.5 text-sm text-[#0B318F] hover:underline">Open Time Logs</a>
            @endcan
            <a
                href="{{ route(TimekeepingEmployeeProfile::routeName('attendance-pdf'), [
                    'employee' => $employee->employee_id,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]) }}"
                class="btn-secondary !px-3 !py-1.5 text-sm"
                target="_blank"
                rel="noopener"
            >PDF</a>
            <div>
                <label for="attendance-date-from-{{ $employee->employee_id }}" class="form-label !mb-1 text-xs">Date From</label>
                <input
                    id="attendance-date-from-{{ $employee->employee_id }}"
                    type="date"
                    class="form-input !py-1.5 text-sm"
                    value="{{ $dateFrom }}"
                    data-attendance-date-from
                >
            </div>
            <div>
                <label for="attendance-date-to-{{ $employee->employee_id }}" class="form-label !mb-1 text-xs">Date To</label>
                <input
                    id="attendance-date-to-{{ $employee->employee_id }}"
                    type="date"
                    class="form-input !py-1.5 text-sm"
                    value="{{ $dateTo }}"
                    data-attendance-date-to
                >
            </div>
            <button
                type="button"
                class="btn-secondary !px-3 !py-1.5 text-sm"
                data-attendance-range-apply
                data-attendance-base-url="{{ route(TimekeepingEmployeeProfile::routeName('attendance'), ['employee' => $employee->employee_id]) }}"
            >Apply</button>
        </div>
    </div>

    <div data-client-paginate data-page-size="10">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-max text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-[#0B318F] px-2 py-2 text-left"></th>
                        <th class="px-2 py-2 text-left whitespace-nowrap">Date</th>
                        <th class="px-2 py-2 text-left whitespace-nowrap">Day Type</th>
                        <th class="px-2 py-2 text-left whitespace-nowrap">Shift</th>
                        <th class="px-2 py-2 text-left whitespace-nowrap">Time In</th>
                        <th class="px-2 py-2 text-left whitespace-nowrap">Time Out</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Basic</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Excess Hours</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Ot</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Ndiff</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Ndot</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Late</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Undertime</th>
                        <th class="px-2 py-2 text-right whitespace-nowrap">Break Late</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse ($attendance['days'] as $day)
                        <tr
                            data-paginate-row
                            @class([
                                'bg-slate-50/80' => $day['is_rest_day'],
                                'bg-emerald-50/40' => $day['in_payroll_batch'] && ! $day['is_rest_day'],
                            ])
                        >
                        <td class="sticky left-0 z-[1] bg-inherit px-2 py-1.5">
                            @can('employee-profile.update')
                                <button
                                    type="button"
                                    class="btn-icon"
                                    title="Manage logs for {{ $day['date_label'] }}"
                                    data-modal-stack
                                    data-modal-open="attendance-day-{{ $day['date'] }}"
                                >
                                    <svg class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                    </svg>
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="btn-icon"
                                    title="View logs for {{ $day['date_label'] }}"
                                    data-modal-stack
                                    data-modal-open="attendance-day-{{ $day['date'] }}"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            @endcan
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap font-medium text-gray-900">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-4 w-0.5 rounded {{ $day['in_payroll_batch'] ? 'bg-emerald-500' : 'bg-sky-400' }}"></span>
                                {{ $day['date_label'] }}
                            </span>
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $day['day_type'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $day['shift_label'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $day['is_rest_day'] && $day['time_in'] === '—' ? '' : $day['time_in'] }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap">{{ $day['is_rest_day'] && $day['time_out'] === '—' ? '' : $day['time_out'] }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['basic']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['excess_hours']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['ot']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['ndiff']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['ndot']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['late']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['undertime']) }}</td>
                        <td class="px-2 py-1.5 text-right whitespace-nowrap">{{ $fmt($day['break_late']) }}</td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-3 py-8 text-center text-gray-500">No days in the selected date range.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($attendance['days']) > 0)
            @include('partials.client-pagination-controls', ['defaultPageSize' => 10])
        @endif
    </div>

    <p class="text-xs text-gray-500">
        Green date bar = day is covered by a processed payroll batch (OT / late / undertime filled). Blue bar = not yet in a processed batch.
    </p>

    @foreach ($attendance['days'] as $day)
        @include('timekeeping.employee-profile._attendance-day-modal', [
            'employee' => $employee,
            'day' => $day,
            'attendanceYear' => $attendance['year'],
            'attendanceMonth' => $attendance['month'],
            'attendanceDateFrom' => $dateFrom,
            'attendanceDateTo' => $dateTo,
            'autoOpen' => $selectedDate === $day['date'],
        ])
    @endforeach
</div>
