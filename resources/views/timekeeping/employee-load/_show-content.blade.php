@php
    use App\Services\EmployeeLoadPayrollService;
    use App\Support\TimekeepingEmployeeLoad;
    use Carbon\CarbonImmutable;

    /** @var \App\Models\RawEmployeeLoadTransaction $transaction */
    $payroll = app(EmployeeLoadPayrollService::class);

    $toTimeInput = static function (?string $time): string {
        if ($time === null || $time === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($time)->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    };
@endphp

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
        <table class="table-skolaris min-w-[1200px] text-sm">
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
                    <th class="px-3 py-2 text-left">Hours</th>
                    <th class="px-3 py-2 text-left">Late</th>
                    <th class="px-3 py-2 text-left">Remarks</th>
                    <th class="px-3 py-2 text-left">Comments</th>
                    <th class="px-3 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transaction->entries as $entry)
                    @php
                        $metrics = $payroll->attendanceMetricsForEntry($entry);
                    @endphp
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
                        <td class="px-3 py-2 text-gray-900 font-medium">{{ number_format($metrics['hours'], 2) }}</td>
                        <td class="px-3 py-2 text-gray-600">
                            {{ $metrics['late_display'] }}
                            @if ($entry->late_waived && $metrics['late_display'] === 'Waived')
                                <span class="ml-1 text-[10px] uppercase tracking-wide text-emerald-700">✓</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->remarks ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $entry->comments ?: '—' }}</td>
                        <td class="px-3 py-2">
                            @can('employee-load.update')
                                <button
                                    type="button"
                                    class="btn-icon"
                                    title="Edit"
                                    data-modal-open="employee-load-entry-edit-{{ $entry->employee_load_entry_id }}"
                                    data-modal-stack
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                    </svg>
                                </button>
                            @else
                                <span class="text-gray-400">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="py-12 text-center text-sm text-gray-500">No entries in this batch.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('employee-load.update')
        @foreach ($transaction->entries as $entry)
            @include('partials.modal', [
                'id' => 'employee-load-entry-edit-'.$entry->employee_load_entry_id,
                'title' => 'Edit Load Entry',
                'description' => trim(($entry->faculty_name ?: 'Faculty').' · '.($entry->subject ?: 'Subject').' · '.($entry->load_date ?: ($entry->session_date?->format('M j, Y') ?? ''))),
                'open' => isset($editEntry) && (int) $editEntry->employee_load_entry_id === (int) $entry->employee_load_entry_id,
                'panelClass' => 'max-w-lg',
                'body' => view('timekeeping.employee-load._entry-edit-form', [
                    'entry' => $entry,
                    'toTimeInput' => $toTimeInput,
                ])->render(),
            ])
        @endforeach
    @endcan
</div>
