<div class="overflow-x-auto rounded-xl border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">Batch</th>
                <th class="px-4 py-3">Pay Period</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Deducted On</th>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3 text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($lines as $line)
                @php
                    $batch = $line->payrollBatchDetail?->payrollBatch;
                    $calendar = $batch?->payrollCalendar;
                    $deductedOn = $batch?->dt_posted ?? $batch?->dt_processed ?? $line->created_at;
                @endphp
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $batch?->batch_no ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">
                        @if ($calendar)
                            {{ $calendar->formattedPayPeriod() }}
                            <span class="block text-xs text-gray-500">
                                {{ $calendar->dt_from?->format('M j, Y') }} – {{ $calendar->dt_to?->format('M j, Y') }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $batch?->status?->payroll_batch_status ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $deductedOn?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $line->is_manual ? 'Manual' : 'Auto' }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-900">{{ number_format((float) $line->employee_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        No payroll batch has deducted this yet. A row appears after a batch is processed for a pay period on or after the deduction date, and only when this deduction type is checked on that payroll calendar.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($lines->isNotEmpty())
            <tfoot class="bg-gray-50 text-sm font-semibold text-gray-900">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right">Total deducted</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($lines->sum(fn ($line) => (float) $line->employee_amount), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
