<?php

namespace Tests\Feature;

use App\Services\Reports\Bir1601cOfficialPdfWriter;
use App\Services\Reports\Bir2316OfficialPdfWriter;
use Tests\TestCase;

class BirOfficialPdfWriterTest extends TestCase
{
    public function test_1601c_writer_stamps_jan_2018_template(): void
    {
        $binary = app(Bir1601cOfficialPdfWriter::class)->write([
            'calendar_month' => 8,
            'pay_year' => '2026',
            'company_tin' => '123456789000',
            'company_rdo_code' => '039',
            'company_name' => 'Sample Company Inc',
            'company_address' => '123 Sample Street, Quezon City',
            'company_zip' => '1100',
            'compensation_atc' => 'WI010',
            'signatory_name' => 'Juan Dela Cruz',
            'signatory_title' => 'Payroll Manager',
            'totals' => [
                'item_14' => 100000.00,
                'item_15' => 40000.00,
                'item_21' => 50000.00,
                'item_22' => 50000.00,
                'item_24' => 50000.00,
                'item_25' => 5000.00,
                'item_27' => 5000.00,
                'item_31' => 5000.00,
                'item_36' => 5000.00,
                'tax_withheld' => 5000.00,
            ],
        ]);

        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(50000, strlen($binary));
    }

    public function test_2316_writer_stamps_sep_2021_template(): void
    {
        $binary = app(Bir2316OfficialPdfWriter::class)->write([
            [
                'for_year' => '2026',
                'period_from_mm' => '01',
                'period_from_dd' => '01',
                'period_to_mm' => '12',
                'period_to_dd' => '31',
                'tin' => '987654321000',
                'employee_name' => 'Santos, Maria A',
                'employee_name_upper' => 'SANTOS, MARIA A',
                'employee_rdo' => '039',
                'address' => '456 Sample Ave',
                'postal_code' => '1101',
                'birth_date_mmddyyyy' => '05151990',
                'phone' => '09171234567',
                'is_mwe' => false,
                'item_19' => 60000.00,
                'item_20' => 10000.00,
                'item_21' => 50000.00,
                'item_23' => 50000.00,
                'item_24' => 2500.00,
                'item_25a' => 2500.00,
                'item_26' => 2500.00,
                'item_28' => 2500.00,
                'item_29' => 0.00,
                'item_35' => 2000.00,
                'item_36' => 1700.00,
                'item_38' => 3700.00,
                'item_39' => 40000.00,
                'item_50' => 10000.00,
                'item_52' => 50000.00,
            ],
        ], [
            'employer' => [
                'tin' => '123456789000',
                'name' => 'Sample Company Inc',
                'address' => '123 Sample Street',
                'zip' => '1100',
            ],
            'signatory_name' => 'Juan Dela Cruz',
        ]);

        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(20000, strlen($binary));
    }
}
