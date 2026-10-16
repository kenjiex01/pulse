<?php

namespace Tests\Unit;

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
use App\Models\User;
use App\Support\PayslipYearToDate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipYearToDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sums_posted_batches_in_same_pay_year_through_current_period(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-YTD-001',
            'first_name' => 'Ytd',
            'last_name' => 'Tester',
            'email' => 'ytd.tester@example.com',
        ]);
        $incomeType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $whtx = DeductionType::query()->where('deduction_type_code', 'WHTX')->firstOrFail();
        $sssp = DeductionType::query()->where('deduction_type_code', 'SSSP')->firstOrFail();

        $calendarEarly = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 1,
            'dt_from' => '2026-07-01 00:00:00',
            'dt_to' => '2026-07-15 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);
        $calendarLate = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 2,
            'dt_from' => '2026-07-27 00:00:00',
            'dt_to' => '2026-08-10 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);

        foreach ([$calendarEarly, $calendarLate] as $index => $calendar) {
            $batch = PayrollBatch::query()->create([
                'payroll_calendar_id' => $calendar->payroll_calendar_id,
                'batch_no' => 10 + $index,
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
            PayrollIncome::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'income_type_id' => $incomeType->income_type_id,
                'taxable' => 1000,
                'non_taxable' => 100,
                'is_editable' => false,
                'is_deletable' => false,
                'is_manual' => false,
            ]);
            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $whtx->deduction_type_id,
                'employee_amount' => 50,
                'employer_amount' => 0,
                'is_editable' => false,
                'is_deletable' => false,
                'is_manual' => false,
            ]);
            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $sssp->deduction_type_id,
                'employee_amount' => 25,
                'employer_amount' => 0,
                'is_editable' => false,
                'is_deletable' => false,
                'is_manual' => false,
            ]);
        }

        $ytd = PayslipYearToDate::forEmployee((int) $employee->employee_id, $calendarLate);

        $this->assertSame(2200.0, $ytd['gross_income']);
        $this->assertSame(2000.0, $ytd['taxable_income']);
        $this->assertSame(100.0, $ytd['withholding_tax']);
        $this->assertSame(50.0, $ytd['sss_ee']);
    }
}
