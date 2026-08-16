<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const COMPLIANCE_COMPLIANT = 'compliant';

    public const COMPLIANCE_PENDING = 'pending';

    public const COMPLIANCE_OVERDUE = 'overdue';

    public const COMPLIANCE_WITHHELD = 'withheld';

    /**
     * @return array<string, string>
     */
    public static function selectableComplianceStatuses(): array
    {
        return [
            self::COMPLIANCE_PENDING => 'Pending',
            self::COMPLIANCE_COMPLIANT => 'Compliant',
        ];
    }

    protected $table = 'tbl_employees';

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'campus_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'is_hybrid',
        'email',
        'phone',
        'home_phone',
        'work_phone',
        'fax_number',
        'program',
        'department',
        'college',
        'campus',
        'employment_status',
        'compliance_status',
        'is_active',
        'birth_date',
        'place_of_birth',
        'gender',
        'civil_status',
        'nationality',
        'religion',
        'language_dialect',
        'height_cm',
        'weight_kg',
        'tin_number',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'gsis_number',
        'tax_status',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_email',
        'emergency_contact_address',
        'address_line',
        'country',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'postal_code',
        'extended_profile',
        'is_confidential',
    ];

    protected function casts(): array
    {
        return [
            'is_hybrid' => 'boolean',
            'is_confidential' => 'boolean',
            'is_active' => 'boolean',
            'birth_date' => 'date',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'extended_profile' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Employee $employee) {
            if ($employee->isForceDeleting()) {
                return;
            }

            $employee->employmentInformations()->each(
                fn (EmployeeEmploymentInformation $info) => $info->delete()
            );
            $employee->campusAssignments()->each(
                fn (EmployeeCampusAssignment $assignment) => $assignment->delete()
            );
            $employee->credentials()->each(
                fn (EmployeeCredential $credential) => $credential->delete()
            );
        });
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'campus_id');
    }

    public function getCampusNameAttribute(): ?string
    {
        if ($this->relationLoaded('campus')) {
            $relation = $this->getRelation('campus');

            if ($relation instanceof Campus) {
                return $relation->campus_name;
            }
        }

        if ($this->campus_id) {
            $name = $this->campus()->value('campus_name');

            if (filled($name)) {
                return $name;
            }
        }

        $code = $this->attributes['campus'] ?? null;

        return filled($code) ? (string) $code : null;
    }

    public function employmentInformations(): HasMany
    {
        return $this->hasMany(EmployeeEmploymentInformation::class, 'employee_id', 'employee_id')
            ->orderBy('sort_order')
            ->orderBy('employment_info_id');
    }

    public function campusAssignments(): HasMany
    {
        return $this->hasMany(EmployeeCampusAssignment::class, 'employee_id', 'employee_id')
            ->orderBy('sort_order')
            ->orderBy('employee_campus_assignment_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(EmployeeCredential::class, 'employee_id', 'employee_id')
            ->with('documentType')
            ->orderByDesc('employee_credential_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class, 'employee_id', 'employee_id')
            ->orderByDesc('dt_loan')
            ->orderByDesc('employee_loan_id');
    }

    public function timekeepingSetup(): HasOne
    {
        return $this->hasOne(TimekeepingEmployeeSetup::class, 'employee_id', 'employee_id');
    }

    public function timekeepingRestDays(): HasMany
    {
        return $this->hasMany(TimekeepingEmployeeRestDay::class, 'employee_id', 'employee_id');
    }

    public function teachingLoadSyncStatus(): HasOne
    {
        return $this->hasOne(TeachingLoadSyncStatus::class, 'employee_id', 'employee_id');
    }

    public function teachingLoadSessions(): HasMany
    {
        return $this->hasMany(TeachingLoadSession::class, 'employee_id', 'employee_id');
    }

    public function hasTimekeepingSetup(): bool
    {
        return $this->relationLoaded('timekeepingSetup')
            ? $this->timekeepingSetup !== null
            : $this->timekeepingSetup()->exists();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        $term = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($term) {
            $builder
                ->where('employee_number', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('middle_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('department', 'like', $term)
                ->orWhere('college', 'like', $term)
                ->orWhereHas('employmentInformations', function (Builder $employmentQuery) use ($term) {
                    $employmentQuery
                        ->where('position', 'like', $term)
                        ->orWhere('designation', 'like', $term);
                });
        });
    }

    public function scopeFacultyEligible(Builder $query): Builder
    {
        return $query->whereHas('employmentInformations', function (Builder $employmentQuery) {
            $employmentQuery->where('user_type', EmployeeEmploymentInformation::TYPE_FACULTY);
        });
    }

    public function isFaculty(): bool
    {
        if ($this->relationLoaded('employmentInformations')) {
            return $this->employmentInformations->contains(
                fn (EmployeeEmploymentInformation $employment) => $employment->user_type === EmployeeEmploymentInformation::TYPE_FACULTY
            );
        }

        return $this->employmentInformations()
            ->where('user_type', EmployeeEmploymentInformation::TYPE_FACULTY)
            ->exists();
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])));
    }

    public function getPrimaryEmploymentInformationAttribute(): ?EmployeeEmploymentInformation
    {
        if ($this->relationLoaded('employmentInformations')) {
            return $this->employmentInformations->first();
        }

        return $this->employmentInformations()->first();
    }

    public function getUserTypeAttribute(): ?string
    {
        if ($this->is_hybrid) {
            return null;
        }

        return $this->primaryEmploymentInformation?->user_type;
    }

    public function getUserTypeLabelAttribute(): ?string
    {
        if ($this->is_hybrid) {
            return 'Hybrid';
        }

        return $this->primaryEmploymentInformation?->user_type_label;
    }

    public function getPositionAttribute(): ?string
    {
        return $this->primaryEmploymentInformation?->position;
    }

    public function getDesignationAttribute(): ?string
    {
        return $this->primaryEmploymentInformation?->designation;
    }

    public function getRankAttribute(): ?string
    {
        return $this->primaryEmploymentInformation?->rank;
    }

    public function getEmploymentTypeAttribute(): ?string
    {
        return $this->primaryEmploymentInformation?->employment_type;
    }

    public function getHireDateAttribute(): mixed
    {
        return $this->primaryEmploymentInformation?->hire_date;
    }

    public function isInactive(): bool
    {
        return $this->employment_status === self::STATUS_INACTIVE || ! $this->is_active;
    }

    public static function generateEmployeeNumber(): string
    {
        $year = now()->format('Y');
        $prefix = $year.'-';

        $latest = static::query()
            ->where('employee_number', 'like', $prefix.'%')
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $sequence = $latest
            ? ((int) substr($latest, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function extended(string $key, mixed $default = null): mixed
    {
        return data_get($this->extended_profile, $key, $default);
    }

    public function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return $value === [] ? '—' : implode(', ', array_filter($value));
        }

        return (string) $value;
    }

    public function logSnapshot(): array
    {
        $this->loadMissing([
            'campusAssignments.campus',
            'employmentInformations.salary.payType',
            'employmentInformations.salary.basicComputation',
            'employmentInformations.salary.rateGroup',
            'employmentInformations.salary.ndRateGroup',
            'employmentInformations.salary.incomes.incomeType',
            'employmentInformations.salary.deductions.deductionType',
        ]);

        $extended = $this->extended_profile ?? [];
        $roleId = data_get($extended, 'role_id');

        return array_merge(
            $this->only([
                'employee_number',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'is_hybrid',
                'email',
                'phone',
                'home_phone',
                'work_phone',
                'fax_number',
                'program',
                'department',
                'college',
                'campus',
                'campus_id',
                'employment_status',
                'compliance_status',
                'is_active',
                'birth_date',
                'place_of_birth',
                'gender',
                'civil_status',
                'nationality',
                'religion',
                'language_dialect',
                'height_cm',
                'weight_kg',
                'tin_number',
                'sss_number',
                'philhealth_number',
                'pagibig_number',
                'gsis_number',
                'tax_status',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_address',
                'address_line',
                'country',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'postal_code',
                'is_confidential',
            ]),
            [
                'role_id' => $roleId,
                'extended_profile' => $extended,
                'employment_informations' => $this->employmentInformations
                    ->map(fn (EmployeeEmploymentInformation $info) => [
                        'user_type' => $info->user_type,
                        'position' => $info->position,
                        'designation' => $info->designation,
                        'rank' => $info->rank,
                        'employment_type' => $info->employment_type,
                        'hire_date' => $info->hire_date?->format('Y-m-d'),
                        'sort_order' => $info->sort_order,
                    ])
                    ->values()
                    ->all(),
                'campus_assignments' => $this->campusAssignments
                    ->map(fn (EmployeeCampusAssignment $assignment) => [
                        'campus_id' => $assignment->campus_id,
                        'campus_code' => $assignment->campus?->campus_code,
                        'college' => $assignment->college,
                        'department' => $assignment->department,
                        'program' => $assignment->program,
                        'biometric_id' => $assignment->biometric_id,
                        'sort_order' => $assignment->sort_order,
                    ])
                    ->values()
                    ->all(),
                'employee_salaries' => $this->employmentInformations
                    ->flatMap(function (EmployeeEmploymentInformation $info) {
                        $salary = $info->salary;

                        if ($salary === null) {
                            return [];
                        }

                        return [[
                            'user_type' => $info->user_type,
                            'date_effective_from' => $salary->date_effective_from?->format('Y-m-d'),
                            'date_effective_to' => $salary->date_effective_to?->format('Y-m-d'),
                            'pay_type' => $salary->payType?->pay_type,
                            'basic_computation' => $salary->basicComputation?->description,
                            'rate_group' => $salary->rateGroup?->description,
                            'nd_rate_group' => $salary->ndRateGroup?->description,
                            'days_per_period' => $salary->days_per_period,
                            'hours_per_day' => $salary->hours_per_day,
                            'use_basic_income_as_hourly_rate' => $salary->use_basic_income_as_hourly_rate,
                            'is_above_minimum_wage_earner' => $salary->is_above_minimum_wage_earner,
                            'incomes' => $salary->incomes
                                ->map(fn (EmployeeSalaryIncome $income) => [
                                    'code' => $income->incomeType?->income_type_code,
                                    'taxable' => $income->taxable,
                                    'non_taxable' => $income->non_taxable,
                                ])
                                ->values()
                                ->all(),
                            'deductions' => $salary->deductions
                                ->map(fn (EmployeeSalaryDeduction $deduction) => [
                                    'code' => $deduction->deductionType?->deduction_type_code,
                                    'employee_amount' => $deduction->employee_amount,
                                    'employer_amount' => $deduction->employer_amount,
                                ])
                                ->values()
                                ->all(),
                        ]];
                    })
                    ->values()
                    ->all(),
            ],
        );
    }
}
