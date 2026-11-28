<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\PayrollBatchStatusSeeder;
use Database\Seeders\PayrollMaintenanceSeeder;
use Database\Seeders\PayTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThirteenthMonthPayrollTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_thirteenth_month_pay_is_posted_batch_gross_divided_by_twelve(): void
    {
        $this->seed(PayTypeSeeder::class);
        $this->seed(PayrollMaintenanceSeeder::class);
        $this->seed(PayrollBatchStatusSeeder::class);

        $user = User::factory()->create();
        $employee = Employee::query()->create([
            'employee_number' => '13TH-001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.13th@example.com',
            'is_active' => true,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $posted = $this->batchWithGross($user, $employee, PayType::SEMI_MONTHLY, 11, PayrollBatchStatus::POSTED, 10000, 2000);
        $other = $this->batchWithGross($user, $employee, PayType::MONTHLY, 12, PayrollBatchStatus::POSTED, 2000, 0);

        $service = app(PayrollBatchService::class);

        $this->assertSame(1166.67, $service->thirteenthMonthPay((int) $employee->employee_id, [
            (int) $posted->payroll_batch_id,
            (int) $other->payroll_batch_id,
        ]));

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::THIRTEENTH_MONTH,
            'pay_year' => 2026,
            'pay_period' => 1,
            'dt_from' => '2026-01-01',
            'dt_to' => '2026-12-31',
            'calendar_month' => 12,
            'is_regular_period' => true,
        ]);
        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 9001,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
            'withholding_tax_computation_id' => 1,
        ]);
        $batch->sourceLinks()->create(['source_payroll_batch_id' => $posted->payroll_batch_id]);
        $batch->sourceLinks()->create(['source_payroll_batch_id' => $other->payroll_batch_id]);
        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);
        $detail->setRelation('payrollBatch', $batch->fresh(['payrollCalendar', 'sourceLinks']));

        $service->prepareDetailTransactions($detail);

        $income = PayrollIncome::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)->first();
        $thirteenth = IncomeType::query()->where('income_type_code', '13TH')->firstOrFail();

        $this->assertNotNull($income);
        $this->assertSame((int) $thirteenth->income_type_id, (int) $income->income_type_id);
        $this->assertSame('0.00', (string) $income->taxable);
        $this->assertSame('1166.67', (string) $income->non_taxable);
        $this->assertSame(1, PayrollIncome::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)->count());
    }

    #[Test]
    public function test_only_posted_batches_can_be_selected_for_thirteenth_month(): void
    {
        $this->seed(PayTypeSeeder::class);
        $this->seed(PayrollMaintenanceSeeder::class);
        $this->seed(PayrollBatchStatusSeeder::class);

        $user = User::factory()->create();
        $employee = Employee::query()->create([
            'employee_number' => '13TH-002',
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'email' => 'ben.13th@example.com',
        ]);

        $posted = $this->batchWithGross($user, $employee, PayType::SEMI_MONTHLY, 1, PayrollBatchStatus::POSTED, 1200, 0);
        $pending = $this->batchWithGross($user, $employee, PayType::SEMI_MONTHLY, 2, PayrollBatchStatus::PENDING, 1200, 0);
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::THIRTEENTH_MONTH,
            'pay_year' => 2026,
            'pay_period' => 1,
            'dt_from' => '2026-01-01',
            'dt_to' => '2026-12-31',
            'calendar_month' => 12,
        ]);

        $service = app(PayrollBatchService::class);
        $base = [
            'batch_no' => 77,
            'pay_type_id' => PayType::THIRTEENTH_MONTH,
            'pay_year' => 2026,
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'withholding_tax_computation_id' => 1,
        ];

        try {
            $service->validate($base + ['source_payroll_batch_ids' => [$pending->payroll_batch_id]]);
            $this->fail('Pending batches must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source_payroll_batch_ids.0', $exception->errors());
        }

        $validated = $service->validate($base + ['source_payroll_batch_ids' => [$posted->payroll_batch_id]]);
        $batch = $service->create($user, $validated);

        $this->assertSame(
            [(int) $posted->payroll_batch_id],
            $batch->sourceLinks()->pluck('source_payroll_batch_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    private function batchWithGross(
        User $user,
        Employee $employee,
        int $payTypeId,
        int $batchNo,
        int $statusId,
        float $taxable,
        float $nonTaxable,
    ): PayrollBatch {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => $payTypeId,
            'pay_year' => 2026,
            'pay_period' => $batchNo,
            'dt_from' => '2026-01-01',
            'dt_to' => '2026-01-15',
            'calendar_month' => 1,
            'is_regular_period' => true,
        ]);
        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => $batchNo,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => $statusId,
            'withholding_tax_computation_id' => 1,
        ]);
        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);
        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id')
                ?? IncomeType::query()->create([
                    'income_type_code' => 'BASC',
                    'description' => 'Basic Income',
                    'is_non_taxable' => false,
                    'is_active' => true,
                ])->income_type_id,
            'taxable' => $taxable,
            'non_taxable' => $nonTaxable,
            'is_manual' => false,
        ]);

        return $batch;
    }
}
