<div class="grid gap-4 md:grid-cols-2">
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Deduction Types</p>
        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-100 p-3">
            @forelse ($deductionTypes as $deductionType)
                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="{{ in_array($deductionType->deduction_type_id, $selectedDeductionIds, true) ? 'text-green-600' : 'text-gray-400' }}">●</span>
                    <span>{{ $deductionType->description }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No deduction types configured.</p>
            @endforelse
        </div>
    </div>
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Loan Types</p>
        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-100 p-3">
            @forelse ($loanTypes as $loanType)
                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="{{ in_array($loanType->loan_type_id, $selectedLoanIds, true) ? 'text-green-600' : 'text-gray-400' }}">●</span>
                    <span>{{ $loanType->description }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No loan types configured.</p>
            @endforelse
        </div>
    </div>
</div>
