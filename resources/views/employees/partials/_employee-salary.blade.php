@php
    use App\Models\EmployeeEmploymentInformation;

    $isHybridChecked = (bool) old(
        'is_hybrid',
        request()->has('is_hybrid') ? request()->boolean('is_hybrid') : ($employee->is_hybrid ?? false),
    );
    $salaryRecords = old('employee_salaries');

    if ($salaryRecords === null) {
        $salaryRecords = $employee->employmentInformations
            ->map(function ($employmentInfo) {
                $salary = $employmentInfo->salary;

                if (! $salary) {
                    return null;
                }

                return [
                    'employment_index' => $employmentInfo->sort_order,
                    'date_effective' => optional($salary->date_effective)->format('Y-m-d'),
                    'basic_computation_id' => $salary->basic_computation_id,
                    'pay_type_id' => $salary->pay_type_id,
                    'days_per_period' => $salary->days_per_period !== null
                        ? (string) (float) $salary->days_per_period
                        : '',
                    'hours_per_day' => $salary->hours_per_day !== null
                        ? (string) (float) $salary->hours_per_day
                        : '',
                    'rate_group_id' => $salary->rate_group_id,
                    'nd_rate_group_id' => $salary->nd_rate_group_id,
                    'use_basic_income_as_hourly_rate' => $salary->use_basic_income_as_hourly_rate,
                    'incomes' => $salary->incomes->map(fn ($income) => [
                        'income_type_id' => $income->income_type_id,
                        'taxable' => $income->taxable !== null ? (string) (float) $income->taxable : '',
                        'non_taxable' => $income->non_taxable !== null ? (string) (float) $income->non_taxable : '',
                    ])->values()->all(),
                    'deductions' => $salary->deductions->map(fn ($deduction) => [
                        'deduction_type_id' => $deduction->deduction_type_id,
                        'employee_amount' => $deduction->employee_amount,
                        'employer_amount' => $deduction->employer_amount,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    if ($salaryRecords === [] || $salaryRecords === null) {
        $salaryRecords = [['employment_index' => 0]];
    }
@endphp

<section class="employee-tab-section" data-employee-salary-root>
    <h2 class="mb-2 text-lg font-semibold text-gray-900">Employee Salary</h2>
    <p class="mb-4 text-sm text-gray-600">
        Configure salary per employment record. Hybrid employees require separate Faculty and Staff salary setups.
    </p>

    @error('employee_salaries')<p class="mb-4 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('employee_salaries.0.days_per_period')<p class="mb-4 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('employee_salaries.0.incomes.0.taxable')<p class="mb-4 text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="space-y-4" data-employee-salary-panels>
        @if ($isHybridChecked)
            <div class="space-y-4" data-employee-salary-hybrid-panels>
                @include('employees.partials._employee-salary-panel', [
                    'salaryIndex' => 0,
                    'salary' => $salaryRecords[0] ?? [],
                    'panelTitle' => 'Faculty Salary',
                    'formOptions' => $formOptions,
                ])
                @include('employees.partials._employee-salary-panel', [
                    'salaryIndex' => 1,
                    'salary' => $salaryRecords[1] ?? [],
                    'panelTitle' => 'Staff Salary',
                    'formOptions' => $formOptions,
                ])
            </div>
        @else
            <div data-employee-salary-single-panel>
                @include('employees.partials._employee-salary-panel', [
                    'salaryIndex' => 0,
                    'salary' => $salaryRecords[0] ?? [],
                    'panelTitle' => 'Salary',
                    'formOptions' => $formOptions,
                ])
            </div>
        @endif
    </div>
</section>
