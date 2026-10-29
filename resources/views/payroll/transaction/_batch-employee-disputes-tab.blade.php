@php
    use App\Models\PayrollDispute;

    $shortTime = fn (?string $value) => $value ? \Carbon\Carbon::createFromFormat('H:i', substr($value, 0, 5))->format('g:i A') : null;
@endphp

@if ($disputesEditable)
    <div class="mb-3 flex justify-end">
        <button
            type="button"
            class="btn-primary !px-3 !py-1.5 text-xs"
            data-modal-open="payroll-batch-add-dispute-modal"
        >
            File Dispute
        </button>
    </div>
@endif

<p class="mb-3 text-xs text-gray-500">
    Disputes correct days from a previous (posted) pay period using the times filed here, not the raw logs.
    Process/Reprocess the batch to apply them — unpaid / over-deduction disputes become income lines; Over Payment disputes become deduction lines (shown as negative).
</p>

<div class="overflow-x-auto rounded-lg border border-gray-200">
    <table class="table-skolaris min-w-full text-sm">
        <thead>
            <tr>
                <th class="px-3 py-2 text-left">Work Date</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-left">Filed Times</th>
                <th class="px-3 py-2 text-right">Expected Pay</th>
                <th class="px-3 py-2 text-right">Paid in this Batch</th>
                <th class="px-3 py-2 text-left">Remarks</th>
                <th class="px-3 py-2 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($disputes as $dispute)
                @php
                    $computed = $dispute->signedComputedTotal();
                    $times = match ($dispute->category) {
                        PayrollDispute::UNPAID_OVERTIME => 'OT '.($shortTime($dispute->ot_start) ?? '—').' – '.($shortTime($dispute->ot_end) ?? '—'),
                        PayrollDispute::OVER_PAYMENT_OVERTIME => $dispute->ot_start
                            ? 'Actual OT '.$shortTime($dispute->ot_start).' – '.($shortTime($dispute->ot_end) ?? '—')
                            : 'No OT rendered',
                        PayrollDispute::OVER_PAYMENT_DAYS => $dispute->time_in
                            ? ($shortTime($dispute->time_in) ?? '—').' – '.($shortTime($dispute->time_out) ?? '—')
                            : 'Absent',
                        PayrollDispute::OVER_DEDUCTION_LATE => 'In '.($shortTime($dispute->time_in) ?? '—'),
                        default => ($shortTime($dispute->time_in) ?? '—').' – '.($shortTime($dispute->time_out) ?? '—'),
                    };
                @endphp
                <tr>
                    <td class="px-3 py-2 font-medium text-gray-900">{{ $dispute->work_date?->format('M j, Y') }}</td>
                    <td class="px-3 py-2 text-gray-700">{{ $dispute->categoryName() }}</td>
                    <td class="px-3 py-2 text-gray-700">
                        {{ $times }}
                        @if ($dispute->shiftCode)
                            <span class="block text-xs text-gray-500">Shift {{ $dispute->shiftCode->shift_code }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right {{ $dispute->isOverPayment() ? 'text-red-700' : 'text-gray-900' }}">
                        {{ number_format($dispute->signedExpectedTotal(), 2) }}
                        @if ((float) $dispute->expected_ot_pay > 0 && ! $dispute->isOverPayment())
                            <span class="block text-xs text-gray-500">incl. OT {{ number_format((float) $dispute->expected_ot_pay, 2) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-gray-900">
                        {{ $computed !== null ? number_format($computed, 2) : '—' }}
                        @if ($dispute->changedAfterFiling())
                            <span class="block text-xs text-amber-700">Changed after filing</span>
                        @elseif ($computed === null)
                            <span class="block text-xs text-gray-500">Not processed yet</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $dispute->remarks ?: '—' }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        <button
                            type="button"
                            class="btn-icon"
                            title="View"
                            data-modal-open="payroll-batch-dispute-view-modal-{{ $dispute->payroll_dispute_id }}"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                        @if ($disputesEditable)
                            <button
                                type="button"
                                class="btn-icon"
                                title="Edit"
                                data-modal-open="payroll-batch-dispute-edit-modal-{{ $dispute->payroll_dispute_id }}"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form
                                method="POST"
                                action="{{ route('payroll.transaction.employees.disputes.destroy', [$batch, $detail, $dispute]) }}"
                                class="inline"
                                onsubmit="return confirm('Remove this dispute?');"
                            >
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="batch_employee_search" value="{{ request('batch_employee_search') }}">
                                <input type="hidden" name="search" value="{{ request('search') }}">
                                <button type="submit" class="btn-icon text-red-600 hover:bg-red-50" title="Remove">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-3 py-8 text-center text-gray-500">
                        No disputes filed for this employee in this batch.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($disputes->isNotEmpty())
            <tfoot>
                <tr class="bg-gray-50 font-semibold">
                    <td class="px-3 py-2 text-gray-900" colspan="3">Total</td>
                    <td class="px-3 py-2 text-right text-gray-900">{{ number_format($disputes->sum(fn ($d) => $d->signedExpectedTotal()), 2) }}</td>
                    <td class="px-3 py-2 text-right text-gray-900">{{ number_format($disputes->sum(fn ($d) => (float) $d->signedComputedTotal()), 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
