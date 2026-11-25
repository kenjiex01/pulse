<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\EmploymentType;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\PayrollCalendarDeduction;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\User;
use App\Services\GovernmentDeductionPayrollService;
use App\Support\SssDeductionTypes;
use Database\Seeders\GovernmentTablesSeeder;
use Database\Seeders\PayTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmploymentTypeGovernmentDeductionTest extends TestCase
{
    use RefreshDatabase;

    private GovernmentDeductionPayrollService $service;

    private DeductionType $sssType;

    private Employee $employee;

    private User $user;

    private IncomeType $incomeType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PayTypeSeeder::class);
        $this->seed(GovernmentTablesSeeder::class);

        Schema::disableForeignKeyConstraints();

        DB::table('lu_payroll_batch_status')->insertOrIgnore([
            'payroll_batch_status_id' => 1,
            'payroll_batch_status' => 'Open',
        ]);

        DB::table('lu_withholding_tax_computations')->insertOrIgnore([
            'withholding_tax_computation_id' => 1,
            'withholding_tax_computation' => 'Regular',
        ]);

        $this->user = User::query()->create([
            'name' => 'Gov Deduction Tester',
            'email' => 'gov-deduction@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->service = app(GovernmentDeductionPayrollService::class);

        $this->sssType = DeductionType::query()->create([
            'deduction_type_code' => SssDeductionTypes::PREMIUM,
            'description' => 'SSS Premium',
            'employer_amount' => 0,
            'is_valid_govt_deduction' => true,
            'govt_table_id' => GovernmentDeductionPayrollService::GOVT_SSS,
            'is_active' => true,
        ]);

        $this->incomeType = IncomeType::query()->create([
            'income_type_code' => 'BASC',
            'description' => 'Basic Income',
            'is_default_basic' => true,
            'is_active' => true,
        ]);

        $campus = Campus::query()->create([
            'campus_code' => 'GD',
            'campus_name' => 'Gov Deduction Campus',
            'is_active' => true,
        ]);

        $this->employee = Employee::query()->create([
            'employee_number' => 'GOV-DED-001',
            'first_name' => 'Gov',
            'last_name' => 'Deduction',
            'email' => 'govded@example.com',
            'phone' => '09170000111',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);
    }

    #[Test]
    public function calendar_government_deductions_are_skipped_when_employment_type_is_unticked(): void
    {
        $this->assignEmploymentType('Contractual', 'CONTRACT', allows: false);
        $batch = $this->batchWithGovernmentDeduction();

        $lines = $this->service->computeForDetail(
            $this->detailWithIncome($batch),
            $batch,
        );

        $this->assertTrue($lines->isEmpty());
    }

    #[Test]
    public function government_deductions_apply_when_calendar_and_employment_type_are_both_checked(): void
    {
        $this->assignEmploymentType('Regular', 'REG', allows: true);
        $batch = $this->batchWithGovernmentDeduction();

        $lines = $this->service->computeForDetail(
            $this->detailWithIncome($batch),
            $batch,
        );

        $this->assertCount(1, $lines);
        $this->assertSame((int) $this->sssType->deduction_type_id, $lines->first()['deduction_type_id']);
        $this->assertGreaterThan(0, $lines->first()['employee_amount']);
    }

    #[Test]
    public function paid_salary_employment_type_overrides_another_type_that_allows_deductions(): void
    {
        $this->assignEmploymentType('Regular', 'REG', allows: true);
        $casual = $this->assignEmploymentType('Casual', 'CASUAL', allows: false, sortOrder: 1);

        EmployeeSalary::query()->create([
            'employment_info_id' => $casual->employment_info_id,
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'date_effective_from' => '2026-06-01',
            'date_effective_to' => null,
        ]);

        $batch = $this->batchWithGovernmentDeduction();

        $lines = $this->service->computeForDetail(
            $this->detailWithIncome($batch),
            $batch,
        );

        $this->assertTrue($lines->isEmpty());
    }

    #[Test]
    public function refresh_removes_government_deductions_when_employment_type_is_unticked(): void
    {
        $this->assignEmploymentType('Temporary', 'TEMP', allows: false);
        $batch = $this->batchWithGovernmentDeduction();
        $detail = $this->detailWithIncome($batch);

        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => $this->sssType->deduction_type_id,
            'employee_amount' => 500,
            'employer_amount' => 1000,
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ]);

        $this->service->refreshGovernmentDeductionsForDetail($detail, $batch);

        $this->assertSame(0, $detail->deductions()->count());
    }

    private function assignEmploymentType(
        string $name,
        string $code,
        bool $allows,
        int $sortOrder = 0,
    ): EmployeeEmploymentInformation {
        EmploymentType::query()->create([
            'type_code' => $code,
            'type_name' => $name,
            'is_active' => true,
            'deduct_government_deductions' => $allows,
        ]);

        return EmployeeEmploymentInformation::query()->create([
            'employee_id' => $this->employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'employment_type' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    private function batchWithGovernmentDeduction(): PayrollBatch
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 1,
            'dt_from' => '2026-06-01',
            'dt_to' => '2026-06-15',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        PayrollCalendarDeduction::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'deduction_type_id' => $this->sssType->deduction_type_id,
        ]);

        return PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 1,
            'created_by_id' => $this->user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => 1,
            'withholding_tax_computation_id' => 1,
        ]);
    }

    private function detailWithIncome(?PayrollBatch $batch = null): PayrollBatchDetail
    {
        $batch ??= $this->batchWithGovernmentDeduction();

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $this->employee->employee_id,
        ]);

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $this->incomeType->income_type_id,
            'taxable' => 20000,
            'non_taxable' => 0,
            'is_manual' => false,
            'is_editable' => true,
            'is_deletable' => true,
        ]);

        return $detail;
    }
}
