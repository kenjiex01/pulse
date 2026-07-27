<?php

namespace Tests\Unit;

use App\Support\GovernmentTables;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GovernmentTablesWtaxRoundingTest extends TestCase
{
    #[Test]
    public function it_keeps_wtax_grid_values_to_two_decimals(): void
    {
        $normalized = GovernmentTables::normalizeWtaxGridColumn([
            'tax_amount' => 4270.70,
            'tax_plus' => 15.00,
            'amount' => 10417.00,
        ]);

        $this->assertSame(4270.70, $normalized['tax_amount']);
        $this->assertSame(15.0, $normalized['tax_plus']);
        $this->assertSame(10417.0, $normalized['amount']);
    }

    #[Test]
    public function it_formats_wtax_grid_values_with_two_decimals(): void
    {
        $this->assertSame('91770.70', GovernmentTables::formatWtaxGridValue(91770.70));
        $this->assertSame('10417.00', GovernmentTables::formatWtaxGridValue(10417));
    }

    #[Test]
    public function it_keeps_annual_wtax_attributes_to_two_decimals(): void
    {
        $normalized = GovernmentTables::normalizeAnnualWtaxAttributes([
            'income_from' => 250000.01,
            'income_to' => 400000.00,
            'amount_due' => 22500.00,
            'percentage_due' => 15.00,
        ]);

        $this->assertSame(250000.01, $normalized['income_from']);
        $this->assertSame(400000.0, $normalized['income_to']);
        $this->assertSame(22500.0, $normalized['amount_due']);
        $this->assertSame(15.0, $normalized['percentage_due']);
    }

    #[Test]
    public function it_caps_annual_income_to_at_column_max(): void
    {
        $normalized = GovernmentTables::normalizeAnnualWtaxAttributes([
            'income_from' => 8000000.01,
            'income_to' => 99999999.99,
            'amount_due' => 2202500.00,
            'percentage_due' => 35.00,
        ]);

        $this->assertSame(8000000.01, $normalized['income_from']);
        $this->assertSame(99999999.99, $normalized['income_to']);
    }
}
