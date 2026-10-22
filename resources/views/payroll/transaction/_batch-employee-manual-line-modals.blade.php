@php
    $closeIcon = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
@endphp

@foreach ($detail->incomes->where('is_manual', true) as $income)
    @php
        $modalId = 'payroll-batch-edit-income-modal-'.$income->payroll_income_id;
        $open = (int) ($openEditIncomeId ?? 0) === (int) $income->payroll_income_id;
    @endphp
    <div
        id="{{ $modalId }}"
        class="modal-overlay modal-overlay-nested {{ $open ? '' : 'hidden' }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $modalId }}-title"
        @if ($open) data-modal-auto-open @endif
    >
        <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
        <div class="modal-panel modal-panel-lg">
            <div class="modal-header">
                <div>
                    <h2 id="{{ $modalId }}-title" class="text-lg font-bold text-[#0B318F]">Edit Income</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Edit this manually added income line in batch no. {{ $batch->formattedBatchNo() }}.</p>
                </div>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">{!! $closeIcon !!}</button>
            </div>
            <div class="modal-body">
                @include('payroll.transaction._batch-employee-add-income-form', [
                    'batch' => $batch,
                    'detail' => $detail,
                    'incomeTypes' => $incomeTypes,
                    'line' => $income,
                    'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
                ])
            </div>
        </div>
    </div>
@endforeach

@foreach ($detail->deductions->where('is_manual', true) as $deduction)
    @php
        $modalId = 'payroll-batch-edit-deduction-modal-'.$deduction->payroll_deduction_id;
        $open = (int) ($openEditDeductionId ?? 0) === (int) $deduction->payroll_deduction_id;
    @endphp
    <div
        id="{{ $modalId }}"
        class="modal-overlay modal-overlay-nested {{ $open ? '' : 'hidden' }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $modalId }}-title"
        @if ($open) data-modal-auto-open @endif
    >
        <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
        <div class="modal-panel modal-panel-lg">
            <div class="modal-header">
                <div>
                    <h2 id="{{ $modalId }}-title" class="text-lg font-bold text-[#0B318F]">Edit Deduction</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Edit this manually added deduction line in batch no. {{ $batch->formattedBatchNo() }}.</p>
                </div>
                <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">{!! $closeIcon !!}</button>
            </div>
            <div class="modal-body">
                @include('payroll.transaction._batch-employee-add-deduction-form', [
                    'batch' => $batch,
                    'detail' => $detail,
                    'deductionTypes' => $deductionTypes,
                    'line' => $deduction,
                    'batchEmployeeSearch' => $batchEmployeeSearch ?? '',
                ])
            </div>
        </div>
    </div>
@endforeach
