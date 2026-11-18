<?php

namespace Tests\Unit;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayType;
use App\Models\User;
use App\Services\EmployeeDeductionPayrollService;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDeductionPayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_batch_deducts_amortization_only_when_the_type_is_ticked(): void
    {
        [$detail, $record] = $this->detailWithDeduction(ticked: true, amount: 5000, amortization: 500);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $line = PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('employee_deduction_id', $record->employee_deduction_id)
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(500.0, (float) $line->employee_amount);
        $this->assertFalse((bool) $line->is_manual);
    }

    public function test_unticked_deduction_type_creates_no_payroll_line(): void
    {
        [$detail, $record] = $this->detailWithDeduction(ticked: false, amount: 5000, amortization: 500);

        app(EmployeeDeductionPayrollService::class)->syncForDetail($detail);

        $this->assertNull(
            PayrollDeduction::query()
                ->where('employee_deduction_id', $record->employee_deduction_id)
                ->first(),
        );
        $this->assertSame(5000.0, $record->fresh()->balance());
    }

    public function test_payment_is_capped_by_remaining_balance_and_resync_does_not_duplicate(): void
    {
        [$detail, $record] = $this->detailWithDeduction(ticked: true, amount: 100, amortization: 150, interest: 20);

        $service = app(EmployeeDeductionPayrollService::class);
        $service->syncForDetail($detail);
        $service->syncForDetail($detail->fresh());

        $lines = PayrollDeduction::query()
            ->where('employee_deduction_id', $record->employee_deduction_id)
            ->get();

        $this->assertCount(1, $lines);
        $this->assertSame(120.0, (float) $lines->first()->employee_amount);
        $this->assertSame(0.0, $record->fresh()->balance());
    }

    public function test_deduction_dated_after_the_period_is_skipped(): void
    {
        [$detail, $record] = $this->detailWithDeduction(ticked: true, amount: 1000, amortization: 100, date: '2026-07-01');

        app(EmployeeDeductionPayrollService::class)->syncForDetail($detail);

        $this->assertNull(
            PayrollDeduction::query()->where('employee_deduction_id', $record->employee_deduction_id)->first(),
        );
    }

    /**
     * @return array{0: PayrollBatchDetail, 1: EmployeeDeduction}
     */
    private function detailWithDeduction(
        bool $ticked,
        float $amount,
        float $amortization,
        float $interest = 0,
        string $date = '2026-06-01',
    ): array {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-DED-PAY-'.uniqid(),
            'first_name' => 'Pay',
            'last_name' => 'Deduct',
        ]);

        $typeId = (int) DeductionType::query()->where('deduction_type_code', 'OTHR')->value('deduction_type_id');

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(1000, 9000),
            'dt_from' => '2026-06-16',
            'dt_to' => '2026-06-30',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        if ($ticked) {
            $calendar->deductions()->create(['deduction_type_id' => $typeId]);
        }

        $record = EmployeeDeduction::query()->create([
            'employee_id' => $employee->employee_id,
            'deduction_type_id' => $typeId,
            'dt_deduction' => $date,
            'deduction_amount' => $amount,
            'amortization_amount' => $amortization,
            'deduction_interest' => $interest,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(1000, 9000),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        return [$detail, $record];
    }
}
