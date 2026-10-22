@php
    use App\Models\PayrollDispute;

    $closeIcon = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
    $shortTime = fn (?string $value) => $value ? \Carbon\Carbon::createFromFormat('H:i', substr($value, 0, 5))->format('g:i A') : '—';
@endphp

@if ($disputesEditable)
    <div
        id="payroll-batch-add-dispute-modal"
        class="modal-overlay modal-overlay-nested {{ $openAdd ? '' : 'hidden' }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payroll-batch-add-dispute-modal-title"
        @if ($openAdd) data-modal-auto-open @endif
    >
        <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
        <div class="modal-panel max-w-2xl">
            <div class="modal-header">
                <div>
                    <h2 id="payroll-batch-add-dispute-modal-title" class="text-lg font-bold text-[#0B318F]">File Dispute</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Pick the work date and dispute type — the fields auto-fill from the logs and the earlier payroll. Adjust the times, then save.
                    </p>
                </div>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">{!! $closeIcon !!}</button>
            </div>
            <div class="modal-body">
                @include('payroll.transaction._batch-employee-dispute-form', [
                    'batch' => $batch,
                    'detail' => $detail,
                    'dispute' => null,
                    'shiftCodes' => $shiftCodes,
                    'maxWorkDate' => $maxWorkDate,
                    'useOld' => old('form_context') === 'create-payroll-dispute',
                    'modalId' => 'payroll-batch-add-dispute-modal',
                    'batchEmployeeSearch' => $batchEmployeeSearch,
                ])
            </div>
        </div>
    </div>
@endif

@foreach ($disputes as $dispute)
    @php
        $disputeId = $dispute->payroll_dispute_id;
        $openEdit = $disputesEditable && (int) $openEditId === (int) $disputeId;
    @endphp

    @if ($disputesEditable)
        <div
            id="payroll-batch-dispute-edit-modal-{{ $disputeId }}"
            class="modal-overlay modal-overlay-nested {{ $openEdit ? '' : 'hidden' }}"
            role="dialog"
            aria-modal="true"
            aria-labelledby="payroll-batch-dispute-edit-modal-title-{{ $disputeId }}"
            @if ($openEdit) data-modal-auto-open @endif
        >
            <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
            <div class="modal-panel max-w-2xl">
                <div class="modal-header">
                    <div>
                        <h2 id="payroll-batch-dispute-edit-modal-title-{{ $disputeId }}" class="text-lg font-bold text-[#0B318F]">Edit Dispute</h2>
                        <p class="mt-0.5 text-sm text-gray-500">{{ $dispute->categoryName() }} · {{ $dispute->work_date?->format('M j, Y') }}</p>
                    </div>
                    <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">{!! $closeIcon !!}</button>
                </div>
                <div class="modal-body">
                    @include('payroll.transaction._batch-employee-dispute-form', [
                        'batch' => $batch,
                        'detail' => $detail,
                        'dispute' => $dispute,
                        'shiftCodes' => $shiftCodes,
                        'maxWorkDate' => $maxWorkDate,
                        'useOld' => (int) old('edit_payroll_dispute_id') === (int) $disputeId,
                        'modalId' => 'payroll-batch-dispute-edit-modal-'.$disputeId,
                        'batchEmployeeSearch' => $batchEmployeeSearch,
                    ])
                </div>
            </div>
        </div>
    @endif

    <div
        id="payroll-batch-dispute-view-modal-{{ $disputeId }}"
        class="modal-overlay modal-overlay-nested hidden"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payroll-batch-dispute-view-modal-title-{{ $disputeId }}"
    >
        <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
        <div class="modal-panel max-w-lg">
            <div class="modal-header">
                <div>
                    <h2 id="payroll-batch-dispute-view-modal-title-{{ $disputeId }}" class="text-lg font-bold text-[#0B318F]">Dispute Details</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ $dispute->categoryName() }} · {{ $dispute->work_date?->format('M j, Y') }}</p>
                </div>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">{!! $closeIcon !!}</button>
            </div>
            <div class="modal-body">
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-gray-500">Work Date</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $dispute->work_date?->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Dispute Type</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $dispute->categoryName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Shift</dt>
                        <dd class="mt-1 text-gray-900">{{ $dispute->shiftCode?->shift_code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Originally Paid In</dt>
                        <dd class="mt-1 text-gray-900">{{ $dispute->sourcePayrollBatch ? 'Batch #'.$dispute->sourcePayrollBatch->formattedBatchNo() : '—' }}</dd>
                    </div>
                    @if ($dispute->category === PayrollDispute::UNPAID_OVERTIME)
                        <div>
                            <dt class="text-xs text-gray-500">OT Start</dt>
                            <dd class="mt-1 text-gray-900">{{ $shortTime($dispute->ot_start) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">OT End</dt>
                            <dd class="mt-1 text-gray-900">{{ $shortTime($dispute->ot_end) }}</dd>
                        </div>
                    @else
                        <div>
                            <dt class="text-xs text-gray-500">Time In</dt>
                            <dd class="mt-1 text-gray-900">{{ $shortTime($dispute->time_in) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Time Out</dt>
                            <dd class="mt-1 text-gray-900">{{ $shortTime($dispute->time_out) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-500">Expected Pay</dt>
                        <dd class="mt-1 font-semibold text-gray-900">
                            {{ number_format($dispute->expectedTotal(), 2) }}
                            @if ((float) $dispute->expected_ot_pay > 0)
                                <span class="block text-xs font-normal text-gray-500">
                                    Basic {{ number_format((float) $dispute->expected_pay, 2) }} + OT {{ number_format((float) $dispute->expected_ot_pay, 2) }}
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Paid in this Batch</dt>
                        <dd class="mt-1 text-gray-900">
                            {{ $dispute->computedTotal() !== null ? number_format($dispute->computedTotal(), 2) : 'Not processed yet' }}
                            @if ($dispute->changedAfterFiling())
                                <span class="block text-xs text-amber-700">Changed after filing (salary or earlier payroll changed).</span>
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">Remarks</dt>
                        <dd class="mt-1 text-gray-900">{{ $dispute->remarks ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endforeach
