<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeLoan;
use App\Models\IncomeType;
use App\Models\LoanType;
use App\Models\PaymentScheme;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayrollLoanPayment;
use App\Models\PayType;
use App\Models\User;
use App\Support\PayrollTransactionModule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBatchEmployeeNetPayColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_batch_employee_list_shows_net_pay_after_employee_name(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-NET-1',
            'first_name' => 'Anna',
            'last_name' => 'Netpay',
        ]);
        $employee->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
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

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 7350,
            'non_taxable' => 200,
        ]);

        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => DeductionType::query()->value('deduction_type_id'),
            'employee_amount' => 558.75,
            'employer_amount' => 999.99,
        ]);

        $loan = EmployeeLoan::query()->create([
            'employee_id' => $employee->employee_id,
            'loan_type_id' => LoanType::query()->value('loan_type_id'),
            'payment_scheme_id' => PaymentScheme::USER_ENTERED_AMORTIZATION,
            'dt_loan' => '2026-05-01',
            'loan_amount' => 5000,
            'amortization_amount' => 100,
            'dt_start_payment' => '2026-06-01',
        ]);

        PayrollLoanPayment::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'employee_loan_id' => $loan->employee_loan_id,
            'payment' => 100,
            'penalty' => 25,
        ]);

        // 7,350 + 200 − 558.75 − 100 − 25. Employer share stays out of net pay.
        $this->actingAs($user)
            ->get(route(PayrollTransactionModule::routeName('tab'), [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
            ]))
            ->assertOk()
            ->assertSeeInOrder(['Employee Name', 'Net Pay', 'Actions'])
            ->assertSeeInOrder(['Anna Netpay', '6,866.25']);
    }
}
