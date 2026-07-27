<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalary extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_salaries';

    protected $primaryKey = 'employee_salary_id';

    protected $fillable = [
        'employment_info_id',
        'date_effective_from',
        'date_effective_to',
        'basic_computation_id',
        'pay_type_id',
        'days_per_period',
        'hours_per_day',
        'use_basic_income_as_hourly_rate',
        'cola_rate_per_hour',
        'rate_group_id',
        'nd_rate_group_id',
    ];

    protected function casts(): array
    {
        return [
            'date_effective_from' => 'date',
            'date_effective_to' => 'date',
            'days_per_period' => 'decimal:5',
            'hours_per_day' => 'decimal:2',
            'use_basic_income_as_hourly_rate' => 'boolean',
            'cola_rate_per_hour' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (EmployeeSalary $salary) {
            if ($salary->isForceDeleting()) {
                return;
            }

            $salary->incomes()->delete();
            $salary->deductions()->delete();
        });
    }

    public function employmentInformation(): BelongsTo
    {
        return $this->belongsTo(EmployeeEmploymentInformation::class, 'employment_info_id', 'employment_info_id');
    }

    public function basicComputation(): BelongsTo
    {
        return $this->belongsTo(BasicComputation::class, 'basic_computation_id', 'basic_computation_id');
    }

    public function payType(): BelongsTo
    {
        return $this->belongsTo(PayType::class, 'pay_type_id', 'pay_type_id');
    }

    public function rateGroup(): BelongsTo
    {
        return $this->belongsTo(RateGroup::class, 'rate_group_id', 'rate_group_id');
    }

    public function ndRateGroup(): BelongsTo
    {
        return $this->belongsTo(NdRateGroup::class, 'nd_rate_group_id', 'nd_rate_group_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(EmployeeSalaryIncome::class, 'employee_salary_id', 'employee_salary_id')
            ->orderBy('sort_order')
            ->orderBy('employee_salary_income_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryDeduction::class, 'employee_salary_id', 'employee_salary_id')
            ->orderBy('sort_order')
            ->orderBy('employee_salary_deduction_id');
    }

    public static function computeHourlyRate(
        float $basicIncomeAmount,
        mixed $daysPerPeriod,
        mixed $hoursPerDay,
    ): ?float {
        $daysPerPeriod = $daysPerPeriod !== null && $daysPerPeriod !== '' ? (float) $daysPerPeriod : 0.0;
        $hoursPerDay = $hoursPerDay !== null && $hoursPerDay !== '' ? (float) $hoursPerDay : 0.0;

        if ($basicIncomeAmount <= 0 || $daysPerPeriod <= 0 || $hoursPerDay <= 0) {
            return null;
        }

        return round($basicIncomeAmount / $daysPerPeriod / $hoursPerDay, 2);
    }

    public function basicIncomeAmount(): float
    {
        $incomes = $this->relationLoaded('incomes')
            ? $this->incomes
            : $this->incomes()->with('incomeType')->get();

        $basicIncome = $incomes->first(
            fn (EmployeeSalaryIncome $income) => $income->incomeType?->is_default_basic
                || $income->incomeType?->income_type_code === 'BASC',
        );

        if (! $basicIncome) {
            return 0.0;
        }

        return (float) $basicIncome->taxable + (float) $basicIncome->non_taxable;
    }

    public function hourlyRate(): ?float
    {
        if ($this->use_basic_income_as_hourly_rate) {
            $basicIncomeAmount = $this->basicIncomeAmount();

            return $basicIncomeAmount > 0 ? round($basicIncomeAmount, 2) : null;
        }

        $daysPerPeriod = $this->days_per_period;

        if ($daysPerPeriod === null || (float) $daysPerPeriod <= 0) {
            $daysPerPeriod = PayType::autoDaysPerPeriod((int) $this->pay_type_id);
        }

        return self::computeHourlyRate(
            $this->basicIncomeAmount(),
            $daysPerPeriod,
            $this->hours_per_day,
        );
    }
}
