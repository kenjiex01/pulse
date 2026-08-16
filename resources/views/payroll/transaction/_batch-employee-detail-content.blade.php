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
            $daysSum = $group->sum(fn ($deduction) => (float) ($deduction->days ?? 0));
            $hasHours = in_array($code, ['LTDE', 'UTDE'], true)
                && $group->contains(fn ($deduction) => $deduction->hours !== null);
            $hasDays = in_array($code, ['LTDE', 'UTDE'], true)
                && $group->contains(fn ($deduction) => $deduction->days !== null);

            return [
                'code' => $code,
                'description' => PhilhealthDeductionTypes::payrollBatchLabel($code, $first->deductionType?->description),
                'hours' => $hasHours ? $hoursSum : null,
                'show_hours' => $hasHours,
                'minutes' => $hasHours ? (int) round($hoursSum * 60) : null,
                'days' => $hasDays ? $daysSum : null,
                'show_days' => $hasDays,
                'employee_amount' => $group->sum(fn ($deduction) => (float) $deduction->employee_amount),
                'employer_amount' => $group->sum(fn ($deduction) => (float) $deduction->employer_amount),
            ];
        })
        ->sortBy('code')
        ->values();
    $taxableTotal = $incomes->sum(fn ($income) => (float) $income->taxable);
    $nonTaxableTotal = $incomes->sum(fn ($income) => (float) $income->non_taxable);
    $hoursTotal = $incomes->contains(fn ($income) => $income->hours !== null)
        ? $incomes->sum(fn ($income) => (float) ($income->hours ?? 0))
        : null;
    $daysTotal = $incomes->contains(fn ($income) => $income->days !== null)
        ? $incomes->sum(fn ($income) => (float) ($income->days ?? 0))
        : null;
    $employeeShareTotal = $deductionRows->sum(fn (array $row) => $row['employee_amount']);
    $employerShareTotal = $deductionRows->sum(fn (array $row) => $row['employer_amount']);
    $deductionHoursTotal = $deductionRows->contains(fn (array $row) => ($row['show_hours'] ?? false))
        ? $deductionRows->sum(fn (array $row) => ($row['show_hours'] ?? false) ? (float) ($row['hours'] ?? 0) : 0.0)
        : null;
    $deductionMinutesTotal = $deductionRows->contains(fn (array $row) => ($row['minutes'] ?? null) !== null)
        ? $deductionRows->sum(fn (array $row) => (int) ($row['minutes'] ?? 0))
        : null;
    $deductionDaysTotal = $deductionRows->contains(fn (array $row) => ($row['show_days'] ?? false))
        ? $deductionRows->sum(fn (array $row) => ($row['show_days'] ?? false) ? (float) ($row['days'] ?? 0) : 0.0)
        : null;
    $grossIncomeTotal = $taxableTotal + $nonTaxableTotal;
    $netPayTotal = $grossIncomeTotal - $employeeShareTotal;
    $attendanceDayBreakdown = $attendanceDayBreakdown ?? ['LTDE' => [], 'UTDE' => [], 'OVRT' => []];
    $lateBreakdown = $attendanceDayBreakdown['LTDE'] ?? [];
    $undertimeBreakdown = $attendanceDayBreakdown['UTDE'] ?? [];
    $overtimeBreakdown = $attendanceDayBreakdown['OVRT'] ?? [];
    $canAddIncome = ($batchEditable ?? false) && $hasPayrollData;
    $canAddDeduction = ($batchEditable ?? false) && $hasPayrollData;
    $canAddShiftCode = (bool) ($batchEditable ?? false) && ($shiftCodes ?? collect())->isNotEmpty();
    $canAddOvertime = (bool) ($batchEditable ?? false);
    $shiftOverrides = $shiftOverrides ?? collect();
    $overtimeApprovals = $overtimeApprovals ?? collect();
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
        @if ($canAddIncome || $canAddShiftCode || $canAddOvertime)
            <div class="mb-3 flex flex-wrap justify-end gap-2">
                @if ($canAddShiftCode)
                    <button
                        type="button"
                        class="btn-primary !px-3 !py-1.5 text-xs"
                        data-modal-open="payroll-batch-add-shift-code-modal"
                    >
                        Add Shift Code
                    </button>
                @endif
                @if ($canAddOvertime)
                    <button
                        type="button"
                        class="btn-primary !px-3 !py-1.5 text-xs"
                        data-modal-open="payroll-batch-add-overtime-modal"
                    >
                        Add Overtime
                    </button>
                @endif
                @if ($canAddIncome)
                    <button
                        type="button"
                        class="btn-primary !px-3 !py-1.5 text-xs"
                        data-modal-open="payroll-batch-add-income-modal"
                    >
                        Add Income
                    </button>
                @endif
            </div>
        @endif

        @if ($shiftOverrides->isNotEmpty())
            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Work Date</th>
                            <th class="px-3 py-2 text-left">Shift Code</th>
                            <th class="px-3 py-2 text-left">Schedule</th>
                            @if ($canAddShiftCode)
                                <th class="px-3 py-2 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shiftOverrides as $override)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $override->work_date?->format('M j, Y') }}</td>
                                <td class="px-3 py-2 text-gray-700">
                                    {{ $override->shiftCode?->shift_code ?? '—' }}
                                    @if ($override->shiftCode?->description)
                                        <span class="text-gray-500">— {{ $override->shiftCode->description }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if ($override->shiftCode?->time_in && $override->shiftCode?->time_out)
                                        {{ \Illuminate\Support\Str::of($override->shiftCode->time_in)->substr(0, 5) }}
                                        –
                                        {{ \Illuminate\Support\Str::of($override->shiftCode->time_out)->substr(0, 5) }}
                                        @if ($override->shiftCode->is_flexi_time)
                                            <span class="text-xs text-gray-500">(Flexi)</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($canAddShiftCode)
                                    <td class="px-3 py-2 text-right">
                                        <form
                                            method="POST"
                                            action="{{ route('payroll.transaction.employees.shift-overrides.destroy', [$batch, $detail, $override]) }}"
                                            class="inline"
                                            onsubmit="return confirm('Remove this day shift override?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="batch_employee_search" value="{{ request('batch_employee_search') }}">
                                            <input type="hidden" name="search" value="{{ request('search') }}">
                                            <button type="submit" class="btn-icon text-red-600 hover:bg-red-50" title="Remove">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($canAddShiftCode)
            <p class="mb-3 text-xs text-gray-500">No day-specific shift codes for this pay period yet.</p>
        @endif

        @if ($overtimeApprovals->isNotEmpty())
            <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200">
                <table class="table-skolaris min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Work Date</th>
                            <th class="px-3 py-2 text-left">OT Start</th>
                            <th class="px-3 py-2 text-left">OT End</th>
                            @if ($canAddOvertime)
                                <th class="px-3 py-2 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overtimeApprovals as $otApproval)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ $otApproval->work_date?->format('M j, Y') }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $otApproval->ot_start?->format('g:i A') ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $otApproval->ot_end?->format('g:i A') ?? '—' }}</td>
                                @if ($canAddOvertime)
                                    <td class="px-3 py-2 text-right">
                                        <form
                                            method="POST"
                                            action="{{ route('payroll.transaction.employees.overtime-approvals.destroy', [$batch, $detail, $otApproval]) }}"
                                            class="inline"
                                            onsubmit="return confirm('Remove this overtime approval?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="batch_employee_search" value="{{ request('batch_employee_search') }}">
                                            <input type="hidden" name="search" value="{{ request('search') }}">
                                            <button type="submit" class="btn-icon text-red-600 hover:bg-red-50" title="Remove">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($canAddOvertime)
            <p class="mb-3 text-xs text-gray-500">No overtime filings for this pay period yet.</p>
        @endif
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Income Type Code</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Hours</th>
                        <th class="px-3 py-2 text-right">Days</th>
                        <th class="px-3 py-2 text-right">Taxable</th>
                        <th class="px-3 py-2 text-right">Non-Taxable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomes as $income)
                        @php
                            $incomeCode = $income->incomeType?->income_type_code;
                            $isOvertimeRow = $incomeCode === 'OVRT' && count($overtimeBreakdown) > 0;
                        @endphp
                        <tr
                            @if ($isOvertimeRow)
                                class="cursor-pointer hover:bg-[#00A3E6]/5"
                                data-modal-open="payroll-batch-overtime-breakdown-modal"
                                role="button"
                                tabindex="0"
                                title="View overtime day details"
                            @endif
                        >
                            <td class="px-3 py-2 font-medium text-gray-900">
                                {{ $incomeCode ?? '—' }}
                                @if ($isOvertimeRow)
                                    <span class="ml-1 text-xs font-normal text-[#00A3E6]">View days</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $income->incomeType?->description ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $income->hours !== null ? number_format((float) $income->hours, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $income->days !== null ? number_format((float) $income->days, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $income->taxable, 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format((float) $income->non_taxable, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                No income records for this employee yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($incomes->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-gray-900" colspan="2">Total</td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $hoursTotal !== null ? number_format($hoursTotal, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $daysTotal !== null ? number_format($daysTotal, 2) : '—' }}
                            </td>
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
                        <th class="px-3 py-2 text-right">Minutes</th>
                        <th class="px-3 py-2 text-right">Hours</th>
                        <th class="px-3 py-2 text-right">Days</th>
                        <th class="px-3 py-2 text-right">Employee Amount</th>
                        <th class="px-3 py-2 text-right">Employer Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deductionRows as $deduction)
                        @php
                            $deductionCode = $deduction['code'] ?? '';
                            $breakdownModalId = match ($deductionCode) {
                                'LTDE' => count($lateBreakdown) > 0 ? 'payroll-batch-late-breakdown-modal' : null,
                                'UTDE' => count($undertimeBreakdown) > 0 ? 'payroll-batch-undertime-breakdown-modal' : null,
                                default => null,
                            };
                        @endphp
                        <tr
                            @if ($breakdownModalId)
                                class="cursor-pointer hover:bg-[#00A3E6]/5"
                                data-modal-open="{{ $breakdownModalId }}"
                                role="button"
                                tabindex="0"
                                title="View day details"
                            @endif
                        >
                            <td class="px-3 py-2 font-medium text-gray-900">
                                {{ $deductionCode !== '' ? $deductionCode : '—' }}
                                @if ($breakdownModalId)
                                    <span class="ml-1 text-xs font-normal text-[#00A3E6]">View days</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-600">{{ $deduction['description'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ ($deduction['minutes'] ?? null) !== null ? number_format((int) $deduction['minutes']) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $deduction['show_hours'] ? number_format((float) $deduction['hours'], 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ ($deduction['show_days'] ?? false) ? number_format((float) $deduction['days'], 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($deduction['employee_amount'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900">{{ number_format($deduction['employer_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-gray-500">
                                No deduction records for this employee yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($deductionRows->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="px-3 py-2 text-gray-900" colspan="2">Total</td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $deductionMinutesTotal !== null ? number_format($deductionMinutesTotal) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $deductionHoursTotal !== null ? number_format($deductionHoursTotal, 2) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-900">
                                {{ $deductionDaysTotal !== null ? number_format($deductionDaysTotal, 2) : '—' }}
                            </td>
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
