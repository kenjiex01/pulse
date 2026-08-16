@php
    $type = $type ?? 'LTDE';
    $rows = $rows ?? [];
    $titles = [
        'LTDE' => 'Late Details',
        'UTDE' => 'Undertime Details',
        'OVRT' => 'Overtime Details',
    ];
    $descriptions = [
        'LTDE' => 'Work dates with late minutes in this pay period (clock-in and break tardiness when applicable).',
        'UTDE' => 'Work dates with undertime minutes in this pay period.',
        'OVRT' => 'Work dates with approved overtime in this pay period.',
    ];
    $modalId = $modalId ?? 'payroll-batch-attendance-breakdown-'.$type;
    $showOtWindow = $type === 'OVRT';
@endphp

<div
    id="{{ $modalId }}"
    class="modal-overlay modal-overlay-nested hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel max-w-4xl">
        <div class="modal-header">
            <div>
                <h2 id="{{ $modalId }}-title" class="text-lg font-bold text-[#0B318F]">{{ $titles[$type] ?? 'Attendance Details' }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $descriptions[$type] ?? '' }}</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Work Date</th>
                            <th class="px-3 py-2 text-left">Shift Code</th>
                            <th class="px-3 py-2 text-left">Schedule</th>
                            <th class="px-3 py-2 text-left">Time In</th>
                            <th class="px-3 py-2 text-left">Time Out</th>
                            @if ($showOtWindow)
                                <th class="px-3 py-2 text-left">OT Window</th>
                            @endif
                            <th class="px-3 py-2 text-right">Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    {{ \Illuminate\Support\Carbon::parse($row['work_date'])->format('M j, Y') }}
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['shift_code'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if (($row['schedule_start'] ?? null) && ($row['schedule_end'] ?? null))
                                        {{ $row['schedule_start'] }} – {{ $row['schedule_end'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['time_in'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['time_out'] ?? '—' }}</td>
                                @if ($showOtWindow)
                                    <td class="px-3 py-2 text-gray-600">
                                        @if (($row['ot_start'] ?? null) && ($row['ot_end'] ?? null))
                                            {{ $row['ot_start'] }} – {{ $row['ot_end'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-2 text-right text-gray-900">{{ number_format((int) ($row['minutes'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showOtWindow ? 7 : 6 }}" class="px-3 py-8 text-center text-gray-500">
                                    No day details found for this pay period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($rows) > 0)
                        <tfoot>
                            <tr class="bg-gray-50 font-semibold">
                                <td class="px-3 py-2 text-gray-900" colspan="{{ $showOtWindow ? 6 : 5 }}">Total</td>
                                <td class="px-3 py-2 text-right text-gray-900">
                                    {{ number_format(collect($rows)->sum(fn ($row) => (int) ($row['minutes'] ?? 0))) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
