<?php

namespace Tests\Unit;

use App\Models\DayType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryIncome;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeRestDay;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHoliday;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingHolidayGroupList;
use App\Models\TimekeepingHolidayYear;
use App\Models\TimekeepingYear;
use App\Models\User;
use App\Services\EmployeeAttendanceLogService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use App\Services\HolidayPayService;
use App\Services\PayrollHoursWorkedPayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HolidayPayServiceTest extends TestCase
{
    use RefreshDatabase;

    private HolidayPayService $holidayPay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->holidayPay = app(HolidayPayService::class);
    }

    #[Test]
    public function test_absent_legal_holiday_pays_daily_rate_when_time_logs_before_and_after(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 17:00:00'), false, $user->id);

        $result = $this->holidayPay->absentHolidayIncomeForDetail($detail, $salary);

        $this->assertNotNull($result);
        $this->assertSame(
            (int) IncomeType::query()->where('income_type_code', 'HOLI')->value('income_type_id'),
            $result['income_type_id'],
        );
        $this->assertSame(1000.0, $result['taxable']);
        $this->assertSame(1.0, $result['days']);

        $totals = app(PayrollHoursWorkedPayrollService::class)->computeIncomeTotalsForDetail($detail, $salary);
        $holiId = (int) IncomeType::query()->where('income_type_code', 'HOLI')->value('income_type_id');

        $this->assertNotNull($totals);
        $this->assertSame(1.0, $totals['by_income_type'][$holiId]['days']);
        $this->assertSame(8.0, $totals['by_income_type'][$holiId]['hours']);
    }

    #[Test]
    public function test_absent_holiday_not_paid_without_time_log_before_holiday(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 17:00:00'), false, $user->id);

        $this->assertNull($this->holidayPay->absentHolidayIncomeForDetail($detail, $salary));
    }

    #[Test]
    public function test_absent_holiday_skips_rest_day_when_finding_adjacent_working_day(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-27',
            restDayId: 4,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-28 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-28 17:00:00'), false, $user->id);

        $result = $this->holidayPay->absentHolidayIncomeForDetail($detail, $salary);

        $this->assertNotNull($result);
        $this->assertSame(1000.0, $result['taxable']);
    }

    #[Test]
    public function test_special_holiday_does_not_get_unworked_holiday_pay_even_with_logs_before_and_after(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
            isLegal: false,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 17:00:00'), false, $user->id);

        $this->assertNull($this->holidayPay->absentHolidayIncomeForDetail($detail, $salary));
    }

    #[Test]
    public function test_present_on_holiday_uses_holiday_day_type_in_hours_worked_payroll(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 17:00:00'), false, $user->id);

        $this->assertSame(1, $this->holidayPay->resolveDayTypeId((int) $employee->employee_id, CarbonImmutable::parse('2026-08-25')));
        $this->assertNull($this->holidayPay->absentHolidayIncomeForDetail($detail, $salary));

        $totals = app(PayrollHoursWorkedPayrollService::class)->computeIncomeTotalsForDetail($detail, $salary);
        $holiId = (int) IncomeType::query()->where('income_type_code', 'HOLI')->value('income_type_id');

        $this->assertNotNull($totals);
        $this->assertArrayHasKey($holiId, $totals['by_income_type']);
        $this->assertGreaterThan(0, $totals['by_income_type'][$holiId]['taxable']);
    }

    #[Test]
    public function test_worked_legal_holiday_pays_the_day_and_the_hours_when_present_before_and_after(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 17:00:00'), false, $user->id);

        $dayPay = $this->holidayPay->absentHolidayIncomeForDetail($detail, $salary);

        $this->assertNotNull($dayPay);
        $this->assertSame(1000.0, $dayPay['taxable']);
        $this->assertSame(1.0, $dayPay['days']);

        $totals = app(PayrollHoursWorkedPayrollService::class)->computeIncomeTotalsForDetail($detail, $salary);
        $holiId = (int) IncomeType::query()->where('income_type_code', 'HOLI')->value('income_type_id');

        $this->assertNotNull($totals);
        $this->assertGreaterThan(1000.0, $totals['by_income_type'][$holiId]['taxable']);
    }

    #[Test]
    public function test_worked_special_holiday_posts_rate_times_hours_as_holiday_pay_not_basic(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
            isLegal: false,
        );

        $salary->hours_per_day = 8;
        $salary->days_per_period = 10;
        $salary->save();
        EmployeeSalaryIncome::query()
            ->where('employee_salary_id', $salary->employee_salary_id)
            ->update(['taxable' => 6000, 'non_taxable' => 0]);
        $salary->unsetRelation('incomes');

        ShiftCode::query()->where('shift_code', 'HOLS')->update([
            'time_in' => '08:00',
            'time_out' => '16:00',
        ]);

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 16:00:00'), false, $user->id);

        $this->assertNull($this->holidayPay->absentHolidayIncomeForDetail($detail, $salary->refresh()));

        $totals = app(PayrollHoursWorkedPayrollService::class)->computeIncomeTotalsForDetail($detail, $salary->refresh());
        $holiId = (int) IncomeType::query()->where('income_type_code', 'HOLI')->value('income_type_id');
        $basicId = (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');

        $this->assertNotNull($totals);
        $this->assertSame(780.0, $totals['by_income_type'][$holiId]['taxable']);
        $this->assertSame(8.0, $totals['by_income_type'][$holiId]['hours']);
        $this->assertArrayNotHasKey($basicId, $totals['by_income_type']);
    }

    #[Test]
    public function test_one_minute_late_comes_out_of_basic_at_the_exact_hourly_rate(): void
    {
        ['salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $salary->update([
            'days_per_period' => 26,
            'hours_per_day' => 8,
        ]);
        EmployeeSalaryIncome::query()
            ->where('employee_salary_id', $salary->employee_salary_id)
            ->update(['taxable' => 16770, 'non_taxable' => 0]);
        $salary->unsetRelation('incomes');

        $regularDayTypeId = (int) DayType::query()->where('day_type_code', 'REGU')->value('day_type_id');
        $rows = collect([(object) [
            'day_type_id' => $regularDayTypeId,
            'time_type_id' => 1,
            'hours' => 96 - (1 / 60),
            'days' => 12,
            'time_type_code' => 'BP',
        ]]);

        $totals = app(PayrollHoursWorkedPayrollService::class)->computeIncomeTotals($salary->refresh(), $rows);
        $basicId = (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');

        $this->assertEqualsWithDelta(7738.66, $totals['by_income_type'][$basicId]['taxable'], 0.001);
        $this->assertEqualsWithDelta(95.9833, $totals['by_income_type'][$basicId]['hours'], 0.0001);
    }

    #[Test]
    public function test_late_minutes_are_not_rounded_before_the_basic_amount(): void
    {
        ['salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $salary->update([
            'days_per_period' => 26,
            'hours_per_day' => 8,
        ]);
        EmployeeSalaryIncome::query()
            ->where('employee_salary_id', $salary->employee_salary_id)
            ->update(['taxable' => 18200, 'non_taxable' => 0]);
        $salary->unsetRelation('incomes');

        $regularDayTypeId = (int) DayType::query()->where('day_type_code', 'REGU')->value('day_type_id');
        $service = app(PayrollHoursWorkedPayrollService::class);
        $merge = new \ReflectionMethod($service, 'mergeHourRows');
        $rows = $merge->invoke($service, collect([
            (object) [
                'day_type_id' => $regularDayTypeId,
                'time_type_id' => 1,
                'hours' => 8 - (98 / 60),
                'days' => 1,
                'time_type_code' => 'BP',
            ],
            (object) [
                'day_type_id' => $regularDayTypeId,
                'time_type_id' => 1,
                'hours' => 8 - (6 / 60),
                'days' => 1,
                'time_type_code' => 'BP',
            ],
            (object) [
                'day_type_id' => $regularDayTypeId,
                'time_type_id' => 1,
                'hours' => 80,
                'days' => 10,
                'time_type_code' => 'BP',
            ],
        ]));

        $totals = $service->computeIncomeTotals($salary->refresh(), $rows);
        $basicId = (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');

        $this->assertSame(8248.33, $totals['by_income_type'][$basicId]['taxable']);
        $this->assertEqualsWithDelta(94.2667, $totals['by_income_type'][$basicId]['hours'], 0.0001);
    }

    #[Test]
    public function test_calendar_day_salary_does_not_add_a_second_day_pay_when_the_legal_holiday_was_worked(): void
    {
        ['employee' => $employee, 'detail' => $detail, 'salary' => $salary] = $this->makeHolidayScenario(
            holidayDate: '2026-08-25',
            restDayId: null,
        );

        $salary->update(['use_payroll_calendar_days' => true]);
        $salary->refresh();

        $user = User::query()->firstOrFail();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-24 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-25 17:00:00'), false, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 08:00:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-26 17:00:00'), false, $user->id);

        $this->assertNull($this->holidayPay->absentHolidayIncomeForDetail($detail, $salary));
    }

    /**
     * @return array{employee: Employee, detail: PayrollBatchDetail, salary: EmployeeSalary}
     */
    private function makeHolidayScenario(string $holidayDate, ?int $restDayId, bool $isLegal = true): array
    {
        $shift = ShiftCode::query()->create([
            'shift_code' => 'HOLS',
            'description' => 'Holiday test shift',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'HGP',
            'description' => 'Holiday pay test group',
        ]);

        $holiday = TimekeepingHoliday::query()->create([
            'timekeeping_holiday_code' => 'IND',
            'description' => 'Independence Day',
            'dt_datestamp' => $holidayDate,
            'is_legal' => $isLegal,
            'recurring' => false,
        ]);

        TimekeepingHolidayGroupList::query()->create([
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'timekeeping_holiday_id' => $holiday->timekeeping_holiday_id,
        ]);

        $year = TimekeepingYear::query()->create(['timekeeping_year' => 2026]);

        TimekeepingHolidayYear::query()->create([
            'timekeeping_year_id' => $year->timekeeping_year_id,
            'timekeeping_holiday_id' => $holiday->timekeeping_holiday_id,
            'timekeeping_holiday_code' => $holiday->timekeeping_holiday_code,
            'dt_datestamp' => $holidayDate,
            'is_legal' => $isLegal,
            'recurring' => false,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-HOLI-1',
            'first_name' => 'Holiday',
            'last_name' => 'Tester',
            'email' => 'holiday.test@example.com',
        ]);

        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'HOLP',
            'policy_name' => 'Holiday Pay Policy',
            'description' => 'Holiday pay tests',
            'is_active' => true,
            'grace_period' => 0,
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        if ($restDayId !== null) {
            TimekeepingEmployeeRestDay::query()->create([
                'employee_id' => $employee->employee_id,
                'day_id' => $restDayId,
                'is_paid' => false,
            ]);
        }

        $employment = EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'Staff',
            'sort_order' => 1,
        ]);

        $salary = EmployeeSalary::query()->create([
            'employment_info_id' => $employment->employment_info_id,
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => 1,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'date_effective_from' => '2026-01-01',
            'days_per_period' => 10,
            'hours_per_day' => 8,
        ]);

        $basicIncome = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();

        EmployeeSalaryIncome::query()->create([
            'employee_salary_id' => $salary->employee_salary_id,
            'income_type_id' => $basicIncome->income_type_id,
            'taxable' => 10000,
            'non_taxable' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 88,
            'dt_from' => '2026-08-16 00:00:00',
            'dt_to' => '2026-08-31 00:00:00',
            'calendar_month' => 8,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 88,
            'created_by_id' => User::query()->firstOrFail()->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        return compact('employee', 'detail', 'salary');
    }
}
