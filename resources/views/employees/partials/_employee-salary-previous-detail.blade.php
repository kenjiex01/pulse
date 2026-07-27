@php
    $previousSalary->loadMissing([
        'payType',
        'basicComputation',
        'rateGroup',
        'ndRateGroup',
        'incomes.incomeType',
        'deductions.deductionType',
    ]);

    $detailId = (string) $previousSalary->employee_salary_id;
    $hourlyRate = $previousSalary->hourlyRate();
@endphp

<div
    class="hidden space-y-4"
    data-previous-salary-detail="{{ $detailId }}"
>
    <div class="employee-salary-tab-bar" data-previous-salary-subtabs="{{ $detailId }}">
        <button type="button" class="employee-salary-subtab-btn employee-salary-subtab-btn-active" data-previous-salary-subtab="income" data-previous-salary-detail-id="{{ $detailId }}">Income</button>
        <button type="button" class="employee-salary-subtab-btn" data-previous-salary-subtab="deduction" data-previous-salary-detail-id="{{ $detailId }}">Deduction</button>
    </div>

    <div data-previous-salary-subtab-panel="income" data-previous-salary-detail-id="{{ $detailId }}">
        @if ($previousSalary->incomes->isEmpty())
            <p class="text-sm text-gray-500">No income rows recorded.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Income</th>
                            <th class="px-3 py-2 text-right">Taxable</th>
                            <th class="px-3 py-2 text-right">Non-Taxable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previousSalary->incomes as $income)
                            <tr>
                                <td class="px-3 py-2">{{ $income->incomeType?->description ?: '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $income->taxable, 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $income->non_taxable, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="hidden" data-previous-salary-subtab-panel="deduction" data-previous-salary-detail-id="{{ $detailId }}">
        @if ($previousSalary->deductions->isEmpty())
            <p class="text-sm text-gray-500">No deduction rows recorded.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Deduction</th>
                            <th class="px-3 py-2 text-right">Employee Amount</th>
                            <th class="px-3 py-2 text-right">Employer Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previousSalary->deductions as $deduction)
                            <tr>
                                <td class="px-3 py-2">{{ $deduction->deductionType?->description ?: '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $deduction->employee_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $deduction->employer_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="border-t border-gray-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold text-gray-900">General</h4>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <p class="form-label">Effectivity From</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->date_effective_from?->format('M d, Y') ?: '—' }}</p>
            </div>
            <div>
                <p class="form-label">Effectivity To</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->date_effective_to?->format('M d, Y') ?: '—' }}</p>
            </div>
            <div>
                <p class="form-label">Pay Type</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->payType?->pay_type ?: '—' }}</p>
            </div>
            <div>
                <p class="form-label">Basic Computation</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->basicComputation?->basic_computation ?: '—' }}</p>
            </div>
            <div>
                <p class="form-label">Days Per Period</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->days_per_period !== null ? (string) (float) $previousSalary->days_per_period : '—' }}</p>
            </div>
            <div>
                <p class="form-label">Rate Group</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->rateGroup?->description ?: '—' }}</p>
            </div>
            <div>
                <p class="form-label">Hours Per Day</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->hours_per_day !== null ? (string) (float) $previousSalary->hours_per_day : '—' }}</p>
            </div>
            <div>
                <p class="form-label">Night Diff. Rate Group</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $previousSalary->ndRateGroup?->description ?: '—' }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-100 pt-4">
        <p class="mb-2 text-sm text-gray-700">
            <span class="font-medium">Use Basic Income as Hourly Rate:</span>
            {{ $previousSalary->use_basic_income_as_hourly_rate ? 'Yes' : 'No' }}
        </p>
        <p class="form-label">Hourly Rate</p>
        <p class="mt-1 text-sm font-medium text-gray-900">{{ $hourlyRate !== null ? number_format($hourlyRate, 2) : '—' }}</p>
    </div>
</div>
