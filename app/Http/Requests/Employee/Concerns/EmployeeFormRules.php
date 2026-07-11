<?php

namespace App\Http\Requests\Employee\Concerns;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\PayType;
use App\Services\EmployeeCampusAssignmentSync;
use App\Services\EmployeeEmploymentSync;
use App\Services\EmployeeSalarySync;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait EmployeeFormRules
{
    protected function employeeFieldRules(?Employee $employee = null): array
    {
        return [
            'employee_number' => $this->employeeNumberRules($employee),
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'is_hybrid' => ['sometimes', 'boolean'],
            'employment_informations' => ['required', 'array', 'min:1', 'max:2'],
            'employment_informations.*.user_type' => ['required', Rule::in([
                EmployeeEmploymentInformation::TYPE_FACULTY,
                EmployeeEmploymentInformation::TYPE_STAFF,
                EmployeeEmploymentInformation::TYPE_ADMIN,
            ])],
            'employment_informations.*.position' => ['nullable', 'string', 'max:150'],
            'employment_informations.*.designation' => ['nullable', 'string', 'max:150'],
            'employment_informations.*.rank' => ['nullable', 'string', 'max:150'],
            'employment_informations.*.employment_type' => ['nullable', 'string', 'max:100'],
            'employment_informations.*.hire_date' => ['nullable', 'date'],
            'employee_salaries' => ['required', 'array', 'min:1', 'max:2'],
            'employee_salaries.*.employment_index' => ['nullable', 'integer', 'min:0', 'max:1'],
            'employee_salaries.*.date_effective' => ['required', 'date'],
            'employee_salaries.*.basic_computation_id' => ['required', Rule::exists('lu_basic_computations', 'basic_computation_id')],
            'employee_salaries.*.pay_type_id' => ['required', Rule::exists('lu_pay_types', 'pay_type_id')],
            'employee_salaries.*.rate_group_id' => ['required', Rule::exists('tbl_rate_groups', 'rate_group_id')],
            'employee_salaries.*.nd_rate_group_id' => ['nullable', Rule::exists('tbl_nd_rate_groups', 'nd_rate_group_id')],
            'employee_salaries.*.days_per_period' => ['nullable', 'numeric', 'min:0'],
            'employee_salaries.*.hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'employee_salaries.*.use_basic_income_as_hourly_rate' => ['nullable', 'boolean'],
            'employee_salaries.*.incomes' => ['nullable', 'array'],
            'employee_salaries.*.incomes.*.income_type_id' => ['required_with:employee_salaries.*.incomes.*', Rule::exists('tbl_income_types', 'income_type_id')],
            'employee_salaries.*.incomes.*.taxable' => ['nullable', 'numeric', 'min:0'],
            'employee_salaries.*.incomes.*.non_taxable' => ['nullable', 'numeric', 'min:0'],
            'employee_salaries.*.deductions' => ['nullable', 'array'],
            'employee_salaries.*.deductions.*.deduction_type_id' => ['required_with:employee_salaries.*.deductions.*', Rule::exists('tbl_deduction_types', 'deduction_type_id')],
            'employee_salaries.*.deductions.*.employee_amount' => ['nullable', 'numeric', 'min:0'],
            'employee_salaries.*.deductions.*.employer_amount' => ['nullable', 'numeric', 'min:0'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'home_phone' => ['nullable', 'string', 'max:30'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'fax_number' => ['nullable', 'string', 'max:30'],
            'program' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'college' => ['nullable', 'string', 'max:150'],
            'campus_id' => ['nullable', Rule::exists('tbl_campuses', 'campus_id')],
            'campus' => ['nullable', 'string', 'max:50'],
            'campus_assignments' => ['required', 'array', 'min:1'],
            'campus_assignments.*.campus_id' => ['required', Rule::exists('tbl_campuses', 'campus_id')],
            'campus_assignments.*.biometric_id' => ['required', 'string', 'max:50'],
            'campus_assignments.*.college' => ['nullable', 'string', 'max:150'],
            'campus_assignments.*.department' => ['nullable', 'string', 'max:150'],
            'campus_assignments.*.program' => ['nullable', 'string', 'max:150'],
            'employment_status' => ['required', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'compliance_status' => ['nullable', Rule::in([
                Employee::COMPLIANCE_COMPLIANT,
                Employee::COMPLIANCE_PENDING,
                Employee::COMPLIANCE_OVERDUE,
                Employee::COMPLIANCE_WITHHELD,
            ])],
            'hire_date' => ['prohibited'],
            'birth_date' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'civil_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'separated', 'divorced'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'language_dialect' => ['nullable', 'string', 'max:100'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'tin_number' => ['nullable', 'string', 'max:30'],
            'sss_number' => ['nullable', 'string', 'max:30'],
            'philhealth_number' => ['nullable', 'string', 'max:30'],
            'pagibig_number' => ['nullable', 'string', 'max:30'],
            'gsis_number' => ['nullable', 'string', 'max:30'],
            'tax_status' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city_municipality' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'extended_profile' => ['nullable', 'array'],
            'is_confidential' => ['sometimes', 'boolean'],
        ];
    }

    protected function employeeNumberRules(?Employee $employee = null): array
    {
        return [
            'required',
            'string',
            'max:50',
            Rule::unique(Employee::class, 'employee_number')
                ->whereNull('deleted_at')
                ->ignore($employee?->employee_id, 'employee_id'),
        ];
    }

    protected function employeeValidationMessages(): array
    {
        return [
            'employee_number.required' => 'Employee number is required.',
            'employee_number.unique' => 'This employee number is already assigned to another employee.',
        ];
    }

    protected function prepareEmployeeValidation(): void
    {
        $isHybrid = $this->boolean('is_hybrid');
        $employmentInformations = EmployeeEmploymentSync::normalizeRecords(
            (array) $this->input('employment_informations', []),
            $isHybrid,
        );
        $employeeSalaries = EmployeeSalarySync::normalizeRecords(
            $this->normalizeSalaryRecords((array) $this->input('employee_salaries', [])),
            $isHybrid,
        );
        $campusAssignments = EmployeeCampusAssignmentSync::normalizeRecords(
            (array) $this->input('campus_assignments', []),
        );

        $extended = $this->normalizeExtendedProfile((array) $this->input('extended_profile', []));

        $this->merge([
            'employee_number' => trim((string) $this->input('employee_number', '')),
            'is_hybrid' => $isHybrid,
            'is_confidential' => $this->boolean('is_confidential'),
            'compliance_status' => $this->input('compliance_status', Employee::COMPLIANCE_PENDING),
            'employment_informations' => $employmentInformations,
            'employee_salaries' => $employeeSalaries,
            'campus_assignments' => $campusAssignments,
            'extended_profile' => $extended,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isHybrid = $this->boolean('is_hybrid');
            $records = (array) $this->input('employment_informations', []);
            $count = count($records);

            if ($isHybrid) {
                if ($count !== 2) {
                    $validator->errors()->add(
                        'employment_informations',
                        'Hybrid employees must have exactly two employment records.',
                    );
                }

                $types = collect($records)->pluck('user_type')->filter()->values();

                if (! $types->contains(EmployeeEmploymentInformation::TYPE_FACULTY)
                    || ! $types->contains(EmployeeEmploymentInformation::TYPE_STAFF)) {
                    $validator->errors()->add(
                        'employment_informations',
                        'Hybrid employees must include one Faculty and one Staff employment record.',
                    );
                }
            } elseif ($count !== 1) {
                $validator->errors()->add(
                    'employment_informations',
                    'Enter exactly one employment record when Hybrid is not selected.',
                );
            }

            $salaryRecords = (array) $this->input('employee_salaries', []);
            $expectedSalaries = $isHybrid ? 2 : 1;

            if (count($salaryRecords) !== $expectedSalaries) {
                $validator->errors()->add(
                    'employee_salaries',
                    $isHybrid
                        ? 'Hybrid employees must have Faculty and Staff salary records.'
                        : 'Enter exactly one salary record when Hybrid is not selected.',
                );
            }

            foreach ($salaryRecords as $index => $salary) {
                if (! is_array($salary)) {
                    continue;
                }

                $payTypeId = (int) ($salary['pay_type_id'] ?? 0);

                if (PayType::requiresDaysPerPeriodInput($payTypeId) && blank($salary['days_per_period'] ?? null)) {
                    $validator->errors()->add(
                        "employee_salaries.$index.days_per_period",
                        'Days Per Period is required for Weekly, Semi-Monthly, and Monthly pay types.',
                    );
                }
            }

            $assignments = (array) $this->input('campus_assignments', []);

            if (count($assignments) < 1) {
                $validator->errors()->add('campus_assignments', 'Add at least one campus assignment.');
            }

            $campusIds = collect($assignments)->pluck('campus_id')->filter()->map(fn ($id) => (int) $id);

            if ($campusIds->count() !== $campusIds->unique()->count()) {
                $validator->errors()->add('campus_assignments', 'Each campus may only be assigned once per employee.');
            }

            $employeeId = $this->route('employee')?->employee_id;

            foreach ($assignments as $index => $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }

                $campusId = (int) ($assignment['campus_id'] ?? 0);
                $biometricId = trim((string) ($assignment['biometric_id'] ?? ''));

                if ($campusId <= 0 || $biometricId === '') {
                    continue;
                }

                $duplicate = \App\Models\EmployeeCampusAssignment::query()
                    ->where('campus_id', $campusId)
                    ->where('biometric_id', $biometricId)
                    ->when($employeeId, fn ($query) => $query->where('employee_id', '!=', $employeeId))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        "campus_assignments.$index.biometric_id",
                        "Biometric ID {$biometricId} is already assigned to another employee at this campus.",
                    );
                }
            }
        });
    }

    protected function validatedEmployeeData(): array
    {
        $data = parent::validated();
        unset($data['employment_informations'], $data['employee_salaries'], $data['campus_assignments']);
        $data['is_active'] = ($data['employment_status'] ?? Employee::STATUS_ACTIVE) === Employee::STATUS_ACTIVE;

        if (isset($data['extended_profile']) && is_array($data['extended_profile'])) {
            $data['extended_profile'] = $this->normalizeExtendedProfile($data['extended_profile']);
        }

        return $data;
    }

    public function employmentInformations(): array
    {
        return (array) $this->input('employment_informations', []);
    }

    public function employeeSalaries(): array
    {
        return (array) $this->input('employee_salaries', []);
    }

    public function campusAssignments(): array
    {
        return (array) $this->input('campus_assignments', []);
    }

    protected function resolveEmployeeFormErrorTab(?string $fallbackTab = null): ?string
    {
        if (! $this->validator || $this->validator->errors()->isEmpty()) {
            return $fallbackTab;
        }

        foreach ($this->validator->errors()->keys() as $key) {
            $tab = $this->employeeFormTabForErrorKey($key);

            if ($tab !== null) {
                return $tab;
            }
        }

        return $fallbackTab;
    }

    protected function employeeFormTabForErrorKey(string $key): ?string
    {
        $root = explode('.', $key, 2)[0];

        return match (true) {
            in_array($root, [
                'first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'place_of_birth',
                'gender', 'civil_status', 'nationality', 'religion', 'language_dialect',
                'height_cm', 'weight_kg', 'tin_number', 'sss_number', 'philhealth_number',
                'pagibig_number', 'gsis_number', 'tax_status',
            ], true) => 'personal',
            in_array($root, ['campus_assignments', 'campus_id', 'college', 'department', 'program'], true) => 'assignment',
            in_array($root, ['employee_number', 'compliance_status', 'employment_informations', 'is_hybrid'], true) => 'employment',
            $root === 'employee_salaries' => 'salary',
            in_array($root, [
                'email', 'phone', 'home_phone', 'work_phone', 'fax_number',
                'emergency_contact_name', 'emergency_contact_relationship',
                'emergency_contact_phone', 'emergency_contact_email', 'emergency_contact_address',
            ], true) => 'contact',
            in_array($root, [
                'country', 'region', 'province', 'city_municipality', 'barangay', 'postal_code', 'address_line',
            ], true) => 'address',
            $root === 'extended_profile' => 'extended',
            in_array($root, ['employment_status', 'is_confidential'], true) => 'access',
            default => null,
        };
    }

    protected function normalizeSalaryRecords(array $records): array
    {
        return array_values(array_map(function ($record) {
            if (! is_array($record)) {
                return $record;
            }

            foreach (['nd_rate_group_id', 'days_per_period', 'hours_per_day'] as $field) {
                if (array_key_exists($field, $record) && blank($record[$field])) {
                    $record[$field] = null;
                }
            }

            $payTypeId = (int) ($record['pay_type_id'] ?? 0);

            if ($payTypeId === PayType::DAILY) {
                $record['days_per_period'] = PayType::autoDaysPerPeriod(PayType::DAILY);
            }

            $record['use_basic_income_as_hourly_rate'] = ! empty($record['use_basic_income_as_hourly_rate']);

            foreach (['incomes', 'deductions'] as $collection) {
                if (! isset($record[$collection]) || ! is_array($record[$collection])) {
                    continue;
                }

                $record[$collection] = array_values(array_map(function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }

                    foreach (['taxable', 'non_taxable', 'employee_amount', 'employer_amount'] as $amountField) {
                        if (array_key_exists($amountField, $row) && blank($row[$amountField])) {
                            $row[$amountField] = null;
                        }
                    }

                    return $row;
                }, $record[$collection]));
            }

            return $record;
        }, $records));
    }

    protected function normalizeExtendedProfile(array $profile): array
    {
        $skillsProfile = $profile['skills_profile'] ?? [];
        $skills = $skillsProfile['skills'] ?? [];

        foreach ([
            'computer' => 'computer_text',
            'technical' => 'technical_text',
            'talents' => 'talents_text',
        ] as $key => $textField) {
            if (! empty($skillsProfile[$textField])) {
                $skills[$key] = collect(explode(',', (string) $skillsProfile[$textField]))
                    ->map(fn ($value) => trim($value))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        $skillsProfile['skills'] = $skills;
        unset($skillsProfile['computer_text'], $skillsProfile['technical_text'], $skillsProfile['talents_text']);
        $profile['skills_profile'] = array_filter($skillsProfile, fn ($value) => $value !== null && $value !== '' && $value !== []);

        foreach (['family_members', 'employment_history', 'exams', 'seminars', 'awards', 'references'] as $collection) {
            if (! isset($profile[$collection]) || ! is_array($profile[$collection])) {
                continue;
            }

            $profile[$collection] = array_values(array_filter($profile[$collection], function ($row) {
                if (! is_array($row)) {
                    return false;
                }

                return collect($row)
                    ->reject(fn ($value, $key) => $key === 'relationship_type' && blank($value))
                    ->filter(fn ($value) => ! blank($value))
                    ->isNotEmpty();
            }));
        }

        if (isset($profile['family_background']) && is_array($profile['family_background'])) {
            $profile['family_background'] = array_filter($profile['family_background'], fn ($value) => ! blank($value));
        }

        if (isset($profile['general_information']) && is_array($profile['general_information'])) {
            foreach ([
                'has_physical_defect_or_disability',
                'has_major_operations_or_illness',
                'has_nervous_disorder',
                'has_relative_in_icct',
                'has_been_suspended_or_discharged',
                'has_labor_union_participation',
                'has_administrative_civil_criminal_case',
            ] as $checkbox) {
                $profile['general_information'][$checkbox] = filter_var(
                    Arr::get($profile['general_information'], $checkbox, false),
                    FILTER_VALIDATE_BOOLEAN,
                );
            }

            $profile['general_information'] = array_filter(
                $profile['general_information'],
                fn ($value) => ! blank($value) || is_bool($value),
            );
        }

        return array_filter($profile, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }
}
