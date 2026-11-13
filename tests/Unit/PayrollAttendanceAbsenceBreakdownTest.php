<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\User;
use App\Services\PayrollAttendanceDayBreakdownService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollAttendanceAbsenceBreakdownTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saved_half_day_absent_appears_in_abst_breakdown(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ABST-1',
            'first_name' => 'Abby',
            'last_name' => 'Sent',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 904,
            'dt_from' => '2026-08-01 00:00:00',
            'dt_to' => '2026-08-15 00:00:00',
            'calendar_month' => 8,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 9200,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
            'withholding_tax_computation_id' => 1,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-08-05',
            'day_type' => 'Half-day Absent',
            'time_in' => '12:53:00',
            'time_out' => '17:00:00',
        ]);

        $breakdown = app(PayrollAttendanceDayBreakdownService::class)->forDetail($detail->fresh([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
            'attendanceDays.shiftCode',
        ]));

        $this->assertCount(1, $breakdown['ABST']);
        $this->assertSame('Half-day', $breakdown['ABST'][0]['absence_type']);
        $this->assertSame(0.5, $breakdown['ABST'][0]['days']);
        $this->assertSame('2026-08-05', $breakdown['ABST'][0]['work_date']);
    }

    #[Test]
    public function blank_regular_day_is_listed_with_full_absences_when_calendar_days_are_used(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ABST-2',
            'first_name' => 'Blank',
            'last_name' => 'Day',
        ]);

        $employment = $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 11,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'use_payroll_calendar_days' => true,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 905,
            'dt_from' => '2026-10-22 00:00:00',
            'dt_to' => '2026-10-23 00:00:00',
            'calendar_month' => 10,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 9201,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
            'withholding_tax_computation_id' => 1,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-10-22',
            'day_type' => 'Absent',
            'time_in' => '06:37:00',
        ]);

        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-10-23',
            'day_type' => 'Regular',
        ]);

        $breakdown = app(PayrollAttendanceDayBreakdownService::class)->forDetail($detail->fresh([
            'payrollBatch.payrollCalendar',
            'employee.timekeepingSetup.policy',
            'employee.timekeepingSetup.shiftCode',
            'attendanceDays.shiftCode',
        ]));

        $dates = array_column($breakdown['ABST'], 'work_date');

        $this->assertSame(['2026-10-22', '2026-10-23'], $dates);
        $this->assertEquals(2.0, array_sum(array_column($breakdown['ABST'], 'days')));
    }
}
