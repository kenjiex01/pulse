<?php

namespace Tests\Unit;

use App\Support\Bir2316FormMapper;
use PHPUnit\Framework\TestCase;

class Bir2316FormMapperTest extends TestCase
{
    public function test_mwe_layout_puts_compensation_in_non_taxable_section(): void
    {
        $mapped = Bir2316FormMapper::map([
            'taxable_compensation' => 0,
            'non_taxable_compensation' => 160218.33,
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
        ]);

        $this->assertTrue($mapped['is_mwe']);
        $this->assertSame(149764.0, $mapped['item_29']);
        $this->assertSame(10454.0, $mapped['item_31']);
        $this->assertSame(13753.33, $mapped['item_34']);
        $this->assertSame(15276.0, $mapped['item_36']);
        $this->assertSame(189247.33, $mapped['item_38']);
        $this->assertSame(0.0, $mapped['item_52']);
        $this->assertSame(189247.33, $mapped['item_19']);
        $this->assertSame(0.0, $mapped['item_25a']);
    }

    public function test_mwe_flag_puts_taxable_tagged_basic_in_item_29(): void
    {
        $mapped = Bir2316FormMapper::map([
            'taxable_compensation' => 7556.25,
            'non_taxable_compensation' => 0,
            'tax_withheld' => 0,
            'sss_contribution' => 375,
            'philhealth_contribution' => 250,
            'pagibig_contribution' => 200,
            'is_above_minimum_wage_earner' => false,
            'income_breakdown' => [
                'BASC' => ['taxable' => 7181.25, 'non_taxable' => 0.0],
                'OVRT' => ['taxable' => 375.0, 'non_taxable' => 0.0],
            ],
        ]);

        $this->assertTrue($mapped['is_mwe']);
        $this->assertSame(7181.25, $mapped['item_29']);
        $this->assertSame(375.0, $mapped['item_31']);
        $this->assertSame(825.0, $mapped['item_36']);
        $this->assertSame(0.0, $mapped['item_52']);
        $this->assertSame(8381.25, $mapped['item_19']);
    }

    public function test_mwe_holiday_nd_and_hazard_go_to_items_30_32_33(): void
    {
        $mapped = Bir2316FormMapper::map([
            'taxable_compensation' => 0,
            'non_taxable_compensation' => 5000,
            'tax_withheld' => 0,
            'sss_contribution' => 0,
            'philhealth_contribution' => 0,
            'pagibig_contribution' => 0,
            'is_above_minimum_wage_earner' => false,
            'income_breakdown' => [
                'BASC' => ['taxable' => 0.0, 'non_taxable' => 3000.0],
                'HOLI' => ['taxable' => 0.0, 'non_taxable' => 800.0],
                'NDIF' => ['taxable' => 0.0, 'non_taxable' => 700.0],
                'HAZD' => ['taxable' => 0.0, 'non_taxable' => 500.0],
            ],
        ]);

        $this->assertTrue($mapped['is_mwe']);
        $this->assertSame(3000.0, $mapped['item_29']);
        $this->assertSame(800.0, $mapped['item_30']);
        $this->assertSame(700.0, $mapped['item_32']);
        $this->assertSame(500.0, $mapped['item_33']);
        $this->assertSame(0.0, $mapped['item_37']);
    }

    public function test_taxable_employee_puts_basic_and_ot_in_taxable_section(): void
    {
        $mapped = Bir2316FormMapper::map([
            'taxable_compensation' => 50000,
            'non_taxable_compensation' => 2000,
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
        ]);

        $this->assertFalse($mapped['is_mwe']);
        $this->assertSame(40000.0, $mapped['item_39']);
        $this->assertSame(10000.0, $mapped['item_50']);
        $this->assertSame(2000.0, $mapped['item_35']);
        $this->assertSame(1700.0, $mapped['item_36']);
        $this->assertSame(2500.0, $mapped['item_25a']);
        $this->assertSame(50000.0, $mapped['item_52']);
    }
}
