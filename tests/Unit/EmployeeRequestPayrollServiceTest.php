<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeOvertimeApproval;
use App\Models\EmployeeRequest;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Models\User;
use App\Services\EmployeeRequestPayrollService;
use Carbon\Carbon;
use Database\Seeders\PayTypeSeeder;
use Database\Seeders\PayrollBatchStatusSeeder;
use Database\Seeders\PayrollMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeRequestPayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_a_past_request_approved_this_cutoff_is_paid_once_on_the_current_batch(): void
    {
        $this->seed(PayTypeSeeder::class);
        $this->seed(PayrollMaintenanceSeeder::class);
        $this->seed(PayrollBatchStatusSeeder::class);

        $user = User::factory()->create();
        $employee = Employee::query()->create([
            'employee_number' => '25-ACCO150189',
            'first_name' => 'Marvin',
            'middle_name' => 'Jayrus',
            'last_name' => 'Evangelio',
            'email' => 'marvin.request@example.com',
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'REQ8',
            'description' => 'Request 8h',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'is_flexi_time' => false,
        ]);
        $policy = TimekeepingPolicy::query()->create([
            'policy_code' => 'REQ',
            'policy_name' => 'Request Policy',
            'description' => 'Employee request payroll',
            'is_active' => true,
            'grace_period' => 0,
        ]);
        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'REQG',
            'description' => 'Request holidays',
        ]);
        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $employment = $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);
        $salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
        ]);
        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 10000,
            'non_taxable' => 0,
        ]);

        $this->punch($user->id, $employee->employee_id, '2026-09-08 08:00:00', true);
        $this->punch($user->id, $employee->employee_id, '2026-09-08 19:00:00', false);
        EmployeeOvertimeApproval::query()->create([
            'employee_id' => $employee->employee_id,
            'work_date' => '2026-09-08',
            'ot_start' => '2026-09-08 17:00:00',
            'ot_end' => '2026-09-08 19:00:00',
        ]);

        EmployeeRequest::query()->create([
            'skolaris_request_id' => 801,
            'request_number' => 'JOBORDER-20261112-0001',
            'request_type_code' => 'job_order',
            'request_type_name' => 'Job Order',
            'status_code' => 'approved',
            'prepared_by_name' => 'Julie Lo Ang',
            'approved_at' => '2026-11-12 08:00:00',
            'applied_at' => '2026-11-12 08:05:00',
            'pulled_at' => '2026-11-12 08:05:00',
            'items' => [[
                'employee_id' => 'skolaris-339',
                'employee_name' => 'Marvin Jayrus Evangelio',
                'work_schedules' => [[
                    'date_from' => '2026-09-08',
                    'date_to' => '2026-09-08',
                    'time_from' => '17:00',
                    'time_to' => '19:00',
                ]],
            ]],
        ]);
        EmployeeRequest::query()->create([
            'skolaris_request_id' => 802,
            'request_number' => 'LEAVEOFABSENCE-20261112-0001',
            'request_type_code' => 'leave_of_absence',
            'request_type_name' => 'Leave of Absence',
            'status_code' => 'approved',
            'prepared_by_employee_number' => '25-ACCO150189',
            'prepared_by_name' => 'Marvin Jayrus Evangelio',
            'approved_at' => '2026-11-12 09:00:00',
            'applied_at' => '2026-11-12 09:05:00',
            'pulled_at' => '2026-11-12 09:05:00',
            'form_payload' => [
                'leave_type_label' => 'Vacation Leave',
                'leave_rows' => [[
                    'date_of_leave' => '2026-09-09',
                    'num_days' => '1',
                ]],
            ],
        ]);
        EmployeeRequest::query()->create([
            'skolaris_request_id' => 803,
            'request_number' => 'JOBORDER-20261120-0001',
            'request_type_code' => 'job_order',
            'request_type_name' => 'Job Order',
            'status_code' => 'approved',
            'approved_at' => '2026-11-20 08:00:00',
            'applied_at' => '2026-11-20 08:05:00',
            'pulled_at' => '2026-11-20 08:05:00',
            'items' => [[
                'employee_name' => 'Marvin Jayrus Evangelio',
                'work_schedules' => [[
                    'date_from' => '2026-09-10',
                    'time_from' => '17:00',
                    'time_to' => '19:00',
                ]],
            ]],
        ]);

        $detail = $this->detail($user->id, $employee->employee_id, '2026-11-01', '2026-11-15', 15);
        $service = app(EmployeeRequestPayrollService::class);
        $service->applyToDetail($detail);

        $overtime = PayrollIncome::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', 'OVRT')->value('income_type_id'))
            ->get();
        $leave = PayrollIncome::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('remarks', 'like', 'Vacation Leave%')
            ->first();

        $this->assertCount(1, $overtime);
        $this->assertSame(250.0, (float) $overtime[0]->taxable);
        $this->assertSame(2.0, (float) $overtime[0]->hours);
        $this->assertStringContainsString('Sep 8, 2026', (string) $overtime[0]->remarks);
        $this->assertNotNull($leave);
        $this->assertSame(1000.0, (float) $leave->taxable);
        $this->assertSame(0, PayrollIncome::query()->where('remarks', 'like', '%Sep 10, 2026%')->count());

        $later = $this->detail($user->id, $employee->employee_id, '2026-12-01', '2026-12-15', 16);
        $service->applyToDetail($later);

        $this->assertSame(0, PayrollIncome::query()->where('payroll_batch_detail_id', $later->payroll_batch_detail_id)->count());
    }

    private function detail(int $userId, int $employeeId, string $from, string $to, int $batchNo): PayrollBatchDetail
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => $batchNo,
            'dt_from' => $from.' 00:00:00',
            'dt_to' => $to.' 00:00:00',
            'calendar_month' => (int) substr($from, 5, 2),
            'is_regular_period' => true,
        ]);
        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => $batchNo,
            'created_by_id' => $userId,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        return PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employeeId,
        ]);
    }

    private function punch(int $userId, int $employeeId, string $datetime, bool $isIn): void
    {
        $transaction = RawTimekeepingTransaction::query()->firstOrCreate(
            ['batch_no' => 9101],
            [
                'timekeeping_transaction_type_id' => 1,
                'dt_from' => '2026-09-01',
                'dt_to' => '2026-09-30',
                'uploaded_by_id' => $userId,
            ],
        );

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => $employeeId,
            'dt_datetime' => Carbon::parse($datetime),
            'is_in' => $isIn,
            'timekeeping_trantype' => 1,
        ]);
    }
}
