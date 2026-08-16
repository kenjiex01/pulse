<div
    id="payroll-batch-add-overtime-modal"
    class="modal-overlay modal-overlay-nested {{ ($open ?? false) ? '' : 'hidden' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="payroll-batch-add-overtime-modal-title"
    @if ($open ?? false) data-modal-auto-open @endif
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel max-w-lg">
        <div class="modal-header">
            <div>
                <h2 id="payroll-batch-add-overtime-modal-title" class="text-lg font-bold text-[#0B318F]">Add Overtime</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    Pick a work date — OT Start/End auto-fill from excess hours outside the shift. Policy OT settings are ignored. Process/Reprocess the batch to apply it to pay.
                </p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            @include('payroll.transaction._batch-employee-add-overtime-form', [
                'batch' => $batch,
                'detail' => $detail,
                'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
            ])
        </div>
    </div>
</div>
