<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\User;
use App\Services\PayrollBatchService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyPayrollWithoutTeachingLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_faculty_batch_with_punches_and_no_teaching_loads_pays_zero_basic(): void
    {
        $detail = $this->hybridOnFacultyCalendar();
        $this->punchDay($detail->employee, '2026-10-01');

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basic = $this->basicIncome($detail);

        $this->assertNotNull($basic);
        $this->assertSame(0.0, (float) $basic->taxable);
        $this->assertSame(0.0, (float) $basic->non_taxable);
        $this->assertNull($basic->hours);
        $this->assertNull($basic->days);
    }

    public function test_faculty_batch_pays_teaching_load_hours_not_shift_punches(): void
    {
        $detail = $this->hybridOnFacultyCalendar();
        $this->punchDay($detail->employee, '2026-10-01');
        $this->teachingLoad($detail->employee, '2026-10-01', 1.5);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basic = $this->basicIncome($detail);

        // 2,080 / 26 days / 8 hours = 10 per hour. 1.5 class hours, not the 8-hour punch.
        $this->assertNotNull($basic);
        $this->assertSame(15.0, (float) $basic->taxable);
        $this->assertSame(1.5, (float) $basic->hours);
        $this->assertSame(1.0, (float) $basic->days);
    }

    private function hybridOnFacultyCalendar(): PayrollBatchDetail
    {
        $user = User::query()->firstOrFail();
        $suffix = substr(uniqid(), -6);

        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'FT'.$suffix,
            'policy_name' => 'Faculty load policy '.$suffix,
            'description' => 'Faculty payroll without teaching loads',
            'is_active' => true,
            'grace_period' => 0,
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'FS'.$suffix,
            'description' => 'Faculty 8h',
            'time_in' => '08:00',
            'time_out' => '16:00',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'HG'.$suffix,
            'description' => 'Faculty holiday group',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'FAC-LOAD-'.$suffix,
            'first_name' => 'Anna',
            'last_name' => 'Faculty',
            'is_hybrid' => true,
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $faculty = $employee->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 1,
        ]);
        $staff = $employee->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 2,
        ]);

        $facultySalary = $faculty->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'is_fixed_rate' => false,
            'use_basic_income_as_hourly_rate' => false,
        ]);
        $facultySalary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 2080,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $staffSalary = $staff->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'is_fixed_rate' => false,
            'use_basic_income_as_hourly_rate' => true,
        ]);
        $staffSalary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 170,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-10-01 00:00:00',
            'dt_to' => '2026-10-15 00:00:00',
            'calendar_month' => 10,
            'is_regular_period' => true,
        ]);
        $calendar->userTypes()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        return $detail->load('employee');
    }

    private function punchDay(Employee $employee, string $date): void
    {
        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => $date.' 08:00:00',
            'dt_to' => $date.' 16:00:00',
            'uploaded_by_id' => User::query()->value('id'),
            'batch_no' => 1,
        ]);

        foreach (['08:00:00' => true, '16:00:00' => false] as $time => $isIn) {
            RawTimekeepingInandout::query()->create([
                'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                'employee_id' => $employee->employee_id,
                'dt_datetime' => $date.' '.$time,
                'is_in' => $isIn,
            ]);
        }
    }

    private function teachingLoad(Employee $employee, string $date, float $hours): void
    {
        $transaction = RawEmployeeLoadTransaction::query()->create([
            'batch_no' => 1,
            'dt_from' => $date,
            'dt_to' => $date,
            'uploaded_by_id' => User::query()->value('id'),
        ]);

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transaction->employee_load_transaction_id,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => $date,
            'class_schedule' => '08:00 - 09:30',
            'total_hours' => $hours,
        ]);
    }

    private function basicIncome(PayrollBatchDetail $detail): ?PayrollIncome
    {
        return PayrollIncome::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'))
            ->first();
    }
}
