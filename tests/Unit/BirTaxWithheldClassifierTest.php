<?php

namespace Tests\Unit;

use App\Models\PayType;
use App\Support\BirTaxWithheldClassifier;
use Tests\TestCase;

class BirTaxWithheldClassifierTest extends TestCase
{
    public function test_mwe_under_threshold_goes_to_mwe_income_with_non_taxable_ot(): void
    {
        $result = BirTaxWithheldClassifier::classify([
            'gross_income' => 15570.0,
            'overtime_amount' => 2580.0,
            'deminimis_benefit' => 0.0,
            'is_above_minimum_wage_earner' => false,
            'tax_withheld' => 0.0,
            'threshold' => 20833.0,
        ]);

        $this->assertSame(2580.0, $result['non_taxable_overtime']);
        $this->assertSame(12990.0, $result['mwe_income']);
        $this->assertSame(0.0, $result['taxable_no_wt']);
        $this->assertSame(0.0, $result['taxable_with_wt']);
        $this->assertSame(0.0, $result['tax_withheld']);
        $this->assertFalse($result['is_taxable']);
    }

    public function test_above_mwe_under_threshold_goes_to_taxable_no_wt(): void
    {
        $result = BirTaxWithheldClassifier::classify([
            'gross_income' => 19225.0,
            'overtime_amount' => 0.0,
            'deminimis_benefit' => 0.0,
            'is_above_minimum_wage_earner' => true,
            'tax_withheld' => 0.0,
            'threshold' => 20833.0,
        ]);

        $this->assertSame(0.0, $result['mwe_income']);
        $this->assertSame(19225.0, $result['taxable_no_wt']);
        $this->assertSame(0.0, $result['taxable_with_wt']);
        $this->assertSame(0.0, $result['tax_withheld']);
    }

    public function test_above_mwe_over_threshold_goes_to_taxable_with_wt(): void
    {
        $result = BirTaxWithheldClassifier::classify([
            'gross_income' => 35000.0,
            'overtime_amount' => 0.0,
            'deminimis_benefit' => 10000.0,
            'is_above_minimum_wage_earner' => true,
            'tax_withheld' => 313.65,
            'threshold' => 20833.0,
        ]);

        $this->assertSame(0.0, $result['taxable_no_wt']);
        $this->assertSame(25000.0, $result['taxable_with_wt']);
        $this->assertSame(313.65, $result['tax_withheld']);
        $this->assertSame(10000.0, $result['deminimis_benefit']);
        $this->assertTrue($result['is_taxable']);
    }

    public function test_mwe_over_threshold_moves_to_taxable_with_wt(): void
    {
        $result = BirTaxWithheldClassifier::classify([
            'gross_income' => 23000.0,
            'overtime_amount' => 1000.0,
            'deminimis_benefit' => 0.0,
            'is_above_minimum_wage_earner' => false,
            'tax_withheld' => 100.0,
            'threshold' => 20833.0,
        ]);

        $this->assertSame(1000.0, $result['non_taxable_overtime']);
        $this->assertSame(0.0, $result['mwe_income']);
        $this->assertSame(22000.0, $result['taxable_with_wt']);
        $this->assertSame(100.0, $result['tax_withheld']);
    }

    public function test_monthly_threshold_uses_20833(): void
    {
        $this->assertSame(20833.0, BirTaxWithheldClassifier::taxableThresholdForPayType(PayType::MONTHLY));
        $this->assertSame(10417.0, BirTaxWithheldClassifier::taxableThresholdForPayType(PayType::SEMI_MONTHLY));
    }
}
