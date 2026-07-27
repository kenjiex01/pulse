<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
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

class GovernmentDeductionMonthlyTrueUpTest extends TestCase
{
    use RefreshDatabase;

    private GovernmentDeductionPayrollService $service;

    private DeductionType $sssType;

    private Employee $employee;

    private User $user;

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
            'name' => 'True Up Tester',
            'email' => 'trueup-tester@example.com',
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

        $campus = Campus::query()->create([
            'campus_code' => 'T',
            'campus_name' => 'Test Campus',
            'is_active' => true,
        ]);

        $this->employee = Employee::query()->create([
            'employee_number' => 'TRUE-UP-001',
            'first_name' => 'True',
            'last_name' => 'Up',
            'email' => 'trueup@example.com',
            'phone' => '09170000099',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);
    }

    #[Test]
    public function net_of_prior_subtracts_earlier_period_amounts(): void
    {
        $result = $this->service->netOfPriorDeductions(
            ['employee_amount' => 1000.0, 'employer_amount' => 2030.0],
            $this->employeeDetailForPeriod(2, withPriorSss: true),
            $this->batchForPeriod(2),
            (int) $this->sssType->deduction_type_id,
        );

        $this->assertSame(400.0, $result['employee_amount']);
        $this->assertSame(1015.0, $result['employer_amount']);
    }

    #[Test]
    public function first_period_keeps_full_monthly_due(): void
    {
        $result = $this->service->netOfPriorDeductions(
            ['employee_amount' => 600.0, 'employer_amount' => 1210.0],
            $this->employeeDetailForPeriod(1, withPriorSss: false),
            $this->batchForPeriod(1),
            (int) $this->sssType->deduction_type_id,
        );

        $this->assertSame(600.0, $result['employee_amount']);
        $this->assertSame(1210.0, $result['employer_amount']);
    }

    #[Test]
    public function sss_true_up_uses_month_to_date_gross_minus_prior(): void
    {
        $detail = $this->employeeDetailForPeriod(2, withPriorSss: true);
        $batch = $this->batchForPeriod(2);

        $due = $this->service->computeSssRegular(20000);
        $net = $this->service->netOfPriorDeductions(
            $due,
            $detail,
            $batch,
            (int) $this->sssType->deduction_type_id,
        );

        $this->assertSame(1000.0, $due['employee_amount']);
        $this->assertSame(400.0, $net['employee_amount']);
        $this->assertSame(1015.0, $net['employer_amount']);
    }

    private function batchForPeriod(int $payPeriod): PayrollBatch
    {
        $calendar = PayrollCalendar::query()->firstOrCreate(
            [
                'pay_type_id' => PayType::SEMI_MONTHLY,
                'pay_year' => 2026,
                'pay_period' => $payPeriod,
            ],
            [
                'dt_from' => $payPeriod === 1 ? '2026-06-01' : '2026-06-16',
                'dt_to' => $payPeriod === 1 ? '2026-06-15' : '2026-06-30',
                'calendar_month' => 6,
                'is_regular_period' => true,
            ],
        );

        return PayrollBatch::query()->firstOrCreate(
            ['payroll_calendar_id' => $calendar->payroll_calendar_id],
            [
                'batch_no' => $payPeriod,
                'created_by_id' => $this->user->id,
                'dt_created' => now(),
                'payroll_batch_status_id' => 1,
                'withholding_tax_computation_id' => 1,
            ],
        );
    }

    private function employeeDetailForPeriod(int $payPeriod, bool $withPriorSss): PayrollBatchDetail
    {
        if ($withPriorSss) {
            $priorBatch = $this->batchForPeriod(1);
            $priorDetail = PayrollBatchDetail::query()->firstOrCreate(
                [
                    'payroll_batch_id' => $priorBatch->payroll_batch_id,
                    'employee_id' => $this->employee->employee_id,
                ],
            );

            PayrollDeduction::query()->firstOrCreate(
                [
                    'payroll_batch_detail_id' => $priorDetail->payroll_batch_detail_id,
                    'deduction_type_id' => $this->sssType->deduction_type_id,
                ],
                [
                    'employee_amount' => 600,
                    'employer_amount' => 1015,
                    'is_manual' => false,
                    'is_editable' => true,
                    'is_deletable' => true,
                ],
            );
        }

        $batch = $this->batchForPeriod($payPeriod);

        return PayrollBatchDetail::query()->firstOrCreate(
            [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $this->employee->employee_id,
            ],
        );
    }
}
