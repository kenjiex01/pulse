<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryIncome;
use App\Models\IncomeType;
use App\Models\PayType;
use Database\Seeders\DatabaseSeeder;
use App\Services\EmployeeLoadPayrollService;
use App\Services\EmployeeSalaryResolverService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeSalaryEffectivityPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function it_selects_overlapping_salary_records_for_a_pay_period(): void
    {
        $resolver = new EmployeeSalaryResolverService;

        $oldSalary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'date_effective_from' => '2026-06-01',
            'date_effective_to' => '2026-06-10',
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => 10,
            'hours_per_day' => 8,
        ]);

        $newSalary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'date_effective_from' => '2026-06-11',
            'date_effective_to' => null,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => 10,
            'hours_per_day' => 8,
        ]);

        $salaries = collect([$oldSalary, $newSalary]);

        $this->assertSame(
            $oldSalary->employee_salary_id,
            $resolver->salaryEffectiveOnDate($salaries, CarbonImmutable::parse('2026-06-05'))?->employee_salary_id,
        );

        $this->assertSame(
            $newSalary->employee_salary_id,
            $resolver->salaryEffectiveOnDate($salaries, CarbonImmutable::parse('2026-06-12'))?->employee_salary_id,
        );
    }

    #[Test]
    public function it_prorates_fixed_income_amounts_by_overlap_days(): void
    {
        $resolver = new EmployeeSalaryResolverService;

        $salary = new EmployeeSalary([
            'date_effective_from' => '2026-06-11',
            'date_effective_to' => null,
        ]);

        $amount = $resolver->prorateAmount(
            15000,
            $salary,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-15'),
        );

        $this->assertSame(5000.0, $amount);
    }

    #[Test]
    public function it_calculates_daily_rates_for_old_and_new_salary_amounts(): void
    {
        $service = new EmployeeLoadPayrollService;
        $basicIncomeType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();

        $oldSalary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'use_basic_income_as_hourly_rate' => false,
        ]);
        $oldIncome = new EmployeeSalaryIncome([
            'income_type_id' => $basicIncomeType->income_type_id,
            'taxable' => 10000,
            'non_taxable' => 0,
        ]);
        $oldIncome->setRelation('incomeType', $basicIncomeType);
        $oldSalary->setRelation('incomes', collect([$oldIncome]));

        $newSalary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'use_basic_income_as_hourly_rate' => false,
        ]);
        $newIncome = new EmployeeSalaryIncome([
            'income_type_id' => $basicIncomeType->income_type_id,
            'taxable' => 15000,
            'non_taxable' => 0,
        ]);
        $newIncome->setRelation('incomeType', $basicIncomeType);
        $newSalary->setRelation('incomes', collect([$newIncome]));

        $this->assertSame(1000.0, $service->dailyRate($oldSalary));
        $this->assertSame(1500.0, $service->dailyRate($newSalary));
    }
}
