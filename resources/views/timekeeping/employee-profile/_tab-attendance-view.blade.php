@php
    use App\Support\TimeLogs;
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
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($attendanceLogs as $log)
                    <tr>
                        <td class="px-3 py-2 text-gray-800 whitespace-nowrap">{{ $log->dt_datetime?->format('M j, Y') ?? '—' }}</td>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-gray-500">No attendance logs found for this employee.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.numbered-pagination', ['paginator' => $attendanceLogs])

    <p class="text-xs text-gray-500">
        Full calendar attendance view from paths-mvc will be added in a later update.
    </p>
</div>
