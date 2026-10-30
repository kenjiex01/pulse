<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryIncome;
use App\Models\IncomeType;
use App\Models\LuDay;
use App\Models\PayType;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Services\TimeLogsPayrollService;
use App\Support\PayrollCalendarWorkingDays;
use Carbon\CarbonImmutable;
use Database\Seeders\PayrollMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollCalendarWorkingDaysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function working_day_count_excludes_employee_rest_days(): void
    {
        $sunday = LuDay::idFromDate(CarbonImmutable::parse('2026-10-04'));

        $count = PayrollCalendarWorkingDays::count(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-14'),
            [$sunday],
        );

        $this->assertSame(12, $count);
        $this->assertSame(14, PayrollCalendarWorkingDays::count(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-14'),
        ));
    }

    #[Test]
    public function salary_setup_hourly_rate_uses_half_of_days_per_period(): void
    {
        $basicType = new IncomeType(['income_type_code' => 'BASC', 'is_default_basic' => true]);
        $salary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'use_basic_income_as_hourly_rate' => false,
            'use_payroll_calendar_days' => true,
        ]);
        $income = new EmployeeSalaryIncome([
            'taxable' => 13000,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);
        $salary->setRelation('incomes', collect([$income]));

        $this->assertEqualsWithDelta(125.0, $salary->hourlyRate(), 0.001);
        $this->assertSame(116.07, $salary->hourlyRate(14));
    }

    #[Test]
    public function payroll_hourly_rate_uses_cutoff_working_days_not_days_per_period(): void
    {
        $this->seed(PayrollMaintenanceSeeder::class);

        if (! LuDay::query()->where('day_id', 1)->exists()) {
            DB::table('lu_days')->insert(['day_id' => 1, 'day' => 'Sunday']);
        }

        $policy = TimekeepingPolicy::query()->create([
            'policy_code' => 'PCAL',
            'policy_name' => 'Calendar days',
            'is_active' => true,
            'grace_period' => 0,
        ]);
        $shift = ShiftCode::query()->create([
            'shift_code' => 'PCAL',
            'description' => '8 to 5',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'is_flexi_time' => false,
        ]);
        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'PCAL',
            'description' => 'Calendar days',
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'PCAL-1',
            'first_name' => 'Ada',
            'last_name' => 'Calendar',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);
        $employee->timekeepingRestDays()->create([
            'day_id' => LuDay::idFromDate(CarbonImmutable::parse('2026-10-04')),
            'is_paid' => false,
        ]);

        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => '2026-10-08 08:00:00',
            'dt_to' => '2026-10-08 17:00:00',
            'uploaded_by_id' => 0,
            'batch_no' => 1,
        ]);
        foreach (['2026-10-08 08:00:00' => true, '2026-10-08 17:00:00' => false] as $dateTime => $isIn) {
            RawTimekeepingInandout::query()->create([
                'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                'employee_id' => $employee->employee_id,
                'dt_datetime' => $dateTime,
                'is_in' => $isIn,
            ]);
        }

        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $salary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'use_basic_income_as_hourly_rate' => false,
            'use_payroll_calendar_days' => true,
        ]);
        $income = new EmployeeSalaryIncome([
            'income_type_id' => $basicType->income_type_id,
            'taxable' => 13000,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);
        $salary->setRelation('incomes', collect([$income]));

        $result = app(TimeLogsPayrollService::class)->computeForPeriod(
            $salary,
            (int) $employee->employee_id,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-14'),
            $policy,
            '08:00',
            '17:00',
            $shift,
        );

        $this->assertSame(1, $result['worked_days']);
        $this->assertEqualsWithDelta(13000 / 12, $result['basic_taxable'], 0.01);
    }

    #[Test]
    public function unworked_days_become_an_absence_amount_while_basic_stays_whole(): void
    {
        $basicType = new IncomeType(['income_type_code' => 'BASC', 'is_default_basic' => true]);
        $salary = new EmployeeSalary([
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'use_payroll_calendar_days' => true,
        ]);
        $income = new EmployeeSalaryIncome([
            'taxable' => 13000,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);
        $salary->setRelation('incomes', collect([$income]));

        $unworked = PayrollCalendarWorkingDays::unworkedAbsence($salary, 12, 10);

        $this->assertSame(13000.0, $salary->basicIncomeAmount());
        $this->assertSame(2.0, $unworked['days']);
        $this->assertEqualsWithDelta(13000 / 12 * 2, $unworked['amount'], 0.01);
        $this->assertSame(0.0, PayrollCalendarWorkingDays::unworkedAbsence($salary, 12, 12)['amount']);
    }

    #[Test]
    public function rest_days_are_not_absences_even_with_no_punch(): void
    {
        $from = CarbonImmutable::parse('2026-10-01');
        $to = CarbonImmutable::parse('2026-10-14');
        $sunday = LuDay::idFromDate(CarbonImmutable::parse('2026-10-04'));

        $absentDates = PayrollCalendarWorkingDays::absenceDates($from, $to, [$sunday], []);

        $this->assertCount(12, $absentDates);
        $this->assertNotContains('2026-10-04', $absentDates);
        $this->assertNotContains('2026-10-11', $absentDates);

        $basicType = new IncomeType(['income_type_code' => 'BASC', 'is_default_basic' => true]);
        $salary = new EmployeeSalary([
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'use_payroll_calendar_days' => true,
        ]);
        $income = new EmployeeSalaryIncome([
            'taxable' => 13000,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);
        $salary->setRelation('incomes', collect([$income]));

        $unworked = PayrollCalendarWorkingDays::unworkedAbsence(
            $salary,
            12,
            0,
            $from,
            $to,
            [$sunday],
            [],
        );

        $this->assertSame(12.0, $unworked['days']);
        $this->assertEqualsWithDelta(13000.0, $unworked['amount'], 0.01);
    }
}
