<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Services\EmployeeSalaryResolverService;
use App\Services\EmployeeSalarySync;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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

    #[Test]
    public function it_archives_previous_salary_when_amount_changes_on_the_same_effectivity_date(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EFF-002',
            'first_name' => 'Same',
            'middle_name' => 'Day',
            'last_name' => 'History',
            'email' => 'same.day.history@example.com',
            'phone' => '09170000002',
            'campus_id' => Campus::query()->value('campus_id'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => true,
        ]);

        $faculty = EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 0,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $salaryPayload = fn (int $index, float $basic) => [
            'employment_index' => $index,
            'date_effective_from' => '2026-07-14',
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 10.875,
            'hours_per_day' => 8,
            'incomes' => [[
                'income_type_id' => 1,
                'taxable' => $basic,
                'non_taxable' => 0,
            ]],
        ];

        EmployeeSalarySync::sync($employee, [
            $salaryPayload(0, 25000),
            $salaryPayload(1, 18000),
        ], true);

        EmployeeSalarySync::sync($employee, [
            $salaryPayload(0, 25000.01),
            $salaryPayload(1, 18000),
        ], true);

        $previousFaculty = EmployeeSalary::query()
            ->where('employment_info_id', $faculty->employment_info_id)
            ->whereNotNull('date_effective_to')
            ->whereDate('date_effective_from', '2026-07-14')
            ->whereDate('date_effective_to', '2026-07-14')
            ->first();

        $currentFaculty = EmployeeSalary::query()
            ->where('employment_info_id', $faculty->employment_info_id)
            ->whereNull('date_effective_to')
            ->whereDate('date_effective_from', '2026-07-14')
            ->first();

        $this->assertNotNull($previousFaculty);
        $this->assertNotNull($currentFaculty);
        $this->assertEquals(25000.0, (float) $previousFaculty->incomes()->sum('taxable'));
        $this->assertEquals(25000.01, (float) $currentFaculty->incomes()->sum('taxable'));
        $this->assertSame(
            2,
            EmployeeSalary::query()->where('employment_info_id', $faculty->employment_info_id)->count(),
        );
    }

    #[Test]
    public function it_closes_previous_salary_the_day_before_an_earlier_effectivity_date(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EFF-003',
            'first_name' => 'Earlier',
            'middle_name' => 'Date',
            'last_name' => 'History',
            'email' => 'earlier.date.history@example.com',
            'phone' => '09170000003',
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

        EmployeeSalarySync::sync($employee, [$salaryPayload('2024-10-28', 15600)], false);
        EmployeeSalarySync::sync($employee, [$salaryPayload('2024-10-01', 15600)], false);

        $previousSalary = EmployeeSalary::query()
            ->where('employment_info_id', $employment->employment_info_id)
            ->whereDate('date_effective_from', '2024-10-28')
            ->whereDate('date_effective_to', '2024-09-30')
            ->first();

        $currentSalary = EmployeeSalary::query()
            ->where('employment_info_id', $employment->employment_info_id)
            ->whereDate('date_effective_from', '2024-10-01')
            ->whereNull('date_effective_to')
            ->first();

        $this->assertNotNull($previousSalary);
        $this->assertNotNull($currentSalary);
    }

    #[Test]
    public function a_future_effectivity_stays_out_of_current_salary_until_that_date(): void
    {
        Carbon::setTestNow('2026-10-15 08:00:00');

        try {
            $employee = Employee::query()->create([
                'employee_number' => 'EFF-004',
                'first_name' => 'Future',
                'middle_name' => 'Salary',
                'last_name' => 'Effectivity',
                'email' => 'future.salary.effectivity@example.com',
                'phone' => '09170000004',
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

            EmployeeSalarySync::sync($employee, [$salaryPayload('2024-10-28', 15600)], false);
            EmployeeSalarySync::sync($employee, [$salaryPayload('2026-11-01', 20000)], false);

            $employment->unsetRelation('salary');
            $employment->unsetRelation('previousSalaries');

            $current = $employment->salary;
            $scheduled = $employment->previousSalaries->first(
                fn (EmployeeSalary $salary) => $salary->date_effective_from?->toDateString() === '2026-11-01'
            );

            $this->assertNotNull($current);
            $this->assertSame('2024-10-28', $current->date_effective_from?->toDateString());
            $this->assertSame('2026-10-31', $current->date_effective_to?->toDateString());
            $this->assertEquals(15600.0, (float) $current->incomes()->sum('taxable'));

            $this->assertNotNull($scheduled);
            $this->assertNull($scheduled->date_effective_to);
            $this->assertEquals(20000.0, (float) $scheduled->incomes()->sum('taxable'));

            $resolver = app(EmployeeSalaryResolverService::class);
            $october = $resolver->salariesForPeriod(
                $employee->employee_id,
                PayType::SEMI_MONTHLY,
                CarbonImmutable::parse('2026-10-01'),
                CarbonImmutable::parse('2026-10-31'),
            );
            $november = $resolver->salariesForPeriod(
                $employee->employee_id,
                PayType::SEMI_MONTHLY,
                CarbonImmutable::parse('2026-11-01'),
                CarbonImmutable::parse('2026-11-15'),
            );

            $this->assertCount(1, $october);
            $this->assertSame((int) $current->employee_salary_id, (int) $october->first()->employee_salary_id);
            $this->assertTrue($november->contains(
                fn (EmployeeSalary $salary) => (int) $salary->employee_salary_id === (int) $scheduled->employee_salary_id
            ));

            EmployeeSalarySync::sync($employee, [$salaryPayload('2024-10-28', 15600)], false);

            $employment->unsetRelation('salary');
            $stillCurrent = $employment->salary()->first();
            $stillScheduled = EmployeeSalary::query()
                ->where('employment_info_id', $employment->employment_info_id)
                ->whereDate('date_effective_from', '2026-11-01')
                ->whereNull('date_effective_to')
                ->first();

            $this->assertSame((int) $current->employee_salary_id, (int) $stillCurrent?->employee_salary_id);
            $this->assertSame('2026-10-31', $stillCurrent?->date_effective_to?->toDateString());
            $this->assertNotNull($stillScheduled);
            $this->assertEquals(20000.0, (float) $stillScheduled->incomes()->sum('taxable'));

            EmployeeSalarySync::sync($employee, [$salaryPayload('2024-10-28', 17000)], false);

            $employment->unsetRelation('salary');
            $updatedCurrent = $employment->salary()->first();

            $this->assertSame((int) $current->employee_salary_id, (int) $updatedCurrent?->employee_salary_id);
            $this->assertSame('2026-10-31', $updatedCurrent?->date_effective_to?->toDateString());
            $this->assertEquals(17000.0, (float) $updatedCurrent?->incomes()->sum('taxable'));
            $this->assertEquals(20000.0, (float) $stillScheduled->fresh()->incomes()->sum('taxable'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
