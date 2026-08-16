<?php

namespace Tests\Unit;

use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Services\EmployeeAttendanceLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeAttendanceLogServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function first_edit_preserves_original_values_and_marks_log_as_edited(): void
    {
        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => 1,
            'dt_from' => '2026-06-01',
            'dt_to' => '2026-06-30',
            'uploaded_by_id' => 1,
            'batch_no' => 15,
        ]);

        $log = RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => 6,
            'dt_datetime' => Carbon::parse('2026-06-15 16:01:00'),
            'is_in' => true,
            'timekeeping_trantype' => 1,
        ]);

        $service = new EmployeeAttendanceLogService;
        $updated = $service->update(
            $log,
            Carbon::parse('2026-06-15 16:05:00'),
            false,
            1,
        );

        $this->assertTrue($updated->is_edited);
        $this->assertSame('2026-06-15 16:01:00', $updated->original_dt_datetime?->format('Y-m-d H:i:s'));
        $this->assertTrue($updated->original_is_in);
        $this->assertSame('2026-06-15 16:05:00', $updated->dt_datetime?->format('Y-m-d H:i:s'));
        $this->assertFalse($updated->is_in);
    }

    #[Test]
    public function calendar_month_shows_first_in_and_last_out_per_day(): void
    {
        $employeeId = 6;

        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => 1,
            'dt_from' => '2026-08-01',
            'dt_to' => '2026-08-31',
            'uploaded_by_id' => 1,
            'batch_no' => 18,
        ]);

        foreach ([
            ['2026-08-07 07:52:00', true],
            ['2026-08-07 13:18:00', true],
            ['2026-08-07 18:14:00', false],
            ['2026-08-07 20:01:00', false],
        ] as [$datetime, $isIn]) {
            RawTimekeepingInandout::query()->create([
                'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                'employee_id' => $employeeId,
                'dt_datetime' => Carbon::parse($datetime),
                'is_in' => $isIn,
                'timekeeping_trantype' => 1,
            ]);
        }

        $employee = new \App\Models\Employee;
        $employee->employee_id = $employeeId;

        $calendar = (new EmployeeAttendanceLogService)->calendarMonth($employee, 2026, 8);
        $day = collect($calendar['weeks'])->flatten(1)->firstWhere('date', '2026-08-07');

        $this->assertNotNull($day);
        $this->assertTrue($day['has_logs']);
        $this->assertSame('7:52 AM', $day['first_in']);
        $this->assertSame('8:01 PM', $day['last_out']);
        $this->assertSame(4, $day['log_count']);
        $this->assertCount(4, $calendar['days']['2026-08-07']['logs']);
    }
}
