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
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBatchAllowancePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_allowance_only_period_skips_basic_income_lines(): void
    {
        $detail = $this->makeDetail(allowanceOnly: true);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basicTypeId = IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');
        $allowanceTypeId = IncomeType::query()->where('income_type_code', 'DEMN')->value('income_type_id');

        $this->assertNull($this->incomeForType($detail, (int) $basicTypeId));

        $allowance = $this->incomeForType($detail, (int) $allowanceTypeId);
        $this->assertNotNull($allowance);
        $this->assertSame(500.0, (float) $allowance->non_taxable);
    }

    public function test_regular_only_period_skips_allowance_income_lines(): void
    {
        $detail = $this->makeDetail(allowanceOnly: false);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $basicTypeId = IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');
        $allowanceTypeId = IncomeType::query()->where('income_type_code', 'DEMN')->value('income_type_id');

        $basic = $this->incomeForType($detail, (int) $basicTypeId);
        $this->assertNotNull($basic);
        $this->assertSame(10000.0, (float) $basic->taxable);

        $this->assertNull($this->incomeForType($detail, (int) $allowanceTypeId));
    }

    private function makeDetail(bool $allowanceOnly): PayrollBatchDetail
    {
        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ALW-'.uniqid(),
            'first_name' => 'Allow',
            'last_name' => 'Period',
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
            'is_fixed_rate' => true,
        ]);

        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 10000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'DEMN')->value('income_type_id'),
            'taxable' => 0,
            'non_taxable' => 500,
            'sort_order' => 1,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => $allowanceOnly ? null : true,
            'is_allowance_period' => $allowanceOnly ? true : null,
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

    private function incomeForType(PayrollBatchDetail $detail, int $incomeTypeId): ?PayrollIncome
    {
        return PayrollIncome::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('income_type_id', $incomeTypeId)
            ->first();
    }
}
