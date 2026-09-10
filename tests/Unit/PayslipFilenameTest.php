<?php

namespace Tests\Unit;

use App\Support\PayslipFilename;
use Tests\TestCase;

class PayslipFilenameTest extends TestCase
{
    public function test_for_payslip_builds_readable_name_with_pay_period(): void
    {
        $filename = PayslipFilename::forPayslip([
            'last_name' => 'Abrantes',
            'first_name' => 'Roselyn',
            'middle_name' => 'Lanzaderas',
            'pay_period' => 'July 27 - August 10, 2026',
        ]);

        $this->assertSame('Abrantes Roselyn Lanzaderas July 27 - August 10, 2026.pdf', $filename);
    }

    public function test_sanitize_removes_invalid_characters(): void
    {
        $this->assertSame(
            'Abrantes Roselyn Test',
            PayslipFilename::sanitize('Abrantes: Roselyn Test')
        );
    }

    public function test_for_payslip_with_suffix_handles_duplicates(): void
    {
        $payslip = [
            'last_name' => 'Doe',
            'first_name' => 'Jane',
            'middle_name' => '',
            'pay_period' => 'June 1 - 15, 2026',
            'employee_number' => 'EMP-001',
        ];

        $this->assertSame(
            'Doe Jane June 1 - 15, 2026 EMP-001.pdf',
            PayslipFilename::forPayslipWithSuffix($payslip, 'EMP-001')
        );
    }
}
