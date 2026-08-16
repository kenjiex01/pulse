@php
    $loans = $isEdit
        ? ($employee->relationLoaded('loans') ? $employee->loans : $employee->loans()->with(['loanType', 'paymentScheme'])->get())
        : collect();
@endphp

<div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'loans') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="loans" @endunless>
    <section class="employee-tab-section">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Loans</h2>
                <p class="mt-1 text-sm text-gray-500">Employee loan records used for amortization and payroll deduction.</p>
            </div>
            @if ($isEdit)
                <button
                    type="button"
                    class="btn-secondary"
                    data-modal-open="employee-loan-create-modal"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add
                </button>
            @endif
        </div>

        @unless ($isEdit)
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600">
                Save the employee first to manage loans. This tab stays available so you can return here after creating the record.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Loan Date</th>
                            <th class="px-4 py-3">Loan Type</th>
                            <th class="px-4 py-3">Payment Scheme</th>
                            <th class="px-4 py-3 text-right">Loan Amount</th>
                            <th class="px-4 py-3 text-right">Amortization</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($loans as $loan)
                            <tr>
                                <td class="px-4 py-3 text-gray-700">{{ $loan->dt_loan?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $loan->loanType?->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $loan->paymentScheme?->payment_scheme ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format((float) $loan->loan_amount, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $loan->amortization_amount !== null ? number_format((float) $loan->amortization_amount, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">{{ number_format($loan->loanBalance(), 2) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="btn-icon"
                                            title="Edit"
                                            data-modal-open="employee-loan-edit-modal-{{ $loan->employee_loan_id }}"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button
                                            type="submit"
                                            class="btn-icon text-red-600 hover:bg-red-50"
                                            title="Delete"
                                            form="destroy-employee-loan-{{ $loan->employee_loan_id }}"
                                            onclick="return confirm('Remove this loan?');"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No loans yet. Click Add to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endunless
    </section>
</div>
