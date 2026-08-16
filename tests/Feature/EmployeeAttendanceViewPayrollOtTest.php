<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeOvertimeApproval;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicyTardiness;
use App\Models\User;
use App\Services\EmployeeAttendanceLogService;
use App\Services\EmployeeAttendanceViewService;
use App\Services\PayrollAttendanceDayBreakdownService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeAttendanceViewPayrollOtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function attendance_view_ot_matches_processed_batch_after_tardiness_offset(): void
    {
        $user = User::query()->firstOrFail();

        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'OTOF',
            'policy_name' => 'OT Offset Policy',
            'description' => 'Offset absent tardiness with OT',
            'is_active' => true,
            'grace_period' => 0,
            'is_offset_absent_tardiness_with_ot' => true,
        ]);

        TimekeepingPolicyTardiness::query()->create([
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
            'time_from' => 1,
            'time_to' => 9999,
            'equivalent' => 60,
            'marks_absent' => true,
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'AMOT',
            'description' => 'AM overtime test',
            'time_in' => '06:00',
            'time_out' => '15:00',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'OTG',
            'description' => 'OT test holiday group',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-OT-VIEW',
            'first_name' => 'Roselyn',
            'last_name' => 'Abrantes',
            'email' => 'ot.view@example.com',
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, CarbonImmutable::parse('2026-08-10 06:40:00'), true, $user->id);
        $logs->create($employee, CarbonImmutable::parse('2026-08-10 18:07:00'), false, $user->id);

        EmployeeOvertimeApproval::query()->create([
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-08-10',
            'ot_start' => '2026-08-10 15:00:00',
            'ot_end' => '2026-08-10 18:00:00',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 99,
            'dt_from' => '2026-07-27 00:00:00',
            'dt_to' => '2026-08-10 00:00:00',
            'calendar_month' => 8,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 4,
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
            'dt_processed' => now(),
            'processed_by_id' => $user->id,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        $batchOt = app(PayrollAttendanceDayBreakdownService::class)->forDetail($detail);
        $aug10Batch = collect($batchOt['OVRT'])->firstWhere('work_date', '2026-08-10');

        $this->assertNotNull($aug10Batch);
        $this->assertSame(120, (int) $aug10Batch['minutes']);

        $attendance = app(EmployeeAttendanceViewService::class)->monthForEmployee($employee, 2026, 8);
        $aug10View = collect($attendance['days'])->firstWhere('date', '2026-08-10');

        $this->assertNotNull($aug10View);
        $this->assertTrue($aug10View['in_payroll_batch']);
        $this->assertSame(2.0, (float) $aug10View['ot']);
        $this->assertSame(0.0, (float) $aug10View['late']);
    }
}
