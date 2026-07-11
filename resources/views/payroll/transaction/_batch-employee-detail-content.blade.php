@php
    use App\Support\PayrollTransactionModule;
    use App\Support\PhilhealthDeductionTypes;

    $activeTab = PayrollTransactionModule::resolveBatchDetailTab($activeTab ?? 'incomes');
    $hasPayrollData = $detail->incomes->isNotEmpty() || $detail->deductions->isNotEmpty();
    $incomes = $detail->incomes->sortBy(fn ($income) => $income->incomeType?->income_type_code ?? '');
    $deductionRows = $detail->deductions
        ->groupBy(fn ($deduction) => (int) $deduction->deduction_type_id)
        ->map(function ($group) {
            $first = $group->first();
            $code = $first->deductionType?->deduction_type_code;
            $hoursSum = $group->sum(fn ($deduction) => (float) ($deduction->hours ?? 0));
            $hasHours = in_array($code, ['LTDE', 'UTDE'], true)
                && $group->contains(fn ($deduction) => $deduction->hours !== null);

            return [
                'code' => $code,
                'description' => PhilhealthDeductionTypes::payrollBatchLabel($code, $first->deductionType?->description),
                'hours' => $hasHours ? $hoursSum : null,
                'show_hours' => $hasHours,
                'employee_amount' => $group->sum(fn ($deduction) => (float) $deduction->employee_amount),
                'employer_amount' => $group->sum(fn ($deduction) => (float) $deduction->employer_amount),
            ];
        })
        ->sortBy('code')
        ->values();
    $taxableTotal = $incomes->sum(fn ($income) => (float) $income->taxable);
    $nonTaxableTotal = $incomes->sum(fn ($income) => (float) $income->non_taxable);
    $employeeShareTotal = $deductionRows->sum(fn (array $row) => $row['employee_amount']);
    $employerShareTotal = $deductionRows->sum(fn (array $row) => $row['employer_amount']);
    $grossIncomeTotal = $taxableTotal + $nonTaxableTotal;
    $netPayTotal = $grossIncomeTotal - $employeeShareTotal;
    $canAddIncome = ($batchEditable ?? false) && $hasPayrollData;
    $canAddDeduction = ($batchEditable ?? false) && $hasPayrollData;
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

    @if (! $hasPayrollData)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            This employee has no payroll data yet. Process the batch first to generate income and deduction lines.
        </div>
    @endif

    @include('payroll.transaction._batch-employee-detail-tabs-nav', ['activeTab' => $activeTab])

    <div class="employee-tab-panel {{ $activeTab === 'incomes' ? '' : 'hidden' }}" data-employee-tab-panel="incomes">
        @if ($canAddIncome)
            <div class="mb-3 flex justify-end">
                <button
                    type="button"
                    class="btn-primary !px-3 !py-1.5 text-xs"
                    data-modal-open="payroll-batch-add-income-modal"
                >
                    Add Income
                </button>
            </div>
        @endif
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
        @if ($canAddDeduction)
            <div class="mb-3 flex justify-end">
                <button
                    type="button"
                    class="btn-primary !px-3 !py-1.5 text-xs"
                    data-modal-open="payroll-batch-add-deduction-modal"
                >
                    Add Deduction
                </button>
            </div>
        @endif
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Deduction Type Code</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Hours</th>
                        <th class="px-3 py-2 text-right">Employee Amount</th>
                        <th class="px-3 py-2 text-right">Employer Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deductionRows as $deduction)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $deduction['code'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $deduction['description'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $deduction['show_hours'] ? number_format((float) $deduction['hours'], 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($deduction['employee_amount'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($deduction['employer_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                                No deduction records for this employee yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($deductionRows->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-gray-900" colspan="3">Total</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($employeeShareTotal, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($employerShareTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="employee-tab-panel {{ $activeTab === 'net-pay' ? '' : 'hidden' }}" data-employee-tab-panel="net-pay">
        @include('payroll.transaction._batch-employee-detail-net-pay')
    </div>
</div>
