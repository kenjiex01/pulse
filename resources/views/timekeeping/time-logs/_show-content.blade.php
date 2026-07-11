<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Batch No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->batch_no }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">File Name</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->filename ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Format</p>
            <p class="mt-1 font-medium text-gray-900">
                {{ $transaction->timeCaptureFormat?->device_name ?? ($transaction->campus ? 'Timelogs DTR' : '—') }}
            </p>
        </div>
        @if ($transaction->campus)
            <div>
                <p class="text-xs text-gray-500">Campus</p>
                <p class="mt-1 font-medium text-gray-900">{{ $transaction->campus->campus_name }}</p>
            </div>
        @endif
        <div>
            <p class="text-xs text-gray-500">Uploaded By</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->uploadedBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Date Uploaded</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->dt_uploaded?->format('M j, Y g:i A') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Period</p>
            <p class="mt-1 font-medium text-gray-900">
                {{ $transaction->dt_from?->format('M j, Y') ?? '—' }}
                –
                {{ $transaction->dt_to?->format('M j, Y') ?? '—' }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500">In/Out Records</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->inAndOutRecords->count() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Time Log Records</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->timeLogRecords->count() }}</p>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Time In / Time Out</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        <th class="px-3 py-2 text-left">Date / Time</th>
                        <th class="px-3 py-2 text-left">In/Out</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transaction->inAndOutRecords as $record)
                        <tr>
                            <td class="px-3 py-2 text-gray-900">
                                {{ $record->employee?->employee_number ?? $record->employee_id }}
                                @if ($record->employee)
                                    <span class="text-gray-500">— {{ $record->employee->full_name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->dt_datetime?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $record->is_in ? 'In' : 'Out' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-8 text-center text-gray-500">No in/out records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($transaction->timeLogRecords->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-900">Time Logs</h3>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-3 py-2 text-left">Time In</th>
                            <th class="px-3 py-2 text-left">Time Out</th>
                            <th class="px-3 py-2 text-left">Date Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaction->timeLogRecords as $record)
                            <tr>
                                <td class="px-3 py-2 text-gray-900">{{ $record->employee?->employee_number ?? $record->employee_id }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $record->time_in ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $record->time_out ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $record->date_out?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
