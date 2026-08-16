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
    /**
     * Maximum campus assignment columns in the master-file upload (1 primary + up to 4 optional).
     */
    public const MAX_CAMPUS_ASSIGNMENTS = 5;

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
    public function mapRow(
        array $row,
        int $lineNumber,
        array &$seenNumbers,
        array &$seenEmails,
        bool $disableRequiredFields = false,
    ): array {
        $this->ensureLookupsLoaded();
        $row = $this->normalizeRowDates($row);
        $row = $this->normalizeGovernmentIds($row);
        $match = $this->resolveEmployeeMatch($row, $lineNumber, $seenNumbers, $seenEmails);
        $errors = $match['errors'];

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        $existingEmployeeId = $match['existing_employee_id'];
        $errors = array_merge(
            $errors,
            $this->validateScalars($row, $lineNumber, $disableRequiredFields, $existingEmployeeId),
        );

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        $isHybrid = $this->parseBoolean($row['is_hybrid'] ?? '', false);
        $hasEmploymentData = $this->rowHasEmploymentData($row);
        $hasCampusData = $this->rowHasCampusData($row);
        $hasSalaryData = $this->rowHasSalaryData($row, $isHybrid);
        $hasRoleData = filled($row['role'] ?? '');

        if (! $disableRequiredFields || $hasEmploymentData) {
            $errors = array_merge($errors, $this->validateEmployment($row, $lineNumber, $isHybrid, $disableRequiredFields));
        }

        if (! $disableRequiredFields || $hasSalaryData) {
            $errors = array_merge($errors, $this->validateSalaries($row, $lineNumber, $isHybrid, $disableRequiredFields));
        }

        if (! $disableRequiredFields || $hasCampusData) {
            $errors = array_merge($errors, $this->validateCampusAssignments($row, $lineNumber, $disableRequiredFields, $existingEmployeeId));
        }

        if ((! $disableRequiredFields || $hasRoleData) && $hasRoleData) {
            $errors = array_merge($errors, $this->validateRole($row, $lineNumber));
        } elseif (! $disableRequiredFields) {
            $errors = array_merge($errors, $this->validateRole($row, $lineNumber));
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        return [
            'errors' => [],
            'payload' => $this->buildPayload(
                $row,
                $isHybrid,
                $disableRequiredFields,
                $existingEmployeeId,
                $hasEmploymentData,
                $hasCampusData,
                $hasSalaryData,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenNumbers
     * @param  array<string, bool>  $seenEmails
     * @return array{errors: array<int, string>, existing_employee_id: int|null}
     */
    private function resolveEmployeeMatch(
        array $row,
        int $lineNumber,
        array &$seenNumbers,
        array &$seenEmails,
    ): array {
        $errors = [];
        $employeeNumber = trim((string) ($row['employee_number'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));

        if ($employeeNumber === '') {
            $errors[] = "Line {$lineNumber}: Employee Number is required.";
        }

        if ($email === '') {
            $errors[] = "Line {$lineNumber}: Email is required.";
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line {$lineNumber}: Invalid email ({$email}).";
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'existing_employee_id' => null];
        }

        $emailKey = strtolower($email);

        if (isset($seenEmails[$emailKey])) {
            $errors[] = "Line {$lineNumber}: Duplicate email in file ({$email}).";
        }

        if (isset($seenNumbers[$employeeNumber])) {
            $errors[] = "Line {$lineNumber}: Duplicate employee number in file ({$employeeNumber}).";
        }

        $matched = Employee::query()
            ->where('employee_number', $employeeNumber)
            ->whereRaw('LOWER(email) = ?', [$emailKey])
            ->first();

        $byNumber = Employee::query()
            ->where('employee_number', $employeeNumber)
            ->first();

        $byEmail = Employee::query()
            ->whereRaw('LOWER(email) = ?', [$emailKey])
            ->first();

        if ($matched === null) {
            if ($byNumber !== null) {
                $errors[] = "Line {$lineNumber}: Employee number already exists with a different email ({$employeeNumber}).";
            }

            if ($byEmail !== null) {
                $errors[] = "Line {$lineNumber}: Email already exists on another employee ({$email}).";
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'existing_employee_id' => null];
        }

        $seenNumbers[$employeeNumber] = true;
        $seenEmails[$emailKey] = true;

        return [
            'errors' => [],
            'existing_employee_id' => $matched?->employee_id,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateScalars(
        array $row,
        int $lineNumber,
        bool $disableRequiredFields = false,
        ?int $existingEmployeeId = null,
    ): array {
        $this->ensureLookupsLoaded();
        $errors = [];

        if (! $disableRequiredFields) {
            foreach ($this->requiredColumns() as $alias) {
                // employee_number + email already enforced in resolveEmployeeMatch()
                if (in_array($alias, ['employee_number', 'email'], true)) {
                    continue;
                }

                if (($row[$alias] ?? '') === '') {
                    $errors[] = "Line {$lineNumber}: ".$this->labelFor($alias).' is required.';
                }
            }

            if ($errors !== []) {
                return $errors;
            }
        }

        if (filled($row['emergency_contact_email'] ?? '') && ! filter_var($row['emergency_contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line {$lineNumber}: Invalid emergency contact email.";
        }

        if (filled($row['campus_code'] ?? '') && ! $this->campusesByCode->has($row['campus_code'])) {
            $errors[] = "Line {$lineNumber}: Unknown campus code ({$row['campus_code']}).";
        }

        foreach (['gender', 'civil_status'] as $field) {
            if (filled($row[$field] ?? '') && ! $this->isValidEnum($row[$field], $this->allowedValues($field))) {
                $errors[] = "Line {$lineNumber}: Invalid {$this->labelFor($field)}.";
            }
        }

        if (filled($row['employment_status'] ?? '')) {
            $status = strtolower((string) $row['employment_status']);

            if (! in_array($status, [Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE], true)) {
                $errors[] = "Line {$lineNumber}: Account status must be active or inactive.";
            }
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

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateEmployment(
        array $row,
        int $lineNumber,
        bool $isHybrid,
        bool $disableRequiredFields = false,
    ): array {
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
        } elseif (! $disableRequiredFields || filled($row['user_type'] ?? '')) {
            if (! $this->isValidUserType($row['user_type'] ?? '')) {
                $errors[] = "Line {$lineNumber}: User type must be faculty, staff, or admin.";
            }
        } elseif ($disableRequiredFields && $this->rowHasEmploymentData($row) && blank($row['user_type'] ?? '')) {
            $errors[] = "Line {$lineNumber}: User type is required when employment fields are provided.";
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
    private function validateCampusAssignments(
        array $row,
        int $lineNumber,
        bool $disableRequiredFields = false,
        ?int $existingEmployeeId = null,
    ): array {
        $errors = [];
        $assignments = $this->buildCampusAssignments($row, false);

        $primaryCode = trim((string) ($row['campus_code'] ?? ''));
        $primaryBiometric = trim((string) ($row['biometric_id'] ?? ''));

        if (($primaryCode !== '') xor ($primaryBiometric !== '')) {
            $errors[] = "Line {$lineNumber}: Campus requires both campus code and biometric ID.";
        }

        if ($assignments === [] && ! $disableRequiredFields) {
            $errors[] = "Line {$lineNumber}: At least one campus assignment is required.";
        }

        if ($assignments !== []) {
            $campusIds = collect($assignments)->pluck('campus_id');

            if ($campusIds->count() !== $campusIds->unique()->count()) {
                $errors[] = "Line {$lineNumber}: Each campus may only be assigned once per employee.";
            }

            foreach ($assignments as $assignment) {
                $duplicateQuery = EmployeeCampusAssignment::query()
                    ->where('campus_id', $assignment['campus_id'])
                    ->where('biometric_id', $assignment['biometric_id']);

                if ($existingEmployeeId !== null) {
                    $duplicateQuery->where('employee_id', '!=', $existingEmployeeId);
                }

                if ($duplicateQuery->exists()) {
                    $errors[] = "Line {$lineNumber}: Biometric ID {$assignment['biometric_id']} is already assigned at this campus.";
                }
            }
        }

        foreach ($this->optionalCampusSlotNumbers() as $slot) {
            $prefix = $this->campusSlotPrefix($slot);
            $code = trim((string) ($row[$prefix.'code'] ?? ''));
            $biometric = trim((string) ($row[$prefix.'biometric_id'] ?? ''));
            $label = "Campus {$slot}";

            if (($code !== '') xor ($biometric !== '')) {
                $errors[] = "Line {$lineNumber}: {$label} requires both campus code and biometric ID.";
            }

            if ($code !== '' && ! $this->campusesByCode->has($code)) {
                $errors[] = "Line {$lineNumber}: Unknown campus code {$slot} ({$code}).";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function validateSalaries(
        array $row,
        int $lineNumber,
        bool $isHybrid,
        bool $disableRequiredFields = false,
    ): array {
        $errors = [];
        $prefixes = $isHybrid ? ['salary_', 'salary2_'] : ['salary_'];

        foreach ($prefixes as $prefix) {
            if ($disableRequiredFields && ! $this->salaryBlockHasAnyValue($row, $prefix)) {
                continue;
            }

            $salaryErrors = $this->validateSalaryBlock(
                $row,
                $lineNumber,
                $prefix,
                $prefix === 'salary2_',
                $disableRequiredFields,
            );

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
    private function validateSalaryBlock(
        array $row,
        int $lineNumber,
        string $prefix,
        bool $isSecond,
        bool $disableRequiredFields = false,
    ): array {
        $errors = [];
        $labelSuffix = $isSecond ? ' (salary 2)' : '';
        $dateFrom = $this->salaryEffectivityFrom($row, $prefix);
        $dateTo = $this->salaryEffectivityTo($row, $prefix);
        $payTypeField = $prefix.'pay_type';
        $basicComputationField = $prefix.'basic_computation';
        $rateGroupField = $prefix.'rate_group';

        // Even with "disable required", a salary block that has any value must be complete.
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

        $payType = $this->resolvePayType((string) ($row[$payTypeField] ?? ''));

        if ($payType === null) {
            $errors[] = "Line {$lineNumber}: Unknown pay type{$labelSuffix} ({$row[$payTypeField]}).";
        }

        if ($this->resolveBasicComputation((string) ($row[$basicComputationField] ?? '')) === null) {
            $errors[] = "Line {$lineNumber}: Unknown basic computation{$labelSuffix} ({$row[$basicComputationField]}).";
        }

        if ($this->resolveRateGroup((string) ($row[$rateGroupField] ?? '')) === null) {
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
     * @return array<string, mixed>
     */
    private function buildPayload(
        array $row,
        bool $isHybrid,
        bool $disableRequiredFields = false,
        ?int $existingEmployeeId = null,
        bool $hasEmploymentData = true,
        bool $hasCampusData = true,
        bool $hasSalaryData = true,
    ): array {
        $status = strtolower(
            filled($row['employment_status'] ?? '')
                ? (string) $row['employment_status']
                : Employee::STATUS_ACTIVE
        );
        $campusCode = trim((string) ($row['campus_code'] ?? ''));
        $campusId = $campusCode !== '' && $this->campusesByCode->has($campusCode)
            ? (int) $this->campusesByCode->get($campusCode)
            : null;
        $employeeNumber = filled($row['employee_number'] ?? null)
            ? trim((string) $row['employee_number'])
            : Employee::generateEmployeeNumber();
        $isUpdate = $existingEmployeeId !== null;
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));

        $employee = [
            'employee_number' => $employeeNumber,
            'first_name' => $firstName !== '' ? $firstName : ($isUpdate ? null : ''),
            'middle_name' => $this->nullable($row['middle_name'] ?? ''),
            'last_name' => $lastName !== '' ? $lastName : ($isUpdate ? null : ''),
            'suffix' => $this->nullable($row['suffix'] ?? ''),
            'is_hybrid' => filled($row['is_hybrid'] ?? '') ? $isHybrid : ($isUpdate ? null : false),
            'email' => $email,
            'phone' => $phone !== '' ? $phone : ($isUpdate ? null : ''),
            'home_phone' => $this->nullable($row['home_phone'] ?? ''),
            'work_phone' => $this->nullable($row['work_phone'] ?? ''),
            'fax_number' => $this->nullable($row['fax_number'] ?? ''),
            'program' => $this->nullable($row['program'] ?? ''),
            'department' => $this->nullable($row['department'] ?? ''),
            'college' => $this->nullable($row['college'] ?? ''),
            'campus_id' => $campusId,
            'campus' => $campusCode !== '' ? $campusCode : null,
            'employment_status' => filled($row['employment_status'] ?? '') ? $status : ($isUpdate ? null : Employee::STATUS_ACTIVE),
            'compliance_status' => filled($row['compliance_status'] ?? '')
                ? strtolower((string) $row['compliance_status'])
                : ($isUpdate ? null : Employee::COMPLIANCE_PENDING),
            'is_active' => filled($row['employment_status'] ?? '')
                ? ($status === Employee::STATUS_ACTIVE)
                : ($isUpdate ? null : true),
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
            'country' => filled($row['country'] ?? '') ? trim((string) $row['country']) : ($isUpdate ? null : 'Philippines'),
            'region' => $this->nullable($row['region'] ?? ''),
            'province' => $this->nullable($row['province'] ?? ''),
            'city_municipality' => $this->nullable($row['city_municipality'] ?? ''),
            'barangay' => $this->nullable($row['barangay'] ?? ''),
            'postal_code' => $this->nullable($row['postal_code'] ?? ''),
            'is_confidential' => filled($row['is_confidential'] ?? '')
                ? $this->parseBoolean($row['is_confidential'], false)
                : ($isUpdate ? null : false),
            'extended_profile' => $this->buildExtendedProfile($row),
        ];

        if ($disableRequiredFields || $isUpdate) {
            $employee = array_filter(
                $employee,
                function ($value, $key) {
                    if (in_array($key, ['employee_number', 'email'], true)) {
                        return true;
                    }

                    return $value !== null && $value !== '';
                },
                ARRAY_FILTER_USE_BOTH,
            );

            // Keep explicit empty strings for NOT NULL create fields when creating.
            if (! $isUpdate) {
                $employee['first_name'] = $employee['first_name'] ?? '';
                $employee['last_name'] = $employee['last_name'] ?? '';
                $employee['employment_status'] = $employee['employment_status'] ?? Employee::STATUS_ACTIVE;
                $employee['compliance_status'] = $employee['compliance_status'] ?? Employee::COMPLIANCE_PENDING;
                $employee['is_active'] = $employee['is_active'] ?? true;
                $employee['is_hybrid'] = $employee['is_hybrid'] ?? false;
                $employee['is_confidential'] = $employee['is_confidential'] ?? false;
                $employee['country'] = $employee['country'] ?? 'Philippines';
            }

            // Drop empty extended_profile so we do not wipe existing profile on update.
            if (($employee['extended_profile'] ?? null) === [] || ($employee['extended_profile'] ?? null) === null) {
                unset($employee['extended_profile']);
            }
        }

        $syncEmployment = ! $disableRequiredFields || $hasEmploymentData;
        $syncCampus = ! $disableRequiredFields || $hasCampusData;
        $syncSalary = ! $disableRequiredFields || $hasSalaryData;

        $employmentInformations = $syncEmployment
            ? EmployeeEmploymentSync::normalizeRecords($this->buildEmploymentInformations($row, $isHybrid), $isHybrid)
            : [];
        $campusAssignments = $syncCampus
            ? EmployeeCampusAssignmentSync::normalizeRecords($this->buildCampusAssignments($row, ! $disableRequiredFields))
            : [];
        $employeeSalaries = $syncSalary
            ? EmployeeSalarySync::normalizeRecords($this->buildSalaries($row, $isHybrid), $isHybrid)
            : [];

        return [
            'employee' => $employee,
            'employment_informations' => $employmentInformations,
            'campus_assignments' => $campusAssignments,
            'employee_salaries' => $employeeSalaries,
            'is_hybrid' => $isHybrid,
            'existing_employee_id' => $existingEmployeeId,
            'disable_required_fields' => $disableRequiredFields,
            'sync_employment' => $syncEmployment && $employmentInformations !== [],
            'sync_campus' => $syncCampus && $campusAssignments !== [],
            'sync_salary' => $syncSalary && $employeeSalaries !== [],
            'preview' => [
                'employee_number' => $employeeNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'campus_code' => $campusCode,
                'action' => $isUpdate ? 'Update' : 'Create',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowHasEmploymentData(array $row): bool
    {
        foreach ([
            'user_type', 'position', 'designation', 'rank', 'employment_type', 'hire_date', 'is_hybrid',
            'emp2_user_type', 'emp2_position', 'emp2_designation', 'emp2_rank', 'emp2_employment_type', 'emp2_hire_date',
        ] as $field) {
            if (filled($row[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowHasCampusData(array $row): bool
    {
        if (filled($row['campus_code'] ?? '') || filled($row['biometric_id'] ?? '')) {
            return true;
        }

        foreach ($this->optionalCampusSlotNumbers() as $slot) {
            $prefix = $this->campusSlotPrefix($slot);

            if (filled($row[$prefix.'code'] ?? '') || filled($row[$prefix.'biometric_id'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowHasSalaryData(array $row, bool $isHybrid): bool
    {
        $prefixes = $isHybrid ? ['salary_', 'salary2_'] : ['salary_'];

        foreach ($prefixes as $prefix) {
            if ($this->salaryBlockHasAnyValue($row, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function salaryBlockHasAnyValue(array $row, string $prefix): bool
    {
        foreach ([
            'date_effective_from', 'date_effective_to', 'date_effective',
            'pay_type', 'basic_computation', 'rate_group', 'nd_rate_group',
            'days_per_period', 'hours_per_day', 'use_basic_income_as_hourly_rate',
            'is_above_minimum_wage_earner', 'basic_taxable', 'basic_non_taxable',
            'incomes', 'deductions',
        ] as $suffix) {
            if (filled($row[$prefix.$suffix] ?? '')) {
                return true;
            }
        }

        return false;
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
            'user_type' => strtolower((string) ($row['user_type'] ?? '')),
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

        // Slot 1 (primary) — required when validating / when both fields are filled.
        if ($validatePrimary || (filled($row['campus_code'] ?? '') && filled($row['biometric_id'] ?? ''))) {
            $assignments[] = [
                'campus_id' => (int) $this->campusesByCode->get($row['campus_code']),
                'biometric_id' => trim((string) $row['biometric_id']),
                'college' => $this->nullable($row['college'] ?? ''),
                'department' => $this->nullable($row['department'] ?? ''),
                'program' => $this->nullable($row['program'] ?? ''),
            ];
        }

        // Slots 2–5 — optional; include only when both campus code and biometric ID are present.
        foreach ($this->optionalCampusSlotNumbers() as $slot) {
            $prefix = $this->campusSlotPrefix($slot);
            $code = trim((string) ($row[$prefix.'code'] ?? ''));
            $biometric = trim((string) ($row[$prefix.'biometric_id'] ?? ''));

            if ($code === '' || $biometric === '') {
                continue;
            }

            $assignments[] = [
                'campus_id' => (int) $this->campusesByCode->get($code),
                'biometric_id' => $biometric,
                'college' => $this->nullable($row[$prefix.'college'] ?? ''),
                'department' => $this->nullable($row[$prefix.'department'] ?? ''),
                'program' => $this->nullable($row[$prefix.'program'] ?? ''),
            ];
        }

        return $assignments;
    }

    /**
     * @return list<int>
     */
    private function optionalCampusSlotNumbers(): array
    {
        return range(2, self::MAX_CAMPUS_ASSIGNMENTS);
    }

    private function campusSlotPrefix(int $slot): string
    {
        return 'campus'.$slot.'_';
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
        $payType = $this->resolvePayType((string) ($row[$prefix.'pay_type'] ?? ''));
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
            'is_above_minimum_wage_earner' => $this->parseBoolean($row[$prefix.'is_above_minimum_wage_earner'] ?? '', false),
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

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenKeys
     * @return array{errors: array<int, string>, payload: array<string, mixed>|null}
     */
    public function mapSalaryUploadRow(array $row, int $lineNumber, array &$seenKeys): array
    {
        $this->ensureLookupsLoaded();
        $row = $this->normalizeSalaryUploadRowDates($row);
        $errors = [];

        $employeeNumber = trim((string) ($row['employee_number'] ?? ''));

        if ($employeeNumber === '') {
            return ['errors' => ["Line {$lineNumber}: Employee Number is required."], 'payload' => null];
        }

        $employmentIndex = $this->resolveEmploymentSlot($row['employment_slot'] ?? '1', $lineNumber, $errors);

        if ($errors !== []) {
            return ['errors' => $errors, 'payload' => null];
        }

        $dedupeKey = strtolower($employeeNumber).'|'.$employmentIndex;

        if (isset($seenKeys[$dedupeKey])) {
            return ['errors' => ["Line {$lineNumber}: Duplicate salary row for employee {$employeeNumber} (slot ".($employmentIndex + 1).').'], 'payload' => null];
        }

        $seenKeys[$dedupeKey] = true;

        $employee = Employee::query()
            ->where('employee_number', $employeeNumber)
            ->first();

        if ($employee === null) {
            return ['errors' => ["Line {$lineNumber}: Employee {$employeeNumber} was not found."], 'payload' => null];
        }

        $employment = $employee->employmentInformations()
            ->orderBy('sort_order')
            ->orderBy('employment_info_id')
            ->skip($employmentIndex)
            ->first();

        if ($employment === null) {
            return ['errors' => ["Line {$lineNumber}: Employment slot ".($employmentIndex + 1)." does not exist for employee {$employeeNumber}."], 'payload' => null];
        }

        $salaryRow = $this->salaryPrefixedRow($row);
        $salaryErrors = $this->validateSalaryBlock($salaryRow, $lineNumber, 'salary_', false);

        if ($salaryErrors !== []) {
            return ['errors' => $salaryErrors, 'payload' => null];
        }

        $salary = $this->buildSalaryBlock($salaryRow, 'salary_', $employmentIndex);
        $payType = $this->resolvePayType($salaryRow['salary_pay_type'] ?? '');

        return [
            'errors' => [],
            'payload' => [
                'employee_id' => $employee->employee_id,
                'employee_number' => $employeeNumber,
                'employment_info_id' => $employment->employment_info_id,
                'employment_index' => $employmentIndex,
                'salary' => $salary,
                'preview' => [
                    'employee_number' => $employeeNumber,
                    'name' => $employee->full_name,
                    'employment_slot' => (string) ($employmentIndex + 1),
                    'date_effective_from' => $salary['date_effective_from'] ?? '',
                    'pay_type' => $payType?->pay_type ?? '—',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function salaryPrefixedRow(array $row): array
    {
        $mapped = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['employee_number', 'employment_slot'], true)) {
                continue;
            }

            $mapped['salary_'.$key] = (string) $value;
        }

        return $mapped;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveEmploymentSlot(string $raw, int $lineNumber, array &$errors): int
    {
        $normalized = strtolower(trim($raw));

        if ($normalized === '') {
            return 0;
        }

        return match ($normalized) {
            '1', 'primary', 'first' => 0,
            '2', 'secondary', 'second', 'staff', 'hybrid' => 1,
            default => $this->invalidEmploymentSlot($raw, $lineNumber, $errors),
        };
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function invalidEmploymentSlot(string $raw, int $lineNumber, array &$errors): int
    {
        $errors[] = "Line {$lineNumber}: Invalid employment slot ({$raw}). Use 1 (primary) or 2 (secondary/hybrid).";

        return 0;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeSalaryUploadRowDates(array $row): array
    {
        foreach (['date_effective_from', 'date_effective_to'] as $field) {
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
}
