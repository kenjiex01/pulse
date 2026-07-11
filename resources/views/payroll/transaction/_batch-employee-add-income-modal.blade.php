<div
    id="payroll-batch-add-income-modal"
    class="modal-overlay modal-overlay-nested {{ ($open ?? false) ? '' : 'hidden' }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="payroll-batch-add-income-modal-title"
    @if ($open ?? false) data-modal-auto-open @endif
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel modal-panel-lg">
        <div class="modal-header">
            <div>
                <h2 id="payroll-batch-add-income-modal-title" class="text-lg font-bold text-[#0B318F]">Add Income</h2>
                <p class="mt-0.5 text-sm text-gray-500">Add an income line for this employee in batch no. {{ $batch->formattedBatchNo() }}.</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body">
            @include('payroll.transaction._batch-employee-add-income-form', [
                'batch' => $batch,
                'detail' => $detail,
                'incomeTypes' => $incomeTypes,
                'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
            ])
        </div>
    </div>
</div>
