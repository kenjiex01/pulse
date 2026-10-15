<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Services\TimeLogsPayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimeLogsCrossMidnightBufferTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_out_after_midnight_stays_on_the_previous_shift_inside_the_buffer(): void
    {
        $employee = $this->employeeWithPmShift();
        $this->punch($employee, '2026-09-28 11:59:46', true);
        $this->punch($employee, '2026-09-29 00:15:22', false);
        $this->punch($employee, '2026-09-29 11:59:08', true);
        $this->punch($employee, '2026-09-30 00:10:38', false);

        $sessions = app(TimeLogsPayrollService::class)
            ->daySessionsForPeriod(
                $employee->employee_id,
                CarbonImmutable::parse('2026-09-28'),
                CarbonImmutable::parse('2026-09-30'),
            )
            ->keyBy(fn (array $session) => $session['date']->toDateString());

        $this->assertSame('11:59:46', $sessions['2026-09-28']['time_in']);
        $this->assertSame('00:15:22', $sessions['2026-09-28']['time_out']);
        $this->assertSame('11:59:08', $sessions['2026-09-29']['time_in']);
        $this->assertSame('00:10:38', $sessions['2026-09-29']['time_out']);

        $service = app(TimeLogsPayrollService::class);

        $this->assertSame(0, $service->undertimeRawMinutesForSession(
            $sessions['2026-09-28'],
            '21:30',
            '12:30',
        ));
        $this->assertSame(0, $service->undertimeRawMinutesForSession(
            $sessions['2026-09-29'],
            '21:30',
            '12:30',
        ));
    }

    #[Test]
    public function a_break_return_does_not_replace_the_out_that_crossed_midnight(): void
    {
        $employee = $this->employeeWithPmShift();
        $this->punch($employee, '2026-10-05 11:59:26', true);
        $this->punch($employee, '2026-10-05 16:04:50', false);
        $this->punch($employee, '2026-10-05 16:23:52', true);
        $this->punch($employee, '2026-10-06 00:04:29', false);

        $session = app(TimeLogsPayrollService::class)
            ->daySessionsForPeriod(
                $employee->employee_id,
                CarbonImmutable::parse('2026-10-05'),
                CarbonImmutable::parse('2026-10-06'),
            )
            ->first(fn (array $row) => $row['date']->toDateString() === '2026-10-05');

        $this->assertSame('11:59:26', $session['time_in']);
        $this->assertSame('00:04:29', $session['time_out']);
        $this->assertSame(0, app(TimeLogsPayrollService::class)->undertimeRawMinutesForSession(
            $session,
            '21:30',
            '12:30',
        ));
    }

    #[Test]
    public function an_early_in_before_the_buffer_still_keeps_the_following_out(): void
    {
        $employee = $this->employeeWithPmShift();
        $this->punch($employee, '2026-10-03 11:58:31', true);
        $this->punch($employee, '2026-10-04 00:17:16', false);
        $this->punch($employee, '2026-10-04 05:14:45', true);
        $this->punch($employee, '2026-10-05 00:10:55', false);

        $sessions = app(TimeLogsPayrollService::class)
            ->daySessionsForPeriod(
                $employee->employee_id,
                CarbonImmutable::parse('2026-10-03'),
                CarbonImmutable::parse('2026-10-05'),
            )
            ->keyBy(fn (array $session) => $session['date']->toDateString());

        $this->assertSame('00:17:16', $sessions['2026-10-03']['time_out']);
        $this->assertSame('05:14:45', $sessions['2026-10-04']['time_in']);
        $this->assertSame('00:10:55', $sessions['2026-10-04']['time_out']);
        $this->assertSame(0, app(TimeLogsPayrollService::class)->undertimeRawMinutesForSession(
            $sessions['2026-10-04'],
            '21:30',
            '12:30',
        ));
    }

    #[Test]
    public function leaving_before_shift_end_on_the_same_day_is_still_undertime(): void
    {
        $employee = $this->employeeWithPmShift();
        $this->punch($employee, '2026-10-08 12:00:00', true);
        $this->punch($employee, '2026-10-08 16:30:00', false);

        $session = app(TimeLogsPayrollService::class)
            ->daySessionsForPeriod(
                $employee->employee_id,
                CarbonImmutable::parse('2026-10-08'),
                CarbonImmutable::parse('2026-10-08'),
            )
            ->first();

        $this->assertSame('16:30:00', $session['time_out']);
        $this->assertSame(300, app(TimeLogsPayrollService::class)->undertimeRawMinutesForSession(
            $session,
            '21:30',
            '12:30',
        ));
    }

    private function employeeWithPmShift(): Employee
    {
        $policy = TimekeepingPolicy::query()->create([
            'policy_code' => 'PMBF',
            'policy_name' => 'PM buffer',
            'is_active' => true,
            'buffer_time_in' => 4,
            'buffer_time_out' => 10,
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'PM4X',
            'description' => '12:30 PM TO 9:30 PM',
            'time_in' => '12:30',
            'time_out' => '21:30',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'PMB',
            'description' => 'PM buffer holidays',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'PM-BUFFER-1',
            'first_name' => 'Rey',
            'last_name' => 'Valdez',
            'email' => 'pm.buffer@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        return $employee;
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
}
