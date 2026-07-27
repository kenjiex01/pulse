<?php

namespace Tests\Unit;

use App\Models\PayrollBatch;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Services\GovernmentDeductionPayrollService;
use Database\Seeders\GovernmentTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WithholdingTaxPayTypeFrequencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernmentTablesSeeder::class);
    }

    #[Test]
    public function semi_monthly_employee_uses_semi_monthly_wtax_table(): void
    {
        $service = app(GovernmentDeductionPayrollService::class);
        $batch = $this->batchWithPayType(PayType::SEMI_MONTHLY);

        // Period gross 25,267.34 − statutory 1,953.22 = 23,314.12
        // Semi-monthly ≥ 16,667: 937.50 + 20% of excess ≈ 2,266.92
        $result = $service->computeWithholdingTax(25267.34, 1953.22, $batch);

        $this->assertEqualsWithDelta(2266.92, $result['employee_amount'], 0.02);
        $this->assertSame(0.0, $result['employer_amount']);
    }

    #[Test]
    public function monthly_employee_uses_monthly_wtax_table(): void
    {
        $service = app(GovernmentDeductionPayrollService::class);
        $batch = $this->batchWithPayType(PayType::MONTHLY);

        // Same net on monthly table ≥ 20,833: 15% of excess ≈ 372.17
        $result = $service->computeWithholdingTax(25267.34, 1953.22, $batch);

        $this->assertEqualsWithDelta(372.17, $result['employee_amount'], 0.02);
    }

    #[Test]
    public function frequency_id_follows_calendar_pay_type(): void
    {
        $service = app(GovernmentDeductionPayrollService::class);

        $this->assertSame(
            3,
            $service->withholdingTaxFrequencyId($this->batchWithPayType(PayType::SEMI_MONTHLY)),
        );
        $this->assertSame(
            4,
            $service->withholdingTaxFrequencyId($this->batchWithPayType(PayType::MONTHLY)),
        );
    }

    private function batchWithPayType(int $payTypeId): PayrollBatch
    {
        $batch = new PayrollBatch([
            'withholding_tax_computation_id' => 1,
        ]);
        $batch->setRelation('payrollCalendar', new PayrollCalendar([
            'pay_type_id' => $payTypeId,
        ]));

        return $batch;
    }
}
