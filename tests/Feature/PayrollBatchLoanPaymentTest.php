<?php

namespace Tests\Feature;

use App\Models\BasicComputation;
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
use App\Models\PayrollLoanPayment;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\SysLog;
use App\Models\User;
use App\Services\LoanPayrollService;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollBatchLoanPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PayrollBatch $batch;

    private PayrollBatchDetail $detail;

    private EmployeeLoan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-LOAN-1',
            'first_name' => 'Lorna',
            'last_name' => 'Loaner',
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
            'is_fixed_rate' => true,
        ]);

        $salary->incomes()->create([
            'income_type_id' => (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 10000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 902,
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $loanTypeId = (int) LoanType::query()->where('loan_type_code', 'SSAL')->value('loan_type_id');
        $calendar->loans()->create(['loan_type_id' => $loanTypeId]);

        $this->loan = EmployeeLoan::query()->create([
            'employee_id' => $employee->employee_id,
            'loan_type_id' => $loanTypeId,
            'payment_scheme_id' => PaymentScheme::USER_ENTERED_AMORTIZATION,
            'dt_loan' => '2026-05-01',
            'loan_amount' => 5000,
            'amortization_amount' => 1000,
            'dt_start_payment' => '2026-06-01',
        ]);

        $this->batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 902,
            'created_by_id' => $this->user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $this->detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $this->batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);
        $this->actingAs($this->user);
    }

    private function url(string $suffix = ''): string
    {
        return '/payroll/transaction/batches/'.$this->batch->payroll_batch_id
            .'/employees/'.$this->detail->payroll_batch_detail_id.'/loans'.$suffix;
    }

    private function payments()
    {
        return PayrollLoanPayment::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('employee_loan_id', $this->loan->employee_loan_id);
    }

    #[Test]
    public function manual_loan_deduction_replaces_auto_line_and_appears_in_loan_history(): void
    {
        app(LoanPayrollService::class)->syncForDetail($this->detail);
        $this->assertSame(1, $this->payments()->where('is_manual', false)->count());

        $this->post($this->url(), [
            'employee_loan_id' => $this->loan->employee_loan_id,
            'payment' => 1500,
            'penalty' => 50,
        ])->assertSessionHas('success');

        $this->assertSame(0, $this->payments()->where('is_manual', false)->count());
        $manual = $this->payments()->where('is_manual', true)->firstOrFail();
        $this->assertSame(1500.0, (float) $manual->payment);
        $this->assertSame(50.0, (float) $manual->penalty);
        $this->assertSame(3450.0, $this->loan->fresh()->loanBalance());
        $this->assertTrue(SysLog::query()->where('table_name', 'trn_payroll_loan_payments')->where('action', 'create')->exists());

        app(LoanPayrollService::class)->syncForDetail($this->detail->fresh());
        $this->assertSame(1, $this->payments()->count());

        $this->get(route('payroll.transaction.tab', [
            'tab' => 'batches',
            'view_payroll_batch' => $this->batch->payroll_batch_id,
            'view_batch_detail' => $this->detail->payroll_batch_detail_id,
            'batch_detail_tab' => 'loans',
        ]))->assertOk()
            ->assertSee('data-employee-tab-panel="loans"', false)
            ->assertSee('payroll-batch-add-loan-modal', false)
            ->assertSee('payroll-batch-edit-loan-modal-'.$manual->payroll_loan_payment_id, false);

        $this->assertTrue($this->loan->fresh()->payments->contains('payroll_loan_payment_id', $manual->payroll_loan_payment_id));
    }

    #[Test]
    public function manual_loan_deduction_can_be_edited_and_soft_deleted(): void
    {
        $this->post($this->url(), [
            'employee_loan_id' => $this->loan->employee_loan_id,
            'payment' => 1000,
        ])->assertSessionHas('success');
        $manual = $this->payments()->where('is_manual', true)->firstOrFail();

        $this->put($this->url('/'.$manual->payroll_loan_payment_id), ['payment' => 5000])
            ->assertSessionHas('success');
        $this->assertSame(5000.0, (float) $manual->fresh()->payment);
        $this->assertSame(0.0, $this->loan->fresh()->loanBalance());

        $this->put($this->url('/'.$manual->payroll_loan_payment_id), ['payment' => 5001])
            ->assertSessionHasErrors('payment', null, 'editLoanPayment'.$manual->payroll_loan_payment_id);

        $this->delete($this->url('/'.$manual->payroll_loan_payment_id))->assertSessionHas('success');
        $this->assertSoftDeleted('trn_payroll_loan_payments', ['payroll_loan_payment_id' => $manual->payroll_loan_payment_id]);
        $this->assertSame(5000.0, $this->loan->fresh()->loanBalance());
    }

    #[Test]
    public function payment_cannot_exceed_balance_or_use_another_employees_loan(): void
    {
        $this->post($this->url(), [
            'employee_loan_id' => $this->loan->employee_loan_id,
            'payment' => 6000,
        ])->assertSessionHasErrors('payment');

        $other = Employee::query()->create(['employee_number' => 'EMP-LOAN-2', 'first_name' => 'O', 'last_name' => 'Ther']);
        $otherLoan = EmployeeLoan::query()->create([
            'employee_id' => $other->employee_id,
            'loan_type_id' => $this->loan->loan_type_id,
            'payment_scheme_id' => PaymentScheme::USER_ENTERED_AMORTIZATION,
            'dt_loan' => '2026-05-01',
            'loan_amount' => 5000,
        ]);

        $this->post($this->url(), [
            'employee_loan_id' => $otherLoan->employee_loan_id,
            'payment' => 100,
        ])->assertSessionHasErrors('employee_loan_id');

        $this->assertSame(0, PayrollLoanPayment::query()->where('is_manual', true)->count());
    }

    #[Test]
    public function auto_loan_line_cannot_be_edited(): void
    {
        app(LoanPayrollService::class)->syncForDetail($this->detail);
        $auto = $this->payments()->firstOrFail();

        $this->put($this->url('/'.$auto->payroll_loan_payment_id), ['payment' => 10])
            ->assertSessionHasErrors('batch', null, 'editLoanPayment'.$auto->payroll_loan_payment_id);
        $this->delete($this->url('/'.$auto->payroll_loan_payment_id))->assertSessionHas('error');
        $this->assertSame(1000.0, (float) $auto->fresh()->payment);
    }
}
