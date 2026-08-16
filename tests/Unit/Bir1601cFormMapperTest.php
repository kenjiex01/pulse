<?php

namespace Tests\Unit;

use App\Support\Bir1601cFormMapper;
use PHPUnit\Framework\TestCase;

class Bir1601cFormMapperTest extends TestCase
{
    public function test_mwe_and_taxable_employees_roll_into_jan_2018_items(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 0,
                'non_taxable_compensation' => 160218.33,
                'gross_compensation' => 160218.33,
                'tax_withheld' => 0,
                'sss_contribution' => 10000,
                'philhealth_contribution' => 3000,
                'pagibig_contribution' => 2276,
                'is_above_minimum_wage_earner' => false,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 0.0, 'non_taxable' => 149764.0],
                    'OVRT' => ['taxable' => 0.0, 'non_taxable' => 10454.0],
                    '13TH' => ['taxable' => 0.0, 'non_taxable' => 13753.33],
                ],
            ],
            [
                'taxable_compensation' => 50000,
                'non_taxable_compensation' => 2000,
                'gross_compensation' => 52000,
                'tax_withheld' => 2500,
                'sss_contribution' => 1000,
                'philhealth_contribution' => 500,
                'pagibig_contribution' => 200,
                'is_above_minimum_wage_earner' => true,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 40000.0, 'non_taxable' => 0.0],
                    'OVRT' => ['taxable' => 10000.0, 'non_taxable' => 0.0],
                    'DEMN' => ['taxable' => 0.0, 'non_taxable' => 2000.0],
                ],
            ],
        ]);

        $this->assertSame(242947.33, $totals['item_14']);
        $this->assertSame(134488.33, $totals['item_15']);
        $this->assertSame(10454.0, $totals['item_16']);
        $this->assertSame(0.0, $totals['item_17']);
        $this->assertSame(2000.0, $totals['item_18']);
        $this->assertSame(16976.0, $totals['item_19']);
        $this->assertSame(0.0, $totals['item_20']);
        $this->assertSame(163918.33, $totals['item_21']);
        $this->assertSame(79029.0, $totals['item_22']);
        $this->assertSame(0.0, $totals['item_23']);
        $this->assertSame(79029.0, $totals['item_24']);
        $this->assertSame(2500.0, $totals['item_25']);
        $this->assertSame(2500.0, $totals['item_27']);
        $this->assertSame(2500.0, $totals['item_31']);
        $this->assertSame(2500.0, $totals['item_36']);
        $this->assertSame(2500.0, $totals['tax_withheld']);
    }

    public function test_taxable_without_withholding_goes_to_item_23(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 10000,
                'non_taxable_compensation' => 0,
                'gross_compensation' => 10000,
                'tax_withheld' => 0,
                'sss_contribution' => 0,
                'philhealth_contribution' => 0,
                'pagibig_contribution' => 0,
                'is_above_minimum_wage_earner' => true,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 10000.0, 'non_taxable' => 0.0],
                ],
            ],
        ]);

        $this->assertSame(10000.0, $totals['item_14']);
        $this->assertSame(10000.0, $totals['item_23']);
        $this->assertSame(0.0, $totals['item_24']);
        $this->assertSame(0.0, $totals['item_25']);
    }

    public function test_statutory_contributions_are_inside_item_14_so_item_24_is_not_negative(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 7200,
                'non_taxable_compensation' => 0,
                'gross_compensation' => 7200,
                'tax_withheld' => 0,
                'sss_contribution' => 350,
                'philhealth_contribution' => 250,
                'pagibig_contribution' => 200,
                'is_above_minimum_wage_earner' => false,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 7200.0, 'non_taxable' => 0.0],
                ],
            ],
        ]);

        $this->assertSame(8000.0, $totals['item_14']);
        $this->assertSame(6400.0, $totals['item_15']);
        $this->assertSame(800.0, $totals['item_19']);
        $this->assertSame(7200.0, $totals['item_21']);
        $this->assertSame(800.0, $totals['item_22']);
        $this->assertSame(0.0, $totals['item_23']);
        $this->assertSame(800.0, $totals['item_24']);
    }

    public function test_mwe_with_taxable_tagged_basic_uses_gross_less_government_deductions_except_wht(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 7556.25,
                'non_taxable_compensation' => 0,
                'gross_compensation' => 7556.25,
                'tax_withheld' => 0,
                'sss_contribution' => 375,
                'philhealth_contribution' => 250,
                'pagibig_contribution' => 200,
                'is_above_minimum_wage_earner' => false,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 7181.25, 'non_taxable' => 0.0],
                    'OVRT' => ['taxable' => 375.0, 'non_taxable' => 0.0],
                ],
            ],
        ]);

        $this->assertSame(8381.25, $totals['item_14']);
        $this->assertSame(6356.25, $totals['item_15']);
        $this->assertSame(375.0, $totals['item_16']);
        $this->assertSame(825.0, $totals['item_19']);
        $this->assertSame(0.0, $totals['item_23']);
    }

    public function test_mwe_holiday_ot_nd_and_hazard_fill_item_16(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 0,
                'non_taxable_compensation' => 5000,
                'gross_compensation' => 5000,
                'tax_withheld' => 0,
                'sss_contribution' => 0,
                'philhealth_contribution' => 0,
                'pagibig_contribution' => 0,
                'is_above_minimum_wage_earner' => false,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 0.0, 'non_taxable' => 3000.0],
                    'OVRT' => ['taxable' => 0.0, 'non_taxable' => 1000.0],
                    'HOLI' => ['taxable' => 0.0, 'non_taxable' => 500.0],
                    'NDIF' => ['taxable' => 0.0, 'non_taxable' => 300.0],
                    'HAZD' => ['taxable' => 0.0, 'non_taxable' => 200.0],
                ],
            ],
        ]);

        $this->assertSame(3000.0, $totals['item_15']);
        $this->assertSame(2000.0, $totals['item_16']);
        $this->assertSame(0.0, $totals['item_23']);
    }

    public function test_item_17_is_zero_when_annual_13th_is_not_included(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines([
            [
                'taxable_compensation' => 0,
                'non_taxable_compensation' => 12000,
                'gross_compensation' => 12000,
                'tax_withheld' => 0,
                'sss_contribution' => 0,
                'philhealth_contribution' => 0,
                'pagibig_contribution' => 0,
                'is_above_minimum_wage_earner' => false,
                'income_breakdown' => [
                    'BASC' => ['taxable' => 0.0, 'non_taxable' => 12000.0],
                    '13TH' => ['taxable' => 0.0, 'non_taxable' => 1000.0],
                ],
            ],
        ]);

        $this->assertSame(0.0, $totals['item_17']);
    }

    public function test_item_17_uses_annual_13th_when_checkbox_is_on(): void
    {
        $totals = Bir1601cFormMapper::totalsFromLines(
            [
                [
                    'taxable_compensation' => 0,
                    'non_taxable_compensation' => 12000,
                    'gross_compensation' => 12000,
                    'tax_withheld' => 0,
                    'sss_contribution' => 0,
                    'philhealth_contribution' => 0,
                    'pagibig_contribution' => 0,
                    'is_above_minimum_wage_earner' => false,
                    'income_breakdown' => [
                        'BASC' => ['taxable' => 0.0, 'non_taxable' => 12000.0],
                    ],
                ],
            ],
            [
                'include_annual_13th_month' => true,
                'annual_13th_month' => 8500.50,
            ],
        );

        $this->assertSame(8500.50, $totals['item_17']);
    }

    public function test_annual_13th_month_is_year_basic_divided_by_12_capped_at_90000(): void
    {
        $this->assertSame(10000.0, Bir1601cFormMapper::thirteenthMonthFromYearBasic(120000.0));
        $this->assertSame(90000.0, Bir1601cFormMapper::thirteenthMonthFromYearBasic(1200000.0));
        $this->assertSame(18000.0, Bir1601cFormMapper::annual13thMonthFromLines([
            [
                'income_breakdown' => [
                    'BASC' => ['taxable' => 72000.0, 'non_taxable' => 0.0],
                ],
            ],
            [
                'income_breakdown' => [
                    'BASC' => ['taxable' => 0.0, 'non_taxable' => 144000.0],
                ],
            ],
        ]));
    }
}
