<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\EmployeeEmploymentInformation;
use App\Models\IncomeType;
use App\Models\NdRateGroup;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\Role;
use App\Support\GovernmentIdNumbers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeUploadRowMapper
{
    private Collection $campusesByCode;

    private Collection $payTypesById;

    private Collection $payTypesByName;

    private Collection $basicComputationsById;

    private Collection $basicComputationsByName;

    private Collection $rateGroupsById;

    private Collection $rateGroupsByDescription;

    private Collection $ndRateGroupsById;

    private Collection $ndRateGroupsByDescription;

    private Collection $incomeTypesByCode;

    private Collection $incomeTypesById;

    private Collection $deductionTypesByCode;

    private Collection $deductionTypesById;

    private Collection $rolesById;

    private Collection $rolesBySlug;

    private Collection $rolesByName;

    private bool $lookupsLoaded = false;

    public function __construct() {}

    private function ensureLookupsLoaded(): void
    {
        if ($this->lookupsLoaded) {
            return;
        }

        $this->lookupsLoaded = true;
        $this->campusesByCode = Campus::query()->pluck('campus_id', 'campus_code');

        $payTypes = PayType::query()->get();
        $this->payTypesById = $payTypes->keyBy('pay_type_id');
        $this->payTypesByName = $payTypes->keyBy(fn ($row) => strtolower((string) $row->pay_type));

        $basicComputations = BasicComputation::query()->get();
        $this->basicComputationsById = $basicComputations->keyBy('basic_computation_id');
        $this->basicComputationsByName = $basicComputations->keyBy(fn ($row) => strtolower((string) $row->basic_computation));

        $rateGroups = RateGroup::query()->get();
        $this->rateGroupsById = $rateGroups->keyBy('rate_group_id');
        $this->rateGroupsByDescription = $rateGroups->keyBy(fn ($row) => strtolower((string) $row->description));

        $ndRateGroups = NdRateGroup::query()->get();
        $this->ndRateGroupsById = $ndRateGroups->keyBy('nd_rate_group_id');
        $this->ndRateGroupsByDescription = $ndRateGroups->keyBy(fn ($row) => strtolower((string) $row->description));

        $incomeTypes = IncomeType::query()->where('is_active', true)->get();
        $this->incomeTypesByCode = $incomeTypes->keyBy(fn ($row) => strtoupper((string) $row->income_type_code));
        $this->incomeTypesById = $incomeTypes->keyBy('income_type_id');

        $deductionTypes = DeductionType::query()->where('is_active', true)->get();
        $this->deductionTypesByCode = $deductionTypes->keyBy(fn ($row) => strtoupper((string) $row->deduction_type_code));
        $this->deductionTypesById = $deductionTypes->keyBy('deduction_type_id');

        $roles = Role::query()->get();
        $this->rolesById = $roles->keyBy('id');
        $this->rolesBySlug = $roles->keyBy(fn ($row) => strtolower((string) $row->slug));
        $this->rolesByName = $roles->keyBy(fn ($row) => strtolower((string) $row->name));
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleRowValues(): array
    {
        $aliases = array_column((array) config('employee_upload.columns', []), 'alias');
        $sample = (array) config('employee_upload.sample_row', []);

        return array_merge(
            array_fill_keys($aliases, ''),
            array_intersect_key($sample, array_flip($aliases)),
        );
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenNumbers
     * @param  array<string, bool>  $seenEmails
     * @return array{errors: array<int, string>, payload: array<string, mixed>|null}
     */
    public function mapRow(array $row, int $lineNumber, array &$seenNumbers, array &$seenEmails): array
    {
        $this->ensureLookupsLoaded();
        $row = $this->normalizeRowDates($row);
        $row = $this->normalizeGovernmentIds($row);
        $errors = $this->validateScalars($row, $lineNumber, $seenNumbers, $seenEmails);

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        $isHybrid = $this->parseBoolean($row['is_hybrid'] ?? '', false);
        $employmentErrors = $this->validateEmployment($row, $lineNumber, $isHybrid);
        $salaryErrors = $this->validateSalaries($row, $lineNumber, $isHybrid);
        $campusErrors = $this->validateCampusAssignments($row, $lineNumber);
        $roleErrors = $this->validateRole($row, $lineNumber);
        $jsonErrors = $this->validateJsonCollections($row, $lineNumber);

        $errors = array_merge($errors, $employmentErrors, $salaryErrors, $campusErrors, $roleErrors, $jsonErrors);

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        return [
            'errors' => [],
            'payload' => $this->buildPayload($row, $isHybrid),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateScalars(array $row, int $lineNumber, array &$seenNumbers, array &$seenEmails): array
    {
        $this->ensureLookupsLoaded();
        $errors = [];

        foreach ($this->requiredColumns() as $alias) {
            if (($row[$alias] ?? '') === '') {
                $errors[] = "Line {$lineNumber}: ".$this->labelFor($alias).' is required.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line {$lineNumber}: Invalid email ({$row['email']}).";
        }

        if (filled($row['emergency_contact_email'] ?? '') && ! filter_var($row['emergency_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line {$lineNumber}: Invalid emergency contact email.";
        }

        $emailKey = strtolower($row['email']);

        if (isset($seenEmails[$emailKey])) {
            $errors[] = "Line {$lineNumber}: Duplicate email in file ({$row['email']}).";
        }

        if (
            $row['employee_number'] !== ''
            && isset($seenNumbers[$row['employee_number']])
        ) {
            $errors[] = "Line {$lineNumber}: Duplicate employee number in file ({$row['employee_number']}).";
        }

        if ($row['employee_number'] !== '' && Employee::query()->where('employee_number', $row['employee_number'])->exists()) {
            $errors[] = "Line {$lineNumber}: Employee number already exists ({$row['employee_number']}).";
        }

        if (Employee::query()->where('email', $row['email'])->exists()) {
            $errors[] = "Line {$lineNumber}: Email already exists ({$row['email']}).";
        }

        if (! $this->campusesByCode->has($row['campus_code'])) {
            $errors[] = "Line {$lineNumber}: Unknown campus code ({$row['campus_code']}).";
        }

        foreach (['gender', 'civil_status'] as $field) {
            if (filled($row[$field] ?? '') && ! $this->isValidEnum($row[$field], $this->allowedValues($field))) {
                $errors[] = "Line {$lineNumber}: Invalid {$this->labelFor($field)}.";
            }
        }

        $status = strtolower($row['employment_status'] !== '' ? $row['employment_status'] : Employee::STATUS_ACTIVE);

        if (! in_array($status, [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE], true)) {
            $errors[] = "Line {$lineNumber}: Account status must be active or inactive.";
        }

        if (filled($row['compliance_status'] ?? '')) {
            $compliance = strtolower((string) $row['compliance_status']);

            if (! in_array($compliance, array_keys(Employee::selectableComplianceStatuses()), true)) {
                $errors[] = "Line {$lineNumber}: Invalid compliance status.";
            }
        }

        foreach ([
            'tin_number' => GovernmentIdNumbers::TYPE_TIN,
            'sss_number' => GovernmentIdNumbers::TYPE_SSS,
            'philhealth_number' => GovernmentIdNumbers::TYPE_PHILHEALTH,
            'pagibig_number' => GovernmentIdNumbers::TYPE_PAGIBIG,
        ] as $field => $type) {
            if (filled($row[$field] ?? '') && ! GovernmentIdNumbers::isValid($row[$field], $type)) {
                $errors[] = "Line {$lineNumber}: ".GovernmentIdNumbers::uploadErrorMessage($type, $this->labelFor($field));
            }
        }

        foreach (['birth_date', 'hire_date', 'emp2_hire_date', 'fb_date_married'] as $dateField) {
            if (filled($row[$dateField] ?? '') && ! $this->isValidDate($row[$dateField])) {
                $errors[] = "Line {$lineNumber}: Invalid date for ".$this->labelFor($dateField).'.';
            }
        }

        if ($row['employee_number'] !== '') {
            $seenNumbers[$row['employee_number']] = true;
        }

        $seenEmails[$emailKey] = true;

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateEmployment(array $row, int $lineNumber, bool $isHybrid): array
    {
        $errors = [];

        if ($isHybrid) {
            $types = [
                strtolower((string) ($row['user_type'] ?? '')),
                strtolower((string) ($row['emp2_user_type'] ?? '')),
            ];

            if (! in_array(EmployeeEmploymentInformation::TYPE_FACULTY, $types, true)
                || ! in_array(EmployeeEmploymentInformation::TYPE_STAFF, $types, true)) {
                $errors[] = "Line {$lineNumber}: Hybrid employees must include one faculty and one staff user type.";
            }
        } elseif (! $this->isValidUserType($row['user_type'] ?? '')) {
            $errors[] = "Line {$lineNumber}: User type must be faculty, staff, or admin.";
        }

        foreach (['user_type', 'emp2_user_type'] as $field) {
            if (filled($row[$field] ?? '') && ! $this->isValidUserType($row[$field])) {
                $errors[] = "Line {$lineNumber}: Invalid ".$this->labelFor($field).'.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateCampusAssignments(array $row, int $lineNumber): array
    {
        $errors = [];
        $assignments = $this->buildCampusAssignments($row, false);

        if ($assignments === []) {
            return ["Line {$lineNumber}: At least one campus assignment is required."];
        }

        $campusIds = collect($assignments)->pluck('campus_id');

        if ($campusIds->count() !== $campusIds->unique()->count()) {
            $errors[] = "Line {$lineNumber}: Each campus may only be assigned once per employee.";
        }

        foreach ($assignments as $assignment) {
            $duplicate = EmployeeCampusAssignment::query()
                ->where('campus_id', $assignment['campus_id'])
                ->where('biometric_id', $assignment['biometric_id'])
                ->exists();

            if ($duplicate) {
                $errors[] = "Line {$lineNumber}: Biometric ID {$assignment['biometric_id']} is already assigned at this campus.";
            }
        }

        if (filled($row['campus2_code'] ?? '') xor filled($row['campus2_biometric_id'] ?? '')) {
            $errors[] = "Line {$lineNumber}: Campus 2 requires both campus code and biometric ID.";
        }

        if (filled($row['campus2_code'] ?? '') && ! $this->campusesByCode->has($row['campus2_code'])) {
            $errors[] = "Line {$lineNumber}: Unknown campus code 2 ({$row['campus2_code']}).";
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateSalaries(array $row, int $lineNumber, bool $isHybrid): array
    {
        $errors = [];
        $prefixes = $isHybrid ? ['salary_', 'salary2_'] : ['salary_'];

        foreach ($prefixes as $prefix) {
            $salaryErrors = $this->validateSalaryBlock($row, $lineNumber, $prefix, $prefix === 'salary2_');

            if ($salaryErrors !== []) {
                $errors = array_merge($errors, $salaryErrors);
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateSalaryBlock(array $row, int $lineNumber, string $prefix, bool $isSecond): array
    {
        $errors = [];
        $labelSuffix = $isSecond ? ' (salary 2)' : '';
        $dateFrom = $this->salaryEffectivityFrom($row, $prefix);
        $dateTo = $this->salaryEffectivityTo($row, $prefix);
        $payTypeField = $prefix.'pay_type';
        $basicComputationField = $prefix.'basic_computation';
        $rateGroupField = $prefix.'rate_group';

        if ($dateFrom === '') {
            $errors[] = "Line {$lineNumber}: ".$this->labelFor($prefix.'date_effective_from')." is required{$labelSuffix}.";
        }

        foreach ([$payTypeField, $basicComputationField, $rateGroupField] as $field) {
            if (($row[$field] ?? '') === '') {
                $errors[] = "Line {$lineNumber}: ".$this->labelFor($field)." is required{$labelSuffix}.";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        if (! $this->isValidDate($dateFrom)) {
            $errors[] = "Line {$lineNumber}: Invalid salary effectivity from{$labelSuffix}.";
        }

        if ($dateTo !== '' && ! $this->isValidDate($dateTo)) {
            $errors[] = "Line {$lineNumber}: Invalid salary effectivity to{$labelSuffix}.";
        }

        if ($dateTo !== '' && $this->isValidDate($dateFrom) && $this->isValidDate($dateTo) && $dateTo < $dateFrom) {
            $errors[] = "Line {$lineNumber}: Salary effectivity to must be on or after effectivity from{$labelSuffix}.";
        }

        $payType = $this->resolvePayType($row[$payTypeField]);

        if ($payType === null) {
            $errors[] = "Line {$lineNumber}: Unknown pay type{$labelSuffix} ({$row[$payTypeField]}).";
        }

        if ($this->resolveBasicComputation($row[$basicComputationField]) === null) {
            $errors[] = "Line {$lineNumber}: Unknown basic computation{$labelSuffix} ({$row[$basicComputationField]}).";
        }

        if ($this->resolveRateGroup($row[$rateGroupField]) === null) {
            $errors[] = "Line {$lineNumber}: Unknown rate group{$labelSuffix} ({$row[$rateGroupField]}).";
        }

        if (filled($row[$prefix.'nd_rate_group'] ?? '') && $this->resolveNdRateGroup($row[$prefix.'nd_rate_group']) === null) {
            $errors[] = "Line {$lineNumber}: Unknown night diff. rate group{$labelSuffix}.";
        }

        if ($payType !== null && PayType::requiresDaysPerPeriodInput((int) $payType->pay_type_id) && blank($row[$prefix.'days_per_period'] ?? null)) {
            $errors[] = "Line {$lineNumber}: Days per period is required for Weekly, Semi-Monthly, and Monthly pay types{$labelSuffix}.";
        }

        $incomes = $this->parseIncomes($row, $prefix);

        if ($incomes === [] && blank($row[$prefix.'basic_taxable'] ?? null) && blank($row[$prefix.'basic_non_taxable'] ?? null)) {
            $errors[] = "Line {$lineNumber}: Provide basic income taxable/non-taxable or salary incomes{$labelSuffix}.";
        }

        foreach ($this->parseIncomeTokens($row[$prefix.'incomes'] ?? '') as $token) {
            if ($this->resolveIncomeType($token['code']) === null) {
                $errors[] = "Line {$lineNumber}: Unknown income type code {$token['code']}{$labelSuffix}.";
            }
        }

        foreach ($this->parseDeductionTokens($row[$prefix.'deductions'] ?? '') as $token) {
            if ($this->resolveDeductionType($token['code']) === null) {
                $errors[] = "Line {$lineNumber}: Unknown deduction type code {$token['code']}{$labelSuffix}.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateRole(array $row, int $lineNumber): array
    {
        if ($this->resolveRoleId($row['role'] ?? '') === null) {
            return ["Line {$lineNumber}: Unknown system role ({$row['role']})."];
        }

        return [];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateJsonCollections(array $row, int $lineNumber): array
    {
        $errors = [];

        foreach ((array) config('employee_upload.json_collection_map', []) as $alias => $collection) {
            $raw = trim((string) ($row[$alias] ?? ''));

            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                $errors[] = "Line {$lineNumber}: {$this->labelFor($alias)} must be a valid JSON array.";

                continue;
            }

            if (! array_is_list($decoded)) {
                $errors[] = "Line {$lineNumber}: {$this->labelFor($alias)} must be a JSON array.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function buildPayload(array $row, bool $isHybrid): array
    {
        $status = strtolower($row['employment_status'] !== '' ? $row['employment_status'] : Employee::STATUS_ACTIVE);
        $campusId = (int) $this->campusesByCode->get($row['campus_code']);
        $employeeNumber = filled($row['employee_number'] ?? null)
            ? trim((string) $row['employee_number'])
            : Employee::generateEmployeeNumber();

        $employee = [
            'employee_number' => $employeeNumber,
            'first_name' => $row['first_name'],
            'middle_name' => $this->nullable($row['middle_name'] ?? ''),
            'last_name' => $row['last_name'],
            'suffix' => $this->nullable($row['suffix'] ?? ''),
            'is_hybrid' => $isHybrid,
            'email' => $row['email'],
            'phone' => $row['phone'],
            'home_phone' => $this->nullable($row['home_phone'] ?? ''),
            'work_phone' => $this->nullable($row['work_phone'] ?? ''),
            'fax_number' => $this->nullable($row['fax_number'] ?? ''),
            'program' => $this->nullable($row['program'] ?? ''),
            'department' => $this->nullable($row['department'] ?? ''),
            'college' => $this->nullable($row['college'] ?? ''),
            'campus_id' => $campusId,
            'campus' => $row['campus_code'],
            'employment_status' => $status,
            'compliance_status' => strtolower($row['compliance_status'] !== ''
                ? $row['compliance_status']
                : Employee::COMPLIANCE_PENDING),
            'is_active' => $status === Employee::STATUS_ACTIVE,
            'birth_date' => $this->nullable($row['birth_date'] ?? ''),
            'place_of_birth' => $this->nullable($row['place_of_birth'] ?? ''),
            'gender' => $this->nullable(strtolower($row['gender'] ?? '')),
            'civil_status' => $this->nullable(strtolower($row['civil_status'] ?? '')),
            'nationality' => $this->nullable($row['nationality'] ?? ''),
            'religion' => $this->nullable($row['religion'] ?? ''),
            'language_dialect' => $this->nullable($row['language_dialect'] ?? ''),
            'height_cm' => $this->nullableNumeric($row['height_cm'] ?? ''),
            'weight_kg' => $this->nullableNumeric($row['weight_kg'] ?? ''),
            'tin_number' => $this->nullable($row['tin_number'] ?? ''),
            'sss_number' => $this->nullable($row['sss_number'] ?? ''),
            'philhealth_number' => $this->nullable($row['philhealth_number'] ?? ''),
            'pagibig_number' => $this->nullable($row['pagibig_number'] ?? ''),
            'gsis_number' => $this->nullable($row['gsis_number'] ?? ''),
            'tax_status' => $this->nullable($row['tax_status'] ?? ''),
            'emergency_contact_name' => $this->nullable($row['emergency_contact_name'] ?? ''),
            'emergency_contact_relationship' => $this->nullable($row['emergency_contact_relationship'] ?? ''),
            'emergency_contact_phone' => $this->nullable($row['emergency_contact_phone'] ?? ''),
            'emergency_contact_email' => $this->nullable($row['emergency_contact_email'] ?? ''),
            'emergency_contact_address' => $this->nullable($row['emergency_contact_address'] ?? ''),
            'address_line' => $this->nullable($row['address_line'] ?? ''),
            'country' => $this->nullable($row['country'] ?? '') ?: 'Philippines',
            'region' => $this->nullable($row['region'] ?? ''),
            'province' => $this->nullable($row['province'] ?? ''),
            'city_municipality' => $this->nullable($row['city_municipality'] ?? ''),
            'barangay' => $this->nullable($row['barangay'] ?? ''),
            'postal_code' => $this->nullable($row['postal_code'] ?? ''),
            'is_confidential' => $this->parseBoolean($row['is_confidential'] ?? '', false),
            'extended_profile' => $this->buildExtendedProfile($row),
        ];

        $employmentInformations = $this->buildEmploymentInformations($row, $isHybrid);
        $campusAssignments = $this->buildCampusAssignments($row, true);
        $employeeSalaries = $this->buildSalaries($row, $isHybrid);

        return [
            'employee' => $employee,
            'employment_informations' => EmployeeEmploymentSync::normalizeRecords($employmentInformations, $isHybrid),
            'campus_assignments' => EmployeeCampusAssignmentSync::normalizeRecords($campusAssignments),
            'employee_salaries' => EmployeeSalarySync::normalizeRecords($employeeSalaries, $isHybrid),
            'is_hybrid' => $isHybrid,
            'preview' => [
                'employee_number' => $employeeNumber,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'campus_code' => $row['campus_code'],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function buildExtendedProfile(array $row): array
    {
        $profile = [];

        $familyBackground = [];

        foreach ((array) config('employee_upload.family_background_map', []) as $alias => $field) {
            $value = trim((string) ($row[$alias] ?? ''));

            if ($value !== '') {
                $familyBackground[$field] = $field === 'number_of_children'
                    ? (int) $value
                    : $value;
            }
        }

        if ($familyBackground !== []) {
            $profile['family_background'] = $familyBackground;
        }

        $generalInformation = [];

        foreach ((array) config('employee_upload.general_information_map', []) as $alias => $field) {
            $value = trim((string) ($row[$alias] ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($field, 'has_')) {
                $generalInformation[$field] = $this->parseBoolean($value, false);
            } else {
                $generalInformation[$field] = $value;
            }
        }

        if ($generalInformation !== []) {
            $profile['general_information'] = $generalInformation;
        }

        $skillsProfile = [];

        foreach ([
            'sk_computer' => 'computer',
            'sk_technical' => 'technical',
            'sk_talents' => 'talents',
        ] as $alias => $key) {
            $value = trim((string) ($row[$alias] ?? ''));

            if ($value !== '') {
                $skillsProfile['skills'][$key] = collect(explode(',', $value))
                    ->map(fn ($item) => trim($item))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        if (filled($row['sk_other_skills'] ?? '')) {
            $skillsProfile['other_skills'] = trim((string) $row['sk_other_skills']);
        }

        if ($skillsProfile !== []) {
            $profile['skills_profile'] = $skillsProfile;
        }

        foreach ((array) config('employee_upload.json_collection_map', []) as $alias => $collection) {
            $raw = trim((string) ($row[$alias] ?? ''));

            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (is_array($decoded) && $decoded !== []) {
                $profile[$collection] = $decoded;
            }
        }

        $roleId = $this->resolveRoleId($row['role'] ?? '');

        if ($roleId !== null) {
            $profile['role_id'] = $roleId;
        }

        return $profile;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, array<string, mixed>>
     */
    private function buildEmploymentInformations(array $row, bool $isHybrid): array
    {
        $primary = [
            'user_type' => strtolower((string) $row['user_type']),
            'position' => $this->nullable($row['position'] ?? ''),
            'designation' => $this->nullable($row['designation'] ?? ''),
            'rank' => $this->nullable($row['rank'] ?? ''),
            'employment_type' => $this->nullable($row['employment_type'] ?? ''),
            'hire_date' => $this->nullable($row['hire_date'] ?? ''),
        ];

        if (! $isHybrid) {
            return [$primary];
        }

        $secondary = [
            'user_type' => strtolower((string) ($row['emp2_user_type'] ?: EmployeeEmploymentInformation::TYPE_STAFF)),
            'position' => $this->nullable($row['emp2_position'] ?? ''),
            'designation' => $this->nullable($row['emp2_designation'] ?? ''),
            'rank' => $this->nullable($row['emp2_rank'] ?? ''),
            'employment_type' => $this->nullable($row['emp2_employment_type'] ?? ''),
            'hire_date' => $this->nullable($row['emp2_hire_date'] ?? ''),
        ];

        return [$primary, $secondary];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, array<string, mixed>>
     */
    private function buildCampusAssignments(array $row, bool $validatePrimary): array
    {
        $assignments = [];

        if ($validatePrimary || (filled($row['campus_code'] ?? '') && filled($row['biometric_id'] ?? ''))) {
            $assignments[] = [
                'campus_id' => (int) $this->campusesByCode->get($row['campus_code']),
                'biometric_id' => trim((string) $row['biometric_id']),
                'college' => $this->nullable($row['college'] ?? ''),
                'department' => $this->nullable($row['department'] ?? ''),
                'program' => $this->nullable($row['program'] ?? ''),
            ];
        }

        if (filled($row['campus2_code'] ?? '') && filled($row['campus2_biometric_id'] ?? '')) {
            $assignments[] = [
                'campus_id' => (int) $this->campusesByCode->get($row['campus2_code']),
                'biometric_id' => trim((string) $row['campus2_biometric_id']),
                'college' => $this->nullable($row['campus2_college'] ?? ''),
                'department' => $this->nullable($row['campus2_department'] ?? ''),
                'program' => $this->nullable($row['campus2_program'] ?? ''),
            ];
        }

        return $assignments;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, array<string, mixed>>
     */
    private function buildSalaries(array $row, bool $isHybrid): array
    {
        $salaries = [$this->buildSalaryBlock($row, 'salary_', 0)];

        if ($isHybrid) {
            $salaries[] = $this->buildSalaryBlock($row, 'salary2_', 1);
        }

        return $salaries;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function buildSalaryBlock(array $row, string $prefix, int $employmentIndex): array
    {
        $payType = $this->resolvePayType($row[$prefix.'pay_type']);
        $payTypeId = (int) $payType?->pay_type_id;
        $daysPerPeriod = filled($row[$prefix.'days_per_period'] ?? '')
            ? (float) $row[$prefix.'days_per_period']
            : PayType::autoDaysPerPeriod($payTypeId);

        return [
            'employment_index' => $employmentIndex,
            'date_effective_from' => $this->salaryEffectivityFrom($row, $prefix),
            'date_effective_to' => filled($this->salaryEffectivityTo($row, $prefix))
                ? $this->salaryEffectivityTo($row, $prefix)
                : null,
            'basic_computation_id' => $this->resolveBasicComputation($row[$prefix.'basic_computation'])?->basic_computation_id,
            'pay_type_id' => $payTypeId,
            'rate_group_id' => $this->resolveRateGroup($row[$prefix.'rate_group'])?->rate_group_id,
            'nd_rate_group_id' => filled($row[$prefix.'nd_rate_group'] ?? '')
                ? $this->resolveNdRateGroup($row[$prefix.'nd_rate_group'])?->nd_rate_group_id
                : null,
            'days_per_period' => $daysPerPeriod,
            'hours_per_day' => filled($row[$prefix.'hours_per_day'] ?? '')
                ? (float) $row[$prefix.'hours_per_day']
                : 8.0,
            'use_basic_income_as_hourly_rate' => $this->parseBoolean($row[$prefix.'use_basic_income_as_hourly_rate'] ?? '', false),
            'incomes' => $this->parseIncomes($row, $prefix),
            'deductions' => $this->parseDeductions($row, $prefix),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, array<string, mixed>>
     */
    private function parseIncomes(array $row, string $prefix): array
    {
        $incomes = [];

        foreach ($this->parseIncomeTokens($row[$prefix.'incomes'] ?? '') as $token) {
            $incomeType = $this->resolveIncomeType($token['code']);

            if ($incomeType === null) {
                continue;
            }

            $incomes[] = [
                'income_type_id' => $incomeType->income_type_id,
                'taxable' => $token['taxable'],
                'non_taxable' => $token['non_taxable'],
            ];
        }

        if ($incomes === [] && (filled($row[$prefix.'basic_taxable'] ?? '') || filled($row[$prefix.'basic_non_taxable'] ?? ''))) {
            $basicIncome = $this->incomeTypesByCode->get('BASC') ?? $this->incomeTypesById->first();

            if ($basicIncome !== null) {
                $incomes[] = [
                    'income_type_id' => $basicIncome->income_type_id,
                    'taxable' => filled($row[$prefix.'basic_taxable'] ?? '') ? (float) $row[$prefix.'basic_taxable'] : 0,
                    'non_taxable' => filled($row[$prefix.'basic_non_taxable'] ?? '') ? (float) $row[$prefix.'basic_non_taxable'] : 0,
                ];
            }
        }

        return $incomes;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, array<string, mixed>>
     */
    private function parseDeductions(array $row, string $prefix): array
    {
        $deductions = [];

        foreach ($this->parseDeductionTokens($row[$prefix.'deductions'] ?? '') as $token) {
            $deductionType = $this->resolveDeductionType($token['code']);

            if ($deductionType === null) {
                continue;
            }

            $deductions[] = [
                'deduction_type_id' => $deductionType->deduction_type_id,
                'employee_amount' => $token['employee_amount'],
                'employer_amount' => $token['employer_amount'],
            ];
        }

        return $deductions;
    }

    /**
     * @return array<int, array{code: string, taxable: float, non_taxable: float}>
     */
    private function parseIncomeTokens(string $raw): array
    {
        $tokens = [];

        foreach (array_filter(array_map('trim', explode(';', $raw))) as $segment) {
            $parts = array_map('trim', explode('|', $segment));

            if (count($parts) < 1 || $parts[0] === '') {
                continue;
            }

            $tokens[] = [
                'code' => strtoupper($parts[0]),
                'taxable' => isset($parts[1]) && $parts[1] !== '' ? (float) $parts[1] : 0,
                'non_taxable' => isset($parts[2]) && $parts[2] !== '' ? (float) $parts[2] : 0,
            ];
        }

        return $tokens;
    }

    /**
     * @return array<int, array{code: string, employee_amount: float, employer_amount: float}>
     */
    private function parseDeductionTokens(string $raw): array
    {
        $tokens = [];

        foreach (array_filter(array_map('trim', explode(';', $raw))) as $segment) {
            $parts = array_map('trim', explode('|', $segment));

            if (count($parts) < 1 || $parts[0] === '') {
                continue;
            }

            $tokens[] = [
                'code' => strtoupper($parts[0]),
                'employee_amount' => isset($parts[1]) && $parts[1] !== '' ? (float) $parts[1] : 0,
                'employer_amount' => isset($parts[2]) && $parts[2] !== '' ? (float) $parts[2] : 0,
            ];
        }

        return $tokens;
    }

    private function resolvePayType(string $value): ?PayType
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->payTypesById->get((int) $value);
        }

        return $this->payTypesByName->get(strtolower($value));
    }

    private function resolveBasicComputation(string $value): ?BasicComputation
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->basicComputationsById->get((int) $value);
        }

        return $this->basicComputationsByName->get(strtolower($value));
    }

    private function resolveRateGroup(string $value): ?RateGroup
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->rateGroupsById->get((int) $value);
        }

        return $this->rateGroupsByDescription->get(strtolower($value));
    }

    private function resolveNdRateGroup(string $value): ?NdRateGroup
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->ndRateGroupsById->get((int) $value);
        }

        return $this->ndRateGroupsByDescription->get(strtolower($value));
    }

    private function resolveIncomeType(string $value): ?IncomeType
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->incomeTypesById->get((int) $value);
        }

        return $this->incomeTypesByCode->get(strtoupper($value));
    }

    private function resolveDeductionType(string $value): ?DeductionType
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->deductionTypesById->get((int) $value);
        }

        return $this->deductionTypesByCode->get(strtoupper($value));
    }

    private function resolveRoleId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $this->rolesById->has((int) $value) ? (int) $value : null;
        }

        $lower = strtolower($value);

        if ($this->rolesBySlug->has($lower)) {
            return (int) $this->rolesBySlug->get($lower)->id;
        }

        if ($this->rolesByName->has($lower)) {
            return (int) $this->rolesByName->get($lower)->id;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function requiredColumns(): array
    {
        return collect((array) config('employee_upload.columns', []))
            ->filter(fn ($column) => ! empty($column['required']))
            ->pluck('alias')
            ->all();
    }

    private function labelFor(string $alias): string
    {
        $column = collect((array) config('employee_upload.columns', []))
            ->firstWhere('alias', $alias);

        return is_array($column) ? (string) ($column['label'] ?? $alias) : $alias;
    }

    /**
     * @return array<int, string>
     */
    private function allowedValues(string $field): array
    {
        return match ($field) {
            'gender' => ['male', 'female', 'other'],
            'civil_status' => ['single', 'married', 'widowed', 'separated', 'divorced'],
            default => [],
        };
    }

    private function isValidEnum(string $value, array $allowed): bool
    {
        return in_array(strtolower(trim($value)), $allowed, true);
    }

    private function isValidUserType(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            EmployeeEmploymentInformation::TYPE_FACULTY,
            EmployeeEmploymentInformation::TYPE_STAFF,
            EmployeeEmploymentInformation::TYPE_ADMIN,
        ], true);
    }

    private function isValidDate(string $value): bool
    {
        return $this->normalizeDate($value) !== null;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeGovernmentIds(array $row): array
    {
        foreach ([
            'tin_number' => GovernmentIdNumbers::TYPE_TIN,
            'sss_number' => GovernmentIdNumbers::TYPE_SSS,
            'philhealth_number' => GovernmentIdNumbers::TYPE_PHILHEALTH,
            'pagibig_number' => GovernmentIdNumbers::TYPE_PAGIBIG,
        ] as $field => $type) {
            if (! filled($row[$field] ?? '')) {
                continue;
            }

            $row[$field] = GovernmentIdNumbers::normalize($row[$field]) ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeRowDates(array $row): array
    {
        foreach ($this->dateFields() as $field) {
            if (! filled($row[$field] ?? '')) {
                continue;
            }

            $normalized = $this->normalizeDate((string) $row[$field]);

            if ($normalized !== null) {
                $row[$field] = $normalized;
            }
        }

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function dateFields(): array
    {
        return [
            'birth_date',
            'hire_date',
            'emp2_hire_date',
            'salary_date_effective_from',
            'salary_date_effective_to',
            'salary2_date_effective_from',
            'salary2_date_effective_to',
            'salary_date_effective',
            'salary2_date_effective',
            'fb_date_married',
        ];
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim(ltrim(trim($value), "'"));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            $serial = (float) $value;

            if ($serial >= 1 && $serial <= 2958465) {
                try {
                    return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (\Throwable) {
                    // Fall through to Carbon parsing.
                }
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function salaryEffectivityFrom(array $row, string $prefix): string
    {
        return trim((string) ($row[$prefix.'date_effective_from']
            ?? $row[$prefix.'date_effective']
            ?? ''));
    }

    /**
     * @param  array<string, string>  $row
     */
    private function salaryEffectivityTo(array $row, string $prefix): string
    {
        return trim((string) ($row[$prefix.'date_effective_to'] ?? ''));
    }

    private function parseBoolean(string $value, bool $default): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    private function nullable(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableNumeric(string $value): ?float
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : (float) $trimmed;
    }
}
