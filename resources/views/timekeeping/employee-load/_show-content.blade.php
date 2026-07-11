<div class="space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <p class="text-xs text-gray-500">Enrollment Period</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->enrollment_period_label ?: '—' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <p class="text-xs text-gray-500">Date Range</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->dateRangeLabel() }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <p class="text-xs text-gray-500">Uploaded By</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->uploadedBy?->name ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <p class="text-xs text-gray-500">Records</p>
            <p class="mt-1 font-medium text-gray-900">{{ $transaction->entries->count() }}</p>
        </div>
    </div>

    <div class="max-h-[28rem] overflow-auto rounded-lg border border-gray-200">
        <table class="table-skolaris min-w-[1100px] text-sm">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left">No.</th>
                    <th class="px-3 py-2 text-left">Faculty Name</th>
                    <th class="px-3 py-2 text-left">College</th>
                    <th class="px-3 py-2 text-left">Modality</th>
                    <th class="px-3 py-2 text-left">Subject</th>
                    <th class="px-3 py-2 text-left">Section</th>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Class Schedule</th>
                    <th class="px-3 py-2 text-left">Time In</th>
                    <th class="px-3 py-2 text-left">Time Out</th>
                    <th class="px-3 py-2 text-left">Remarks</th>
                    <th class="px-3 py-2 text-left">Comments</th>
                    <th class="px-3 py-2 text-left">Verification Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transaction->entries as $entry)
                    <tr>
                        <td class="px-3 py-2 text-gray-600">{{ $loop->iteration }}</td>
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $entry->faculty_name ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->college ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->modality ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->subject ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->section ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->load_date ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->class_schedule ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->time_in ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->time_out ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->remarks ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->comments ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->verification_remarks ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="py-12 text-center text-sm text-gray-500">No entries in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
