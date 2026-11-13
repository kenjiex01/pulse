<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\ShiftCodeBreak;
use App\Services\EmployeeAttendanceViewService;
use App\Services\TimeLogsPayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimeLogsIncompleteAndHalfDayOutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function in_logs_only_have_no_time_out_and_the_day_is_absent(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'IN-ONLY-1',
            'first_name' => 'Mary',
            'last_name' => 'Anda',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $this->punch($employee, '2026-10-20 07:31:00', true);
        $this->punch($employee, '2026-10-20 07:33:00', true);
        $this->punch($employee, '2026-10-20 12:53:00', true);

        $service = app(TimeLogsPayrollService::class);
        $session = $service->daySessionsForPeriod(
            (int) $employee->employee_id,
            CarbonImmutable::parse('2026-10-20'),
            CarbonImmutable::parse('2026-10-20'),
        )->first();

        $this->assertSame('07:31:00', $session['time_in']);
        $this->assertNull($session['time_out']);

        $resolved = $service->resolvedLateForSession($session, null, '08:00', 0, $this->dayShift());

        $this->assertTrue($resolved['is_absent']);
        $this->assertSame(0, $resolved['billable_minutes']);
    }

    #[Test]
    public function an_out_a_few_minutes_after_in_is_ignored(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'IN-CLOSE-1',
            'first_name' => 'Close',
            'last_name' => 'Out',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $this->punch($employee, '2026-10-20 07:31:00', true);
        $this->punch($employee, '2026-10-20 07:34:00', false);

        $session = app(TimeLogsPayrollService::class)->daySessionsForPeriod(
            (int) $employee->employee_id,
            CarbonImmutable::parse('2026-10-20'),
            CarbonImmutable::parse('2026-10-20'),
        )->first();

        $this->assertNull($session['time_out']);
    }

    #[Test]
    public function leaving_at_lunch_is_half_day_absent_not_the_whole_afternoon_as_undertime(): void
    {
        $service = app(TimeLogsPayrollService::class);
        $session = $this->workSession('07:31:00', '12:53:00');
        $window = $service->halfDayEarlyOutWindow($session, '08:00', '17:00', $this->dayShift());

        $this->assertNotNull($window);
        $this->assertSame(240, $window['absent_minutes']);
        $this->assertSame(0, $window['undertime_minutes']);
    }

    #[Test]
    public function attendance_grid_tags_a_1253_out_as_half_day_absent_without_undertime(): void
    {
        $metrics = app(EmployeeAttendanceViewService::class)->metricsForTimes(
            1,
            CarbonImmutable::parse('2026-10-20'),
            '07:31:00',
            '12:53:00',
            null,
            $this->dayShift(),
        );

        $this->assertTrue($metrics['is_half_day']);
        $this->assertFalse($metrics['is_absent']);
        $this->assertFalse($metrics['late_marks_absent']);
        $this->assertSame(0.0, $metrics['undertime']);
        $this->assertEqualsWithDelta(8.0, $metrics['basic'], 0.01);
    }

    #[Test]
    public function three_hours_worked_is_half_day_absent_plus_one_hour_undertime(): void
    {
        $service = app(TimeLogsPayrollService::class);
        $resolved = $service->resolvedUndertimeForSession(
            $this->workSession('08:00:00', '11:00:00'),
            null,
            '17:00',
            '08:00',
            $this->dayShift(),
        );

        $this->assertTrue($resolved['is_half_day']);
        $this->assertSame(240, $resolved['half_day_absent_minutes']);
        $this->assertSame(60, $resolved['raw_minutes']);
    }

    #[Test]
    public function five_hours_worked_is_ordinary_undertime_not_half_day(): void
    {
        $service = app(TimeLogsPayrollService::class);
        $resolved = $service->resolvedUndertimeForSession(
            $this->workSession('08:00:00', '14:00:00'),
            null,
            '17:00',
            '08:00',
            $this->dayShift(),
        );

        $this->assertFalse($resolved['is_half_day']);
        $this->assertSame(180, $resolved['raw_minutes']);
    }

    private function dayShift(): ShiftCode
    {
        $shift = new ShiftCode([
            'shift_code' => 'AM2',
            'time_in' => '08:00',
            'time_out' => '17:00',
            'is_flexi_time' => false,
        ]);
        $shift->setRelation('breaks', new Collection([
            new ShiftCodeBreak([
                'break_out' => '12:00',
                'break_in' => '13:00',
                'shift_code_break_minute' => 60,
            ]),
        ]));

        return $shift;
    }

    /**
     * @return array{date: CarbonImmutable, time_in: string, time_out: string}
     */
    private function workSession(string $timeIn, string $timeOut): array
    {
        return [
            'date' => CarbonImmutable::parse('2026-10-20'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
        ];
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
