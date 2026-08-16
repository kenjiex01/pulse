<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Bir1601cReportService;
use App\Support\PhilhealthDeductionTypes;
use App\Support\SssDeductionTypes;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Bir1601cMultiBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_same_pay_month_batches_are_summed(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee('1601C-SUM');
        $report = Report::query()->where('title', 'BIR Form 1601-C')->firstOrFail();

        $batchA = $this->makePostedBatchWithAmounts($user, $employee, 2026, 7, 101, [
            'basic' => 7000.0,
            'ot' => 300.0,
            'sss' => 300.0,
            'philhealth' => 200.0,
            'pagibig' => 100.0,
        ]);
        $batchB = $this->makePostedBatchWithAmounts($user, $employee, 2026, 7, 102, [
            'basic' => 8000.0,
            'ot' => 400.0,
            'sss' => 350.0,
            'philhealth' => 250.0,
            'pagibig' => 150.0,
        ]);

        $result = app(Bir1601cReportService::class)->generate($report, [
            'payroll_batch_ids' => [$batchA->payroll_batch_id, $batchB->payroll_batch_id],
            'employee_ids' => [$employee->employee_id],
            'output_format' => 'html',
            'include_annual_13th_month' => false,
        ], $user);

        $totals = $result->meta['totals'];
        $line = $result->meta['employee_lines'][0];

        $this->assertSame(2, (int) $result->meta['batch_count']);
        $this->assertSame(15700.0, (float) $line['gross_compensation']);
        $this->assertSame(700.0, (float) ($line['income_breakdown']['OVRT']['taxable'] ?? 0) + (float) ($line['income_breakdown']['OVRT']['non_taxable'] ?? 0));
        $this->assertSame(650.0, (float) $line['sss_contribution']);
        $this->assertSame(450.0, (float) $line['philhealth_contribution']);
        $this->assertSame(250.0, (float) $line['pagibig_contribution']);
        $this->assertSame(0.0, (float) $totals['item_17']);

        // MWE-style mapping: #15 = gross − govt (ex WHT) − #16 OT; #16 = OT sum
        $this->assertSame(700.0, (float) $totals['item_16']);
        $this->assertSame(13650.0, (float) $totals['item_15']); // 15700 - 1350 govt - 700 OT
        $this->assertSame(1350.0, (float) $totals['item_19']); // 650+450+250
    }

    public function test_different_pay_month_batches_are_rejected(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee('1601C-DIFF');
        $report = Report::query()->where('title', 'BIR Form 1601-C')->firstOrFail();

        $july = $this->makePostedBatchWithAmounts($user, $employee, 2026, 7, 201, [
            'basic' => 5000.0,
            'ot' => 0.0,
            'sss' => 100.0,
            'philhealth' => 100.0,
            'pagibig' => 100.0,
        ]);
        $august = $this->makePostedBatchWithAmounts($user, $employee, 2026, 8, 202, [
            'basic' => 5000.0,
            'ot' => 0.0,
            'sss' => 100.0,
            'philhealth' => 100.0,
            'pagibig' => 100.0,
        ]);

        try {
            app(Bir1601cReportService::class)->generate($report, [
                'payroll_batch_ids' => [$july->payroll_batch_id, $august->payroll_batch_id],
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'html',
            ], $user);
            $this->fail('Expected ValidationException for mixed pay months.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payroll_batch_ids', $exception->errors());
            $this->assertStringContainsString(
                'same pay month and pay year',
                $exception->errors()['payroll_batch_ids'][0],
            );
        }

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_ids' => [$july->payroll_batch_id, $august->payroll_batch_id],
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors('payroll_batch_ids');
    }

    public function test_batch_employees_accepts_multiple_posted_batches(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee('1601C-EMP');

        $batchA = $this->makePostedBatchWithAmounts($user, $employee, 2026, 7, 301, [
            'basic' => 1000.0,
            'ot' => 0.0,
            'sss' => 50.0,
            'philhealth' => 50.0,
            'pagibig' => 50.0,
        ]);
        $batchB = $this->makePostedBatchWithAmounts($user, $employee, 2026, 7, 302, [
            'basic' => 1000.0,
            'ot' => 0.0,
            'sss' => 50.0,
            'philhealth' => 50.0,
            'pagibig' => 50.0,
        ]);

        $this->actingAs($user)
            ->getJson(route('payroll.reports.batch-employees', [
                'payroll_batch_ids' => [$batchA->payroll_batch_id, $batchB->payroll_batch_id],
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => (int) $employee->employee_id]);
    }

    private function makeEmployee(string $number): Employee
    {
        return Employee::query()->create([
            'employee_number' => $number,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => strtolower($number).'@example.com',
        ]);
    }

    /**
     * @param  array{basic: float, ot: float, sss: float, philhealth: float, pagibig: float}  $amounts
     */
    private function makePostedBatchWithAmounts(
        User $user,
        Employee $employee,
        int $payYear,
        int $calendarMonth,
        int $batchNo,
        array $amounts,
    ): PayrollBatch {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => $payYear,
            'pay_period' => $batchNo,
            'dt_from' => sprintf('%04d-%02d-01 00:00:00', $payYear, $calendarMonth),
            'dt_to' => sprintf('%04d-%02d-15 00:00:00', $payYear, $calendarMonth),
            'calendar_month' => $calendarMonth,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => $batchNo,
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

        $basicTypeId = (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id');
        $otTypeId = (int) IncomeType::query()->where('income_type_code', 'OVRT')->value('income_type_id');

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $basicTypeId,
            'taxable' => 0,
            'non_taxable' => $amounts['basic'],
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);

        if ($amounts['ot'] > 0) {
            PayrollIncome::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'income_type_id' => $otTypeId,
                'taxable' => 0,
                'non_taxable' => $amounts['ot'],
                'is_editable' => false,
                'is_deletable' => false,
                'is_manual' => false,
            ]);
        }

        $this->createDeduction($detail, SssDeductionTypes::PREMIUM, $amounts['sss']);
        $this->createDeduction($detail, PhilhealthDeductionTypes::EXCLUSIVE_CODES[0], $amounts['philhealth']);
        $this->createDeduction($detail, 'PIBG', $amounts['pagibig']);

        return $batch->fresh(['payrollCalendar']);
    }

    private function createDeduction(PayrollBatchDetail $detail, string $code, float $amount): void
    {
        $typeId = (int) DeductionType::query()->where('deduction_type_code', $code)->value('deduction_type_id');

        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => $typeId,
            'employee_amount' => $amount,
            'employer_amount' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);
    }
}
