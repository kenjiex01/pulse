@php
    use App\Models\BasicComputation;
    use App\Models\IncomeType;
    use App\Models\PayType;

    $salaryIndex = $salaryIndex ?? 0;
    $salary = $salary ?? [];
    $panelTitle = $panelTitle ?? 'Employee Salary';
    $defaultRateGroupId = $formOptions['rateGroups']->first()?->rate_group_id;
    $defaultNdRateGroupId = $formOptions['ndRateGroups']->first()?->nd_rate_group_id;
    $defaultBasicIncomeTypeId = $formOptions['incomeTypes']->firstWhere('income_type_code', 'BASC')?->income_type_id
        ?? $formOptions['incomeTypes']->first()?->income_type_id;
    $incomes = old("employee_salaries.$salaryIndex.incomes", $salary['incomes'] ?? [[
        'income_type_id' => $defaultBasicIncomeTypeId,
        'taxable' => '',
        'non_taxable' => '',
    ]]);
    $deductions = old("employee_salaries.$salaryIndex.deductions", $salary['deductions'] ?? []);
    $basicIncomeRow = collect($incomes)->first(
        fn ($income) => (string) ($income['income_type_id'] ?? '') === (string) $defaultBasicIncomeTypeId,
    );
    $basicIncomeAmount = (float) ($basicIncomeRow['taxable'] ?? 0) + (float) ($basicIncomeRow['non_taxable'] ?? 0);
    $payTypeId = (int) old("employee_salaries.$salaryIndex.pay_type_id", $salary['pay_type_id'] ?? PayType::SEMI_MONTHLY);
    $isDailyPayType = $payTypeId === PayType::DAILY;
    $daysPerPeriod = $isDailyPayType
        ? PayType::autoDaysPerPeriod(PayType::DAILY)
        : old("employee_salaries.$salaryIndex.days_per_period", $salary['days_per_period'] ?? '');
    $useBasicIncomeAsHourlyRate = (bool) old(
        "employee_salaries.$salaryIndex.use_basic_income_as_hourly_rate",
        $salary['use_basic_income_as_hourly_rate'] ?? false,
    );
    $initialHourlyRate = $useBasicIncomeAsHourlyRate
        ? ($basicIncomeAmount > 0 ? round($basicIncomeAmount, 2) : null)
        : \App\Models\EmployeeSalary::computeHourlyRate(
            $basicIncomeAmount,
            $daysPerPeriod,
            old("employee_salaries.$salaryIndex.hours_per_day", $salary['hours_per_day'] ?? '8.00'),
        );
@endphp

<div
    class="rounded-lg border border-gray-200 bg-white p-4"
    data-employee-salary-panel
    data-salary-index="{{ $salaryIndex }}"
    data-basic-income-type-id="{{ $defaultBasicIncomeTypeId }}"
    data-pay-type-daily="{{ PayType::DAILY }}"
>
    <input type="hidden" name="employee_salaries[{{ $salaryIndex }}][employment_index]" value="{{ $salaryIndex }}">

    <h3 class="mb-4 text-sm font-semibold text-gray-900">{{ $panelTitle }}</h3>

    <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200" data-salary-subtabs>
        <button type="button" class="employee-salary-subtab-btn employee-salary-subtab-btn-active" data-salary-subtab="income">Income</button>
        <button type="button" class="employee-salary-subtab-btn" data-salary-subtab="deduction">Deduction</button>
    </div>

    <div data-salary-subtab-panel="income">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="w-10 px-3 py-2"></th>
                        <th class="px-3 py-2 text-left">Income</th>
                        <th class="px-3 py-2 text-right">Taxable</th>
                        <th class="px-3 py-2 text-right">Non-Taxable</th>
                    </tr>
                </thead>
                <tbody data-salary-income-rows="{{ $salaryIndex }}">
                    @foreach ($incomes as $incomeIndex => $income)
                        @include('employees.partials._employee-salary-income-row', [
                            'salaryIndex' => $salaryIndex,
                            'incomeIndex' => $incomeIndex,
                            'income' => $income,
                            'formOptions' => $formOptions,
                        ])
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-salary-add-income="{{ $salaryIndex }}">Add Income</button>
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-salary-delete-income="{{ $salaryIndex }}">Delete</button>
        </div>
    </div>

    <div class="hidden" data-salary-subtab-panel="deduction">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="table-skolaris min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="w-10 px-3 py-2"></th>
                        <th class="px-3 py-2 text-left">Deduction</th>
                        <th class="px-3 py-2 text-right">Employee Amount</th>
                        <th class="px-3 py-2 text-right">Employer Amount</th>
                    </tr>
                </thead>
                <tbody data-salary-deduction-rows="{{ $salaryIndex }}">
                    @forelse ($deductions as $deductionIndex => $deduction)
                        @include('employees.partials._employee-salary-deduction-row', [
                            'salaryIndex' => $salaryIndex,
                            'deductionIndex' => $deductionIndex,
                            'deduction' => $deduction,
                            'formOptions' => $formOptions,
                        ])
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-salary-add-deduction="{{ $salaryIndex }}">Add Deduction</button>
            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-salary-delete-deduction="{{ $salaryIndex }}">Delete</button>
        </div>
    </div>

    <div class="mt-6 border-t border-gray-100 pt-4">
        <h4 class="mb-3 text-sm font-semibold text-gray-900">General</h4>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="form-label">Date Effective <span class="text-red-500">*</span></label>
                <input type="date" name="employee_salaries[{{ $salaryIndex }}][date_effective]" value="{{ old("employee_salaries.$salaryIndex.date_effective", $salary['date_effective'] ?? now()->format('Y-m-d')) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Pay Type <span class="text-red-500">*</span></label>
                <select name="employee_salaries[{{ $salaryIndex }}][pay_type_id]" class="form-input" data-salary-pay-type data-no-searchable-select required>
                    <option value="">Select Pay Type</option>
                    @foreach ($formOptions['payTypes'] as $payType)
                        <option value="{{ $payType->pay_type_id }}" @selected((string) old("employee_salaries.$salaryIndex.pay_type_id", $salary['pay_type_id'] ?? PayType::SEMI_MONTHLY) === (string) $payType->pay_type_id)>
                            {{ $payType->pay_type }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Basic Computation <span class="text-red-500">*</span></label>
                <select name="employee_salaries[{{ $salaryIndex }}][basic_computation_id]" class="form-input" data-no-searchable-select required>
                    <option value="">Select Basic Computation</option>
                    @foreach ($formOptions['basicComputations'] as $basicComputation)
                        <option value="{{ $basicComputation->basic_computation_id }}" @selected((string) old("employee_salaries.$salaryIndex.basic_computation_id", $salary['basic_computation_id'] ?? BasicComputation::LEAVES) === (string) $basicComputation->basic_computation_id)>
                            {{ $basicComputation->basic_computation }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">
                    Days Per Period
                    <span class="text-red-500 @if($isDailyPayType) hidden @endif" data-salary-days-required-indicator>*</span>
                </label>
                <input
                    type="number"
                    min="0"
                    step="0.00001"
                    name="employee_salaries[{{ $salaryIndex }}][days_per_period]"
                    value="{{ $daysPerPeriod }}"
                    class="form-input @if($isDailyPayType) bg-gray-50 @endif"
                    data-salary-days-per-period
                    @readonly($isDailyPayType)
                    @required(! $isDailyPayType)
                >
                @if ($isDailyPayType)
                    <p class="mt-1 text-xs text-gray-500">Auto-set to 1 for Daily pay type.</p>
                @endif
                @error("employee_salaries.$salaryIndex.days_per_period")
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="form-label">Rate Group <span class="text-red-500">*</span></label>
                <select name="employee_salaries[{{ $salaryIndex }}][rate_group_id]" class="form-input" data-no-searchable-select required>
                    <option value="">Select Rate Group</option>
                    @foreach ($formOptions['rateGroups'] as $rateGroup)
                        <option value="{{ $rateGroup->rate_group_id }}" @selected((string) old("employee_salaries.$salaryIndex.rate_group_id", $salary['rate_group_id'] ?? $defaultRateGroupId) === (string) $rateGroup->rate_group_id)>
                            {{ $rateGroup->description }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Hours Per Day</label>
                <input type="number" min="0" step="0.01" name="employee_salaries[{{ $salaryIndex }}][hours_per_day]" value="{{ old("employee_salaries.$salaryIndex.hours_per_day", $salary['hours_per_day'] ?? '8.00') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Night Diff. Rate Group</label>
                <select name="employee_salaries[{{ $salaryIndex }}][nd_rate_group_id]" class="form-input">
                    <option value="">Select Night Diff. Rate Group</option>
                    @foreach ($formOptions['ndRateGroups'] as $ndRateGroup)
                        <option value="{{ $ndRateGroup->nd_rate_group_id }}" @selected((string) old("employee_salaries.$salaryIndex.nd_rate_group_id", $salary['nd_rate_group_id'] ?? $defaultNdRateGroupId) === (string) $ndRateGroup->nd_rate_group_id)>
                            {{ $ndRateGroup->description }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="mt-4 border-t border-gray-100 pt-4">
        <label class="mb-3 flex items-center gap-2 text-sm text-gray-700">
            <input
                type="checkbox"
                name="employee_salaries[{{ $salaryIndex }}][use_basic_income_as_hourly_rate]"
                value="1"
                data-salary-use-basic-income-as-hourly-rate
                @checked($useBasicIncomeAsHourlyRate)
            >
            <span>Use Basic Income as Hourly Rate</span>
        </label>
        <label class="form-label">Hourly Rate</label>
        <input
            type="text"
            readonly
            tabindex="-1"
            class="form-input bg-gray-50 text-right font-medium text-gray-900"
            data-salary-hourly-rate
            value="{{ $initialHourlyRate !== null ? number_format($initialHourlyRate, 2, '.', ',') : '—' }}"
        >
        <p class="mt-1 text-xs text-gray-500" data-salary-hourly-rate-hint>
            {{ $useBasicIncomeAsHourlyRate
                ? 'Uses Basic Income amount directly.'
                : 'Auto-computed from Basic Income, Days Per Period, and Hours Per Day.' }}
        </p>
    </div>

    <template data-salary-income-template="{{ $salaryIndex }}">
        @include('employees.partials._employee-salary-income-row', [
            'salaryIndex' => $salaryIndex,
            'incomeIndex' => '__INDEX__',
            'income' => [],
            'formOptions' => $formOptions,
        ])
    </template>

    <template data-salary-deduction-template="{{ $salaryIndex }}">
        @include('employees.partials._employee-salary-deduction-row', [
            'salaryIndex' => $salaryIndex,
            'deductionIndex' => '__INDEX__',
            'deduction' => [],
            'formOptions' => $formOptions,
        ])
    </template>
</div>
