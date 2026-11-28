<?php

namespace Tests\Unit;

use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Support\PayrollBatchNetPayLines;
use App\Support\PhilhealthDeductionTypes;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBatchNetPayLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_income_lines_use_type_description_and_hours(): void
    {
        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $income = new PayrollIncome([
            'income_type_id' => $basicType->income_type_id,
            'hours' => 95.75,
            'taxable' => 7181.25,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);

        $lines = PayrollBatchNetPayLines::incomeLines(collect([$income]));

        $this->assertCount(1, $lines);
        $this->assertSame('Basic Income', $lines->first()['label']);
        $this->assertSame(95.75, $lines->first()['hours']);
        $this->assertSame(7181.25, $lines->first()['amount']);
    }

    public function test_deduction_rows_convert_ltde_hours_to_minutes(): void
    {
        $lateType = DeductionType::query()->where('deduction_type_code', 'LTDE')->firstOrFail();
        $deduction = new PayrollDeduction([
            'deduction_type_id' => $lateType->deduction_type_id,
            'hours' => 0.25,
            'days' => 1,
            'employee_amount' => 18.75,
            'employer_amount' => 0,
        ]);
        $deduction->setRelation('deductionType', $lateType);

        $rows = PayrollBatchNetPayLines::deductionRows(collect([$deduction]));
        $lines = PayrollBatchNetPayLines::payslipDeductionLines($rows);

        $this->assertCount(1, $lines);
        $this->assertSame('Late', $lines[0]['label']);
        $this->assertSame(15, $lines[0]['minutes']);
        $this->assertSame(18.75, $lines[0]['amount']);
    }

    public function test_deduction_rows_show_absence_days(): void
    {
        $absentType = DeductionType::query()->where('deduction_type_code', 'ABST')->firstOrFail();
        $deduction = new PayrollDeduction([
            'deduction_type_id' => $absentType->deduction_type_id,
            'hours' => 4,
            'days' => 0.5,
            'employee_amount' => 500,
            'employer_amount' => 0,
        ]);
        $deduction->setRelation('deductionType', $absentType);

        $rows = PayrollBatchNetPayLines::deductionRows(collect([$deduction]));

        $this->assertTrue($rows->first()['show_days']);
        $this->assertSame(0.5, $rows->first()['days']);
        $this->assertSame('Absences', $rows->first()['description']);
    }

    public function test_deduction_rows_use_philhealth_premium_label(): void
    {
        $phimType = DeductionType::query()->where('deduction_type_code', PhilhealthDeductionTypes::MINIMUM)->firstOrFail();
        $deduction = new PayrollDeduction([
            'deduction_type_id' => $phimType->deduction_type_id,
            'employee_amount' => 250,
            'employer_amount' => 0,
        ]);
        $deduction->setRelation('deductionType', $phimType);

        $rows = PayrollBatchNetPayLines::deductionRows(collect([$deduction]));
        $lines = PayrollBatchNetPayLines::payslipDeductionLines($rows);

        $this->assertSame('Philhealth Premium', $lines[0]['label']);
        $this->assertNull($lines[0]['minutes']);
    }

    public function test_late_already_in_basic_is_shown_and_left_out_of_net_pay(): void
    {
        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $lateType = DeductionType::query()->where('deduction_type_code', 'LTDE')->firstOrFail();
        $sssType = DeductionType::query()->where('deduction_type_code', 'SSSP')->firstOrFail();

        $income = new PayrollIncome([
            'income_type_id' => $basicType->income_type_id,
            'hours' => 95.9833,
            'days' => 12,
            'taxable' => 7738.65,
            'non_taxable' => 0,
        ]);
        $income->setRelation('incomeType', $basicType);

        $late = new PayrollDeduction([
            'deduction_type_id' => $lateType->deduction_type_id,
            'hours' => 0.0167,
            'days' => 1,
            'employee_amount' => 1.34,
            'employer_amount' => 0,
            'counts_toward_net' => false,
        ]);
        $late->setRelation('deductionType', $lateType);

        $sss = new PayrollDeduction([
            'deduction_type_id' => $sssType->deduction_type_id,
            'employee_amount' => 375,
            'employer_amount' => 0,
            'counts_toward_net' => true,
        ]);
        $sss->setRelation('deductionType', $sssType);

        $rows = PayrollBatchNetPayLines::deductionRows(collect([$late, $sss]));
        $lateRow = $rows->firstWhere('code', 'LTDE');

        $this->assertNotNull($lateRow);
        $this->assertSame(1.34, $lateRow['employee_amount']);
        $this->assertFalse($lateRow['counts_toward_net']);
        $this->assertSame(0.0, $lateRow['net_employee_amount']);

        $detail = new \App\Models\PayrollBatchDetail;
        $detail->setRelation('incomes', collect([$income]));
        $detail->setRelation('deductions', collect([$late, $sss]));
        $detail->setRelation('loanPayments', collect());

        $this->assertSame(7363.65, PayrollBatchNetPayLines::netPay($detail));
    }
}
