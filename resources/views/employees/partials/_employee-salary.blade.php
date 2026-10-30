@php
    use App\Models\EmployeeEmploymentInformation;

    $isHybridChecked = (bool) old(
        'is_hybrid',
        request()->has('is_hybrid') ? request()->boolean('is_hybrid') : ($employee->is_hybrid ?? false),
    );
    $employmentInfos = $employee->employmentInformations->sortBy('sort_order')->values();
    $salaryRecords = old('employee_salaries');

    if ($salaryRecords === null) {
        $salaryRecords = $employmentInfos
            ->map(function ($employmentInfo, $index) {
                $salary = $employmentInfo->salary;

                if (! $salary) {
                    return null;
                }

                return [
                    'employment_index' => $index,
                    'employee_salary_id' => $salary->employee_salary_id,
                    'date_effective_from' => optional($salary->date_effective_from)->format('Y-m-d'),
                    'date_effective_to' => optional($salary->date_effective_to)->format('Y-m-d'),
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
                    'is_above_minimum_wage_earner' => $salary->is_above_minimum_wage_earner,
                    'is_fixed_rate' => $salary->is_fixed_rate,
                    'use_payroll_calendar_days' => $salary->use_payroll_calendar_days,
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
        Configure salary per employment record. Current Salary is the setup in effect today. A new Effectivity From moves that salary to Previous Salary and closes it the day before the new date. Payroll still uses the salary that covers the pay period, so a salary that starts later does not apply until that date.
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
                    'payrollBasis' => \App\Models\EmployeeCategory::usesTeachingLoads(\App\Models\EmployeeEmploymentInformation::TYPE_FACULTY)
                        ? \App\Models\EmployeeCategory::BASIS_TEACHING_LOADS
                        : \App\Models\EmployeeCategory::BASIS_LOGS,
                    'lockPayrollBasis' => true,
                    'formOptions' => $formOptions,
                    'previousSalaries' => $employmentInfos->get(0)?->previousSalaries ?? collect(),
                ])
                @include('employees.partials._employee-salary-panel', [
                    'salaryIndex' => 1,
                    'salary' => $salaryRecords[1] ?? [],
                    'panelTitle' => 'Staff Salary',
                    'payrollBasis' => \App\Models\EmployeeCategory::usesTeachingLoads(\App\Models\EmployeeEmploymentInformation::TYPE_STAFF)
                        ? \App\Models\EmployeeCategory::BASIS_TEACHING_LOADS
                        : \App\Models\EmployeeCategory::BASIS_LOGS,
                    'lockPayrollBasis' => true,
                    'formOptions' => $formOptions,
                    'previousSalaries' => $employmentInfos->get(1)?->previousSalaries ?? collect(),
                ])
            </div>
        @else
            <div data-employee-salary-single-panel>
                @php
                    $singleUserType = (string) ($employmentInfos->first()?->user_type ?? '');
                    $singlePayrollBasis = $singleUserType === ''
                        ? ''
                        : (\App\Models\EmployeeCategory::usesTeachingLoads($singleUserType)
                            ? \App\Models\EmployeeCategory::BASIS_TEACHING_LOADS
                            : \App\Models\EmployeeCategory::BASIS_LOGS);
                @endphp
                @include('employees.partials._employee-salary-panel', [
                    'salaryIndex' => 0,
                    'salary' => $salaryRecords[0] ?? [],
                    'panelTitle' => 'Salary',
                    'payrollBasis' => $singlePayrollBasis,
                    'formOptions' => $formOptions,
                    'previousSalaries' => $employmentInfos->first()?->previousSalaries ?? collect(),
                ])
            </div>
        @endif
    </div>
</section>
