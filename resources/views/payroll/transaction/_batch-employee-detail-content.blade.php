@php
    use App\Support\PayrollTransactionModule;

    $activeTab = PayrollTransactionModule::resolveBatchDetailTab($activeTab ?? 'incomes');
    $incomes = $detail->incomes->sortBy(fn ($income) => $income->incomeType?->income_type_code ?? '');
    $deductions = $detail->deductions->sortBy(fn ($deduction) => $deduction->deductionType?->deduction_type_code ?? '');
    $taxableTotal = $incomes->sum(fn ($income) => (float) $income->taxable);
    $nonTaxableTotal = $incomes->sum(fn ($income) => (float) $income->non_taxable);
    $employeeShareTotal = $deductions->sum(fn ($deduction) => (float) $deduction->employee_amount);
    $employerShareTotal = $deductions->sum(fn ($deduction) => (float) $deduction->employer_amount);
@endphp

<div class="space-y-4" data-employee-form-tabs>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500">Employee No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $detail->employee?->employee_number ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Employee Name</p>
            <p class="mt-1 font-medium text-gray-900">{{ $detail->employee?->full_name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Batch No.</p>
            <p class="mt-1 font-medium text-gray-900">{{ $batch->formattedBatchNo() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Pay Period</p>
            <p class="mt-1 font-medium text-gray-900">
                @if ($batch->payrollCalendar)
                    {{ $batch->payrollCalendar->formattedPayPeriod() }}
                    · {{ $batch->payrollCalendar->dt_from->format('M j') }} – {{ $batch->payrollCalendar->dt_to->format('M j, Y') }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    @include('payroll.transaction._batch-employee-detail-tabs-nav', ['activeTab' => $activeTab])

    <div class="employee-tab-panel {{ $activeTab === 'incomes' ? '' : 'hidden' }}" data-employee-tab-panel="incomes">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Income Type Code</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Taxable</th>
                        <th class="px-3 py-2 text-right">Non-Taxable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomes as $income)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $income->incomeType?->income_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $income->incomeType?->description ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $income->taxable, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $income->non_taxable, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-gray-500">
                                No income records for this employee yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($incomes->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-gray-900" colspan="2">Total</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($taxableTotal, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($nonTaxableTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="employee-tab-panel {{ $activeTab === 'deductions' ? '' : 'hidden' }}" data-employee-tab-panel="deductions">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Deduction Type Code</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Employee Amount</th>
                        <th class="px-3 py-2 text-right">Employer Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deductions as $deduction)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $deduction->deductionType?->deduction_type_code ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $deduction->deductionType?->description ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $deduction->employee_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $deduction->employer_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-gray-500">
                                No deduction records for this employee yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($deductions->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-gray-900" colspan="2">Total</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($employeeShareTotal, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($employerShareTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
