<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Employee No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $employee?->employee_number ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Employee Name</p>
            <p class="mt-1 font-medium text-gray-900">{{ $employee?->full_name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pull Batch</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch?->formattedBatchNo() ? 'Batch #'.$batch->formattedBatchNo() : '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Range</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch?->dateRangeLabel() ?? '—' }}</p>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Pulled load rows</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Session Date</th>
                        <th class="px-3 py-2 text-left">Subject</th>
                        <th class="px-3 py-2 text-left">Section</th>
                        <th class="px-3 py-2 text-left">Schedule</th>
                        <th class="px-3 py-2 text-left">Room</th>
                        <th class="px-3 py-2 text-left">Campus</th>
                        <th class="px-3 py-2 text-left">Hours</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-3 py-2 text-gray-900">{{ $row->session_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">
                                {{ $row->subject_code ?? '—' }}
                                @if ($row->subject_name)
                                    <span class="text-gray-500">— {{ $row->subject_name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->section ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->class_schedule ?? (($row->time_in ?? '—').' - '.($row->time_out ?? '—')) }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->room ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->campus_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->total_hours ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row->status_code ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">No load rows found for this employee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
