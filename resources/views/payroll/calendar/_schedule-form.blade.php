@php
    use App\Support\PayrollCalendarModule;

    $selectedDeductionIds = $selectedDeductionIds ?? $period->deductions->pluck('deduction_type_id')->all();
    $selectedLoanIds = $selectedLoanIds ?? $period->loans->pluck('loan_type_id')->all();
@endphp

@can('payroll-calendar.update')
    <form method="POST" action="{{ route(PayrollCalendarModule::routeName('schedule'), ['payType' => $payTypeSlug, 'period' => $period->payroll_calendar_id]) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="view_period_id" value="{{ $period->payroll_calendar_id }}">

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Deduction Types</p>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-100 p-3">
                    @forelse ($deductionTypes as $deductionType)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="deduction_type_ids[]"
                                value="{{ $deductionType->deduction_type_id }}"
                                @checked(in_array($deductionType->deduction_type_id, $selectedDeductionIds, true))
                            >
                            <span>{{ $deductionType->description }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No deduction types configured.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Loan Types</p>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-100 p-3">
                    @forelse ($loanTypes as $loanType)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="loan_type_ids[]"
                                value="{{ $loanType->loan_type_id }}"
                                @checked(in_array($loanType->loan_type_id, $selectedLoanIds, true))
                            >
                            <span>{{ $loanType->description }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No loan types configured.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @include('partials.modal-form-actions', ['submitLabel' => 'Save Schedule'])
    </form>
@else
    @include('payroll.calendar._schedule-readonly', [
        'deductionTypes' => $deductionTypes,
        'loanTypes' => $loanTypes,
        'selectedDeductionIds' => $selectedDeductionIds,
        'selectedLoanIds' => $selectedLoanIds,
    ])
    <div class="flex justify-end border-t border-gray-100 pt-4">
        <button type="button" class="btn-secondary" data-modal-close>Close</button>
    </div>
@endcan
