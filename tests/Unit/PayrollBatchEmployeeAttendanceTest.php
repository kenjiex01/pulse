<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\IncomeType;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\User;
use App\Support\PayrollBatchEmployeeAttendance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBatchEmployeeAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_below_half_period_when_basc_days_are_low(): void
    {
        $detail = $this->makeDetail(faculty: false, daysPerPeriod: 12.0, hoursPerDay: 8.0);
        $this->addBascIncome($detail, days: 5.0, hours: 40.0);

        $this->assertTrue(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));
    }

    public function test_staff_not_below_half_when_basc_days_meet_threshold(): void
    {
        $detail = $this->makeDetail(faculty: false, daysPerPeriod: 12.0, hoursPerDay: 8.0);
        $this->addBascIncome($detail, days: 7.0, hours: 56.0);

        $this->assertFalse(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));
    }

    public function test_faculty_uses_hours_for_half_period_check(): void
    {
        $detail = $this->makeDetail(faculty: true, daysPerPeriod: 11.0, hoursPerDay: 8.0);
        $this->addBascIncome($detail, days: 11.0, hours: 40.0);

        $this->assertTrue(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));
    }

    public function test_attendance_days_fallback_when_no_basc_income(): void
    {
        $detail = $this->makeDetail(faculty: false, daysPerPeriod: 12.0, hoursPerDay: 8.0);

        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_id' => $detail->employee_id,
            'work_date' => '2026-06-27',
            'day_type' => 'Regular',
            'basic' => 8,
        ]);

        $this->assertTrue(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));
    }

    public function test_expected_working_days_exclude_employee_rest_days_in_period(): void
    {
        $detail = $this->makeDetail(
            faculty: false,
            daysPerPeriod: 12.0,
            hoursPerDay: 8.0,
            periodTo: '2026-07-11 00:00:00',
            restDayIds: [4],
            payPeriod: 21,
        );

        $calendar = $detail->payrollBatch->payrollCalendar;

        $this->assertSame(15.0, (float) ($calendar->dt_from->diffInDays($calendar->dt_to) + 1));
        $this->assertSame(2, PayrollBatchEmployeeAttendance::restDaysInPeriod($detail->employee, $calendar));
        $this->assertSame(13.0, PayrollBatchEmployeeAttendance::workingDaysInPeriod($detail->employee, $calendar));

        $this->addBascIncome($detail, days: 6.0, hours: 48.0);
        $this->assertTrue(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));

        $detail = $this->makeDetail(
            faculty: false,
            daysPerPeriod: 12.0,
            hoursPerDay: 8.0,
            periodTo: '2026-07-11 00:00:00',
            restDayIds: [4],
            payPeriod: 22,
        );
        $this->addBascIncome($detail, days: 7.0, hours: 56.0);
        $this->assertFalse(PayrollBatchEmployeeAttendance::isBelowHalfPayrollPeriod($this->refreshDetail($detail)));
    }

    /**
     * @param  list<int>  $restDayIds
     */
    private function makeDetail(
        bool $faculty,
        float $daysPerPeriod,
        float $hoursPerDay,
        string $periodTo = '2026-07-10 00:00:00',
        array $restDayIds = [],
        int $payPeriod = 1,
    ): PayrollBatchDetail {
        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ATT-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
        ]);

        $employment = $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => $faculty ? EmployeeEmploymentInformation::TYPE_FACULTY : EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => $daysPerPeriod,
            'hours_per_day' => $hoursPerDay,
            'date_effective_from' => '2026-01-01',
            'date_effective_to' => null,
        ]);

        foreach ($restDayIds as $dayId) {
            $employee->timekeepingRestDays()->create([
                'day_id' => $dayId,
                'is_paid' => false,
            ]);
        }

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => $payPeriod,
            'dt_from' => '2026-06-27 00:00:00',
            'dt_to' => $periodTo,
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 99,
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        return PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ])->fresh([
            'employee.employmentInformations.salary',
            'employee.employmentInformations.salaries',
            'employee.timekeepingRestDays',
            'payrollBatch.payrollCalendar',
            'incomes.incomeType',
            'attendanceDays',
        ]);
    }

    private function addBascIncome(PayrollBatchDetail $detail, float $days, float $hours): void
    {
        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $basicType->income_type_id,
            'days' => $days,
            'hours' => $hours,
            'taxable' => 1000,
            'non_taxable' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);
    }

    private function refreshDetail(PayrollBatchDetail $detail): PayrollBatchDetail
    {
        return $detail->fresh([
            'employee.employmentInformations.salary',
            'employee.employmentInformations.salaries',
            'employee.timekeepingRestDays',
            'payrollBatch.payrollCalendar',
            'incomes.incomeType',
            'attendanceDays',
        ]);
    }
}
