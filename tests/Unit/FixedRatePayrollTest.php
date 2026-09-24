<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedRatePayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_fixed_rate_pays_full_basic_income_without_attendance_deductions(): void
    {
        $detail = $this->makeDetail(fixedRate: true);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basic = $this->basicIncome($detail);

        $this->assertNotNull($basic);
        $this->assertSame(10000.0, (float) $basic->taxable);
        $this->assertSame(0.0, (float) $basic->non_taxable);
        $this->assertSame(0, $this->attendanceDeductionCount($detail));
    }

    public function test_time_in_out_without_fixed_rate_does_not_pay_unworked_basic(): void
    {
        $detail = $this->makeDetail(fixedRate: false);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basic = $this->basicIncome($detail);

        $this->assertNotNull($basic);
        $this->assertSame(0.0, (float) $basic->taxable);
    }

    private function makeDetail(bool $fixedRate): PayrollBatchDetail
    {
        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-FIX-'.uniqid(),
            'first_name' => 'Fixed',
            'last_name' => 'Rate',
        ]);

        $employment = $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 11,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'date_effective_to' => null,
            'is_fixed_rate' => $fixedRate,
        ]);

        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 10000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        return PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);
    }

    private function basicIncome(PayrollBatchDetail $detail): ?PayrollIncome
    {
        return PayrollIncome::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'))
            ->first();
    }

    private function attendanceDeductionCount(PayrollBatchDetail $detail): int
    {
        $typeIds = DeductionType::query()
            ->whereIn('deduction_type_code', ['LTDE', 'UTDE'])
            ->pluck('deduction_type_id');

        return PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->whereIn('deduction_type_id', $typeIds)
            ->count();
    }
}
