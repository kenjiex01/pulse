<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\ShiftCodeBreak;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Models\User;
use App\Services\PayrollAttendanceDayBreakdownService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAttendanceBreakLateBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_details_label_break_late_and_list_the_break_logs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-BRK-'.uniqid(),
            'first_name' => 'Teresa',
            'last_name' => 'Maghirang',
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'PM4T'.random_int(10, 99),
            'description' => 'PM4 test',
            'time_in' => '12:30:00',
            'time_out' => '21:30:00',
            'is_flexi_time' => false,
        ]);
        ShiftCodeBreak::query()->create([
            'shift_code_id' => $shift->shift_code_id,
            'shift_code_break_no' => 1,
            'shift_code_break_minute' => 60,
        ]);

        $policy = TimekeepingPolicy::query()->create([
            'policy_code' => 'BRK'.random_int(100, 999),
            'policy_name' => 'Break late test',
            'is_active' => true,
            'break_deduct_tardiness' => true,
            'break_computation' => 1,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->first()
            ?? TimekeepingHolidayGroup::query()->create([
                'timekeeping_holiday_group_code' => 'HG'.random_int(10, 99),
                'description' => 'Break late test',
            ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(1000, 9000),
            'dt_from' => '2026-10-27',
            'dt_to' => '2026-11-10',
            'calendar_month' => 11,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(1000, 9000),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-11-06',
            'day_type' => 'Regular',
            'shift_code_id' => $shift->shift_code_id,
            'time_in' => '10:38:23',
            'time_out' => '21:31:37',
            'late' => 0,
            'break_late' => 0.90,
        ]);

        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => '2026-11-06 10:38:23',
            'dt_to' => '2026-11-06 21:31:37',
            'uploaded_by_id' => $user->id,
            'batch_no' => 1,
        ]);

        foreach ([
            ['2026-11-06 10:38:23', true],
            ['2026-11-06 12:05:47', false],
            ['2026-11-06 12:53:15', true],
            ['2026-11-06 18:30:41', false],
            ['2026-11-06 19:38:31', true],
            ['2026-11-06 21:31:37', false],
        ] as [$at, $isIn]) {
            RawTimekeepingInandout::query()->create([
                'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                'employee_id' => $employee->employee_id,
                'dt_datetime' => $at,
                'is_in' => $isIn,
            ]);
        }

        $breakdown = app(PayrollAttendanceDayBreakdownService::class)->forDetail($detail->fresh([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
            'attendanceDays.shiftCode',
        ]));

        $row = $breakdown['LTDE'][0];
        $this->assertSame('break', $row['late_kind']);
        $this->assertSame(54, $row['minutes']);
        $this->assertSame(60, $row['break_allowed_minutes']);
        $this->assertSame(116, $row['break_consumed_minutes']);
        $this->assertSame([
            ['out' => '12:05', 'in' => '12:53', 'minutes' => 48],
            ['out' => '18:30', 'in' => '19:38', 'minutes' => 68],
        ], $row['break_logs']);

        $html = view('payroll.transaction._batch-employee-attendance-breakdown-modal', [
            'type' => 'LTDE',
            'rows' => $breakdown['LTDE'],
            'modalId' => 'payroll-batch-late-breakdown-modal',
        ])->render();

        $this->assertStringContainsString('Break late', $html);
        $this->assertStringContainsString('12:05 Out → 12:53 In (48 min)', $html);
        $this->assertStringContainsString('18:30 Out → 19:38 In (68 min)', $html);
        $this->assertStringContainsString('allowed 60 min', $html);
    }
}
