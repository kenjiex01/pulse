@php
    use App\Support\TimekeepingEmployeeLoad;

    /** @var \App\Services\EmployeeLoadPayrollService $employeeLoadPayroll */
    $employeeLoadPayroll = app(\App\Services\EmployeeLoadPayrollService::class);

    $formatTime = static function (?string $time): string {
        if ($time === null || $time === '') {
            return '—';
        }

        try {
            return \Carbon\CarbonImmutable::parse($time)->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    };

    $entries = $employeeLoadEntries instanceof \Illuminate\Support\Collection
        ? $employeeLoadEntries
        : collect($employeeLoadEntries);
@endphp

<div class="space-y-4" data-employee-profile-lazy-content>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">Faculty load sessions from Employee Load uploads and Skolaris Teaching Loads pulls. Time In / Time Out come from attendance logs, not from the class schedule.</p>
        @can('employee-load.viewAny')
            <a href="{{ route(TimekeepingEmployeeLoad::routeName('index')) }}" class="text-sm text-[#0B318F] hover:underline">Open Employee Load</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
            <p class="text-xs text-gray-500">Sessions</p>
            <p class="mt-0.5 text-lg font-semibold text-gray-900">{{ number_format($summary['total_sessions']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
            <p class="text-xs text-gray-500">Days Worked</p>
            <p class="mt-0.5 text-lg font-semibold text-gray-900">{{ number_format($summary['worked_days']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 col-span-2 sm:col-span-1">
            <p class="text-xs text-gray-500">With Time In</p>
            <p class="mt-0.5 text-lg font-semibold text-gray-900">{{ number_format($summary['sessions_with_time_in']) }}</p>
        </div>
    </div>

    <div data-client-paginate data-page-size="10">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Date</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Subject</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Section</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Class Schedule</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Time In</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Time Out</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Hours</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Late</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Undertime</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Overtime</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($entries as $entry)
                        @php
                            $metrics = $employeeLoadPayroll->attendanceMetricsForEntry($entry, $timekeepingPolicy ?? null);
                        @endphp
                        <tr data-paginate-row>
                            <td class="px-3 py-2 text-gray-800 whitespace-nowrap">
                                {{ $entry->session_date?->format('M j, Y') ?? ($entry->load_date ?: '—') }}
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $entry->subject ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $entry->section ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $entry->class_schedule ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $formatTime($entry->time_in) }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $formatTime($entry->time_out) }}</td>
                            <td class="px-3 py-2 text-gray-900 font-medium whitespace-nowrap">{{ number_format($metrics['hours'], 2) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap {{ $metrics['late_is_absent'] ? 'font-medium text-red-600' : 'text-gray-600' }}">{{ $metrics['late_display'] }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $employeeLoadPayroll->formatDurationMinutes($metrics['undertime_minutes']) }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $employeeLoadPayroll->formatDurationMinutes($metrics['overtime_minutes']) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $entry->remarks ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-3 py-8 text-center text-gray-500">No employee load entries found for this employee. Pull Teaching Loads from Time Logs or upload Employee Load.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entries->count() > 0)
            @include('partials.client-pagination-controls', ['defaultPageSize' => 10])
        @endif
    </div>

    @if ($entries->count() > 0)
        <p class="text-xs text-gray-500">Hours use Time In → Time Out. Waived late restores the late gap so it is not deducted from hours. Late otherwise uses the employee's Policy Group tardiness equivalents. Days worked counts unique dates with Time In where the session is not marked absent by policy.</p>
    @endif
</div>
