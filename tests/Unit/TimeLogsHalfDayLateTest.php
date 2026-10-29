<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryIncome;
use App\Models\IncomeType;
use App\Models\PayType;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Services\EmployeeAttendanceViewService;
use App\Services\EmployeeLoadPayrollService;
use App\Services\TimeLogsPayrollService;
use Carbon\CarbonImmutable;
use Database\Seeders\PayrollMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimeLogsHalfDayLateTest extends TestCase
{
    use RefreshDatabase;

    private TimeLogsPayrollService $service;

    private TimekeepingPolicy $policy;

    private ShiftCode $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TimeLogsPayrollService::class);
        $this->policy = TimekeepingPolicy::query()->create([
            'policy_code' => 'HDLT',
            'policy_name' => 'Half-day late',
            'is_active' => true,
            'grace_period' => 0,
        ]);
        $this->shift = ShiftCode::query()->create([
            'shift_code' => 'PM211',
            'description' => '2PM-11PM',
            'time_in' => '14:00',
            'time_out' => '23:00',
            'is_flexi_time' => false,
        ]);
        $this->shift->breaks()->create([
            'shift_code_break_no' => 1,
            'break_out' => '18:00',
            'break_in' => '19:00',
            'shift_code_break_minute' => 60,
            'shift_code_is_paid_break' => false,
        ]);
        $this->shift->load('breaks');
    }

    #[Test]
    public function late_past_half_the_shift_is_half_day_absent_not_late(): void
    {
        $session = $this->makeSession('18:44:00', '23:00:00');
        $resolved = $this->service->resolvedLateForSession($session, $this->policy, '14:00', 0, $this->shift);

        $this->assertTrue($resolved['is_half_day']);
        $this->assertFalse($resolved['is_absent']);
        $this->assertSame(0, $resolved['billable_minutes']);
        $this->assertSame(240, $resolved['half_day_absent_minutes']);
        $this->assertSame('19:00:00', $resolved['half_day_start']);

        $hours = $this->service->halfDayRegularHoursForSession(
            $this->service->sessionFromHalfDayStart($session, $resolved['half_day_start']),
            '14:00',
            '23:00',
            $this->shift,
        );

        $this->assertSame(4.0, $hours);
    }

    #[Test]
    public function late_at_or_under_half_the_shift_stays_late(): void
    {
        $session = $this->makeSession('16:00:00', '23:00:00');
        $resolved = $this->service->resolvedLateForSession($session, $this->policy, '14:00', 0, $this->shift);

        $this->assertFalse($resolved['is_half_day']);
        $this->assertFalse($resolved['is_absent']);
        $this->assertSame(120, $resolved['billable_minutes']);
    }

    #[Test]
    public function attendance_view_keeps_full_basic_and_tags_half_day_absent(): void
    {
        $metrics = app(EmployeeAttendanceViewService::class)->metricsForTimes(
            1,
            CarbonImmutable::parse('2026-10-27'),
            '18:44:00',
            '23:00:00',
            $this->policy,
            $this->shift,
        );

        $this->assertSame(0.0, $metrics['late']);
        $this->assertTrue($metrics['is_half_day']);
        $this->assertFalse($metrics['is_absent']);
        $this->assertEqualsWithDelta(8.0, $metrics['basic'], 0.01);

        $row = app(EmployeeAttendanceViewService::class)->pdfRowForDay([
            'date_label' => '10/08/2026',
            'day_type' => 'Half-day Absent',
            'shift_label' => 'PM211',
            'is_rest_day' => false,
            'time_in' => '6:44 PM',
            'time_out' => '11:00 PM',
            'basic' => 8.0,
            'excess_hours' => null,
            'ot' => null,
            'ndiff' => null,
            'ndot' => null,
            'late' => 0.0,
            'late_label' => 'Absent',
            'undertime' => 0.0,
            'break_late' => 0.0,
        ]);

        $this->assertSame('Half-day Absent', $row[1]);
        $this->assertSame('Absent', $row[10]);
        $this->assertSame('8.00', $row[5]);
    }

    #[Test]
    public function faculty_late_display_is_absent_for_half_day(): void
    {
        $display = app(EmployeeLoadPayrollService::class)->formatLateDisplay([
            'raw_minutes' => 284,
            'equivalent_minutes' => null,
            'is_absent' => false,
            'is_half_day' => true,
            'billable_minutes' => 0,
        ]);

        $this->assertSame('Absent', $display);
    }

    #[Test]
    public function payroll_keeps_full_day_basic_and_deducts_half_day_absences(): void
    {
        $this->seed(PayrollMaintenanceSeeder::class);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'HDLT',
            'description' => 'Half-day late holidays',
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'HDLT-1',
            'first_name' => 'Ana',
            'last_name' => 'Late',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $this->shift->shift_code_id,
            'timekeeping_policy_id' => $this->policy->timekeeping_policy_id,
        ]);

        $this->punch($employee, '2026-10-08 18:44:00', true);
        $this->punch($employee, '2026-10-08 23:00:00', false);

        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $salary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'use_basic_income_as_hourly_rate' => false,
        ]);
        $income = new EmployeeSalaryIncome([
            'income_type_id' => $basicType->income_type_id,
            'taxable' => 10000,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);
        $salary->setRelation('incomes', collect([$income]));

        $result = $this->service->computeForPeriod(
            $salary,
            (int) $employee->employee_id,
            CarbonImmutable::parse('2026-10-08'),
            CarbonImmutable::parse('2026-10-08'),
            $this->policy,
            '14:00',
            '23:00',
            $this->shift,
        );

        $this->assertSame(1, $result['worked_days']);
        $this->assertSame(0.5, $result['absent_days']);
        $this->assertEqualsWithDelta(4.0, $result['absent_hours'], 0.01);
        $this->assertSame(0, $result['late_minutes']);
        $this->assertEqualsWithDelta(1000.0, $result['basic_taxable'], 0.01);
        $this->assertEqualsWithDelta(500.0, $result['absent_deduction'], 0.01);
    }

    private function punch(Employee $employee, string $dateTime, bool $isIn): void
    {
        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => $dateTime,
            'dt_to' => $dateTime,
            'uploaded_by_id' => 0,
            'batch_no' => 1,
        ]);

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => $dateTime,
            'is_in' => $isIn,
        ]);
    }

    /**
     * @return array{date: CarbonImmutable, time_in: string, time_out: string}
     */
    private function makeSession(string $timeIn, string $timeOut): array
    {
        return [
            'date' => CarbonImmutable::parse('2026-10-27'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
        ];
    }
}
