<?php

namespace Tests\Feature;

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
use App\Models\SysLog;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollBatchManualLinesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PayrollBatch $batch;

    private PayrollBatchDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-MAN-1',
            'first_name' => 'Manny',
            'last_name' => 'Manual',
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
            'income_type_id' => $this->incomeTypeId('BASC'),
            'taxable' => 10000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 901,
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $this->batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 901,
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

    private function incomeTypeId(string $code): int
    {
        return (int) IncomeType::query()->where('income_type_code', $code)->value('income_type_id');
    }

    private function url(string $suffix): string
    {
        return '/payroll/transaction/batches/'.$this->batch->payroll_batch_id
            .'/employees/'.$this->detail->payroll_batch_detail_id.$suffix;
    }

    private function manualDeductionTypeId(): int
    {
        return (int) DeductionType::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('is_valid_govt_deduction', false)->orWhereNull('is_valid_govt_deduction'))
            ->whereNotIn('deduction_type_code', ['LTDE', 'UTDE'])
            ->value('deduction_type_id');
    }

    #[Test]
    public function manual_income_can_be_edited_and_deleted(): void
    {
        $typeId = (int) IncomeType::query()->whereNotIn('income_type_code', ['BASC', 'OVRT'])->where('is_active', true)->value('income_type_id');

        $this->post($this->url('/incomes'), ['income_type_id' => $typeId, 'taxable' => 500])->assertSessionHas('success');
        $income = PayrollIncome::query()->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)->where('is_manual', true)->firstOrFail();

        $this->put($this->url('/incomes/'.$income->payroll_income_id), ['income_type_id' => $typeId, 'taxable' => 750, 'non_taxable' => 25])
            ->assertSessionHas('success');
        $income->refresh();
        $this->assertSame(750.0, (float) $income->taxable);
        $this->assertSame(25.0, (float) $income->non_taxable);
        $this->assertTrue(SysLog::query()->where('table_name', 'trn_payroll_incomes')->where('action', 'update')->exists());

        $this->get(route('payroll.transaction.tab', [
            'tab' => 'batches',
            'view_payroll_batch' => $this->batch->payroll_batch_id,
            'view_batch_detail' => $this->detail->payroll_batch_detail_id,
        ]))->assertOk()
            ->assertSee('payroll-batch-edit-income-modal-'.$income->payroll_income_id, false)
            ->assertSee('Auto');

        $this->delete($this->url('/incomes/'.$income->payroll_income_id))->assertSessionHas('success');
        $this->assertSoftDeleted('trn_payroll_incomes', ['payroll_income_id' => $income->payroll_income_id]);
    }

    #[Test]
    public function system_computed_income_cannot_be_edited_or_deleted(): void
    {
        $basic = PayrollIncome::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('income_type_id', $this->incomeTypeId('BASC'))
            ->firstOrFail();
        $this->assertFalse((bool) $basic->is_manual);
        $original = (float) $basic->taxable;

        $this->put($this->url('/incomes/'.$basic->payroll_income_id), [
            'income_type_id' => $this->incomeTypeId('BASC'),
            'hours' => 1,
            'days' => 1,
            'taxable' => 1,
        ])->assertSessionHasErrors('batch', null, 'editIncome'.$basic->payroll_income_id);

        $this->delete($this->url('/incomes/'.$basic->payroll_income_id))->assertSessionHas('error');

        $basic->refresh();
        $this->assertNull($basic->deleted_at);
        $this->assertSame($original, (float) $basic->taxable);

        $this->get(route('payroll.transaction.tab', [
            'tab' => 'batches',
            'view_payroll_batch' => $this->batch->payroll_batch_id,
            'view_batch_detail' => $this->detail->payroll_batch_detail_id,
        ]))->assertOk()
            ->assertDontSee('payroll-batch-edit-income-modal-'.$basic->payroll_income_id, false);
    }

    #[Test]
    public function manual_deduction_is_editable_but_system_deduction_is_not(): void
    {
        $typeId = $this->manualDeductionTypeId();

        $this->post($this->url('/deductions'), ['deduction_type_id' => $typeId, 'employee_amount' => 200])->assertSessionHas('success');
        $manual = PayrollDeduction::query()->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)->where('is_manual', true)->firstOrFail();

        $this->put($this->url('/deductions/'.$manual->payroll_deduction_id), [
            'deduction_type_id' => $typeId,
            'employee_amount' => 300,
            'reference_number' => 'REF-1',
        ])->assertSessionHas('success');
        $this->assertSame(300.0, (float) $manual->fresh()->employee_amount);
        $this->assertSame('REF-1', $manual->fresh()->reference_number);

        $system = PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $this->detail->payroll_batch_detail_id,
            'deduction_type_id' => $typeId,
            'employee_amount' => 99,
            'employer_amount' => 0,
            'is_manual' => false,
        ]);

        $amount = (float) $system->employee_amount;
        $this->put($this->url('/deductions/'.$system->payroll_deduction_id), [
            'deduction_type_id' => $typeId,
            'employee_amount' => 1,
        ])->assertSessionHasErrors('batch', null, 'editDeduction'.$system->payroll_deduction_id);
        $this->delete($this->url('/deductions/'.$system->payroll_deduction_id))->assertSessionHas('error');
        $this->assertSame($amount, (float) $system->fresh()->employee_amount);
        $this->assertNull($system->fresh()->deleted_at);

        $this->delete($this->url('/deductions/'.$manual->payroll_deduction_id))->assertSessionHas('success');
        $this->assertSoftDeleted('trn_payroll_deductions', ['payroll_deduction_id' => $manual->payroll_deduction_id]);
    }

    #[Test]
    public function line_from_another_employee_is_rejected(): void
    {
        $other = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $this->batch->payroll_batch_id,
            'employee_id' => Employee::query()->create(['employee_number' => 'EMP-MAN-2', 'first_name' => 'O', 'last_name' => 'Ther'])->employee_id,
        ]);
        $income = PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $other->payroll_batch_detail_id,
            'income_type_id' => $this->incomeTypeId('BASC'),
            'taxable' => 10,
            'non_taxable' => 0,
            'is_manual' => true,
        ]);

        $this->delete($this->url('/incomes/'.$income->payroll_income_id))
            ->assertSessionHas('error', 'That page was not found.');
        $this->assertNull($income->fresh()->deleted_at);
    }
}
