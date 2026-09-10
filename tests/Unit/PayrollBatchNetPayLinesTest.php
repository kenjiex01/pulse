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
}
