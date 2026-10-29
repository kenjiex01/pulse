<div class="overflow-x-auto rounded-xl border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">Batch</th>
                <th class="px-4 py-3">Pay Period</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Deducted On</th>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3 text-right">Payment</th>
                <th class="px-4 py-3 text-right">Penalty</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($payments as $payment)
                @php
                    $batch = $payment->payrollBatchDetail?->payrollBatch;
                    $calendar = $batch?->payrollCalendar;
                    $deductedOn = $batch?->dt_posted ?? $batch?->dt_processed ?? $payment->created_at;
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
                    <td class="px-4 py-3 text-gray-700">{{ $payment->is_manual ? 'Manual' : 'Auto' }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-900">{{ number_format((float) $payment->payment, 2) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format((float) ($payment->penalty ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        No payroll batch has deducted this loan yet. A row appears after a batch is processed for a pay period on or after the payment start date, and only when this loan type is checked on that payroll calendar, or when a loan deduction is added manually in the payroll batch Loans tab.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($payments->isNotEmpty())
            <tfoot class="bg-gray-50 text-sm font-semibold text-gray-900">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right">Total deducted</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($payments->sum(fn ($payment) => (float) $payment->payment), 2) }}</td>
                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format($payments->sum(fn ($payment) => (float) ($payment->penalty ?? 0)), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
