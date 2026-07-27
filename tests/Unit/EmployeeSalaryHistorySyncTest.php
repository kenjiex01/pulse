<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Services\EmployeeSalarySync;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeSalaryHistorySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function it_closes_current_salary_when_a_new_effectivity_date_is_saved(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EFF-001',
            'first_name' => 'Effectivity',
            'middle_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'effectivity.test@example.com',
            'phone' => '09170000001',
            'campus_id' => Campus::query()->value('campus_id'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        $employment = EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 0,
        ]);

        $salaryPayload = fn (string $from, float $basic) => [
            'employment_index' => 0,
            'date_effective_from' => $from,
            'basic_computation_id' => BasicComputation::LEAVES,
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'incomes' => [[
                'income_type_id' => 1,
                'taxable' => $basic,
                'non_taxable' => 0,
            ]],
        ];

        EmployeeSalarySync::sync($employee, [$salaryPayload('2026-06-01', 10000)], false);
        EmployeeSalarySync::sync($employee, [$salaryPayload('2026-06-11', 15000)], false);

        $previousSalary = EmployeeSalary::query()
            ->where('employment_info_id', $employment->employment_info_id)
            ->whereDate('date_effective_from', '2026-06-01')
            ->whereDate('date_effective_to', '2026-06-10')
            ->first();

        $currentSalary = EmployeeSalary::query()
            ->where('employment_info_id', $employment->employment_info_id)
            ->whereDate('date_effective_from', '2026-06-11')
            ->whereNull('date_effective_to')
            ->first();

        $this->assertNotNull($previousSalary);
        $this->assertNotNull($currentSalary);

        $this->assertSame(
            2,
            EmployeeSalary::query()->where('employment_info_id', $employment->employment_info_id)->count(),
        );

        $this->assertEquals(10000.0, (float) $previousSalary->incomes()->sum('taxable'));
        $this->assertEquals(15000.0, (float) $currentSalary->incomes()->sum('taxable'));
        $this->assertSame(
            (int) RateGroup::query()->value('rate_group_id'),
            (int) $previousSalary->rate_group_id,
        );
        $this->assertSame('10.00000', (string) $previousSalary->days_per_period);
    }
}
