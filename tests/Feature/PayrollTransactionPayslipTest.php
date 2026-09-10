<?php

namespace Tests\Feature;

use App\Mail\PayslipMail;
use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\User;
use App\Support\PayrollTransactionModule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayrollTransactionPayslipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_payslip_tab_loads_with_progress_panel_markup(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route(PayrollTransactionModule::routeName('tab'), ['tab' => 'payslip']))
            ->assertOk()
            ->assertSee('Payslip')
            ->assertSee('Send Payslip Email')
            ->assertSee('data-payslip-progress-panel', false)
            ->assertSee('data-payslip-progress-label', false)
            ->assertSee('data-payslip-send-root', false);
    }

    public function test_send_payslip_email_rejects_employee_not_in_batch(): void
    {
        $user = User::query()->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $otherEmployee = Employee::query()->create([
            'employee_number' => 'EMP-PSLIP-EMAIL-002',
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'email' => 'other@example.com',
        ]);

        $this->actingAs($user)
            ->postJson(route(PayrollTransactionModule::routeName('payslips.send')), [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $otherEmployee->employee_id,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.employee_id.0', 'Employee is not in the selected payroll batch.');
    }

    public function test_send_payslip_email_sends_mail_for_posted_batch_employee(): void
    {
        Mail::fake();

        $user = User::query()->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $this->actingAs($user)
            ->postJson(route(PayrollTransactionModule::routeName('payslips.send')), [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $employee->employee_id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'employee_id' => $employee->employee_id,
                'employee_name' => $employee->full_name,
                'email' => 'payslip.fixture@example.com',
            ]);

        Mail::assertSent(PayslipMail::class, function (PayslipMail $mail) use ($employee): bool {
            return $mail->hasTo('payslip.fixture@example.com');
        });
    }

    public function test_send_payslip_email_rejects_employee_without_birth_date(): void
    {
        $user = User::query()->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $employee->update(['birth_date' => null]);

        $this->actingAs($user)
            ->postJson(route(PayrollTransactionModule::routeName('payslips.send')), [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $employee->employee_id,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_send_payslip_email_rejects_employee_without_email(): void
    {
        $user = User::query()->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $employee->update(['email' => null]);

        $this->actingAs($user)
            ->postJson(route(PayrollTransactionModule::routeName('payslips.send')), [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_id' => $employee->employee_id,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * @return array{0: PayrollBatch, 1: Employee}
     */
    private function makePostedPayslipFixture(User $user): array
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PSLIP-EMAIL-001',
            'first_name' => 'Roselyn',
            'middle_name' => 'Test',
            'last_name' => 'Fixture',
            'email' => 'payslip.fixture@example.com',
            'birth_date' => '1990-05-15',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 2,
            'dt_from' => '2026-07-27 00:00:00',
            'dt_to' => '2026-08-10 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 4,
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
            'dt_processed' => now(),
            'processed_by_id' => $user->id,
            'dt_posted' => now(),
            'posted_by_id' => $user->id,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $basicType->income_type_id,
            'hours' => 95.75,
            'taxable' => 7181.25,
            'non_taxable' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);

        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => \App\Models\DeductionType::query()->where('deduction_type_code', 'PHIM')->value('deduction_type_id'),
            'employee_amount' => 250,
            'employer_amount' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);

        return [$batch->fresh(['payrollCalendar']), $employee];
    }
}
