<?php

namespace Tests\Unit;

use App\Support\PayslipPdfProtector;
use Tests\TestCase;

class PayslipPdfProtectorTest extends TestCase
{
    public function test_password_from_birth_date_uses_mmddyyyy(): void
    {
        $this->assertSame('05151990', PayslipPdfProtector::passwordFromBirthDate('1990-05-15'));
    }

    public function test_protect_returns_encrypted_pdf_bytes(): void
    {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body><p>Test payslip</p></body></html>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $protected = (new PayslipPdfProtector)->protect($dompdf->output(), '05151990');

        $this->assertNotSame('', $protected);
        $this->assertStringStartsWith('%PDF', $protected);
    }
}
