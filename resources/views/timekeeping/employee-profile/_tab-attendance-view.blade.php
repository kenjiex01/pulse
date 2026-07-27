@php
    use App\Support\TimeLogs;
    use App\Support\TimekeepingEmployeeProfile;

    $attendancePage = (int) request('attendance_page', $attendanceLogs->currentPage());
@endphp

<div class="space-y-4" data-employee-profile-lazy-content>
    @if (! $employee->hasTimekeepingSetup())
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Complete <strong>Timekeeping Settings</strong> first before viewing attendance for this employee.
        </p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">Recent raw time in / time out logs uploaded for this employee.</p>
        @can('time-logs.viewAny')
            <a href="{{ route(TimeLogs::routeName('index')) }}" class="text-sm text-[#0B318F] hover:underline">Open Time Logs</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Date</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Time</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Reference</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Batch</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($attendanceLogs as $log)
                    <tr @class(['bg-amber-50/40' => $log->is_edited])>
                        <td class="px-3 py-2 text-gray-800 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span>{{ $log->dt_datetime?->format('M j, Y') ?? '—' }}</span>
                                @if ($log->is_edited)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Edited</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2 text-gray-800 whitespace-nowrap">{{ $log->dt_datetime?->format('g:i A') ?? '—' }}</td>
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
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-500">No attendance logs found for this employee.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.numbered-pagination', ['paginator' => $attendanceLogs])

    @foreach ($attendanceLogs as $log)
        @if ($log->is_edited)
            @include('timekeeping.employee-profile._attendance-log-original-modal', ['log' => $log])
        @endif
        @can('employee-profile.update')
            @include('timekeeping.employee-profile._attendance-log-edit-modal', [
                'employee' => $employee,
                'log' => $log,
                'attendancePage' => $attendancePage,
            ])
        @endcan
    @endforeach

    <p class="text-xs text-gray-500">
        Full calendar attendance view from paths-mvc will be added in a later update.
    </p>
</div>
