<?php

namespace Tests\Unit;

use App\Services\Reports\ReportGenerationResult;
use App\Support\ReportPdfDownload;
use Tests\TestCase;

class ReportPdfDownloadTest extends TestCase
{
    public function test_stream_returns_pdf_download_response(): void
    {
        $result = new ReportGenerationResult(
            title: 'Payroll Register',
            headers: ['Employee No.', 'Employee Name', 'Net Pay'],
            rows: [
                ['2026-001', 'Jane Doe', '10000.00'],
            ],
            meta: [
                'batch_labels' => ['Batch No. 0001'],
            ],
        );

        $response = ReportPdfDownload::stream($result, 'Payroll_Register_Test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Payroll_Register_Test.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_payslip_pdf_stream_generates_landscape_document(): void
    {
        $result = new ReportGenerationResult(
            title: 'Payslip',
            headers: ['Employee No.', 'Employee Name', 'Total Earnings', 'Total Deductions', 'Net Pay'],
            rows: [],
            meta: [
                'layout' => 'payslip',
                'payslips' => [[
                    'layout_type' => 'staff',
                    'employee_name' => 'Jane Doe',
                    'faculty_label' => 'Staff',
                    'pay_period' => 'June 1 - 15, 2026',
                    'pay_date' => 'June 25, 2026',
                    'days_present' => 10,
                    'new_rate' => 500,
                    'earnings' => [['label' => 'Basic Pay', 'days' => 10, 'amount' => 5000]],
                    'deductions' => [['label' => 'SSS', 'mins' => null, 'amount' => 200]],
                    'total_earnings' => 5000,
                    'total_deductions' => 200,
                    'net_pay' => 4800,
                    'is_confidential' => false,
                ]],
                'company_name' => 'ICCT COLLEGES FOUNDATION, INC.',
            ],
        );

        $response = ReportPdfDownload::stream($result, 'Payslip_Test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotSame('', $response->getContent());
    }

    public function test_payroll_register_pdf_renders_wide_layout_without_error(): void
    {
        $headers = [];
        for ($i = 1; $i <= 84; $i++) {
            $headers[] = 'Col '.$i;
        }

        $row = array_fill(0, 84, 'x');
        $row[0] = '1';
        $row[1] = 'Jane Doe';
        $row[5] = '75';
        $row[6] = '600';

        $result = new ReportGenerationResult(
            title: 'Payroll Register',
            headers: $headers,
            rows: [$row],
            meta: [
                'layout' => 'icct_per_hour',
                'subheaders' => array_fill(0, 84, ''),
                'highlight_indices' => [],
                'company_name' => 'ICCT COLLEGES FOUNDATION, INC.',
                'subtitle' => 'PAYROLL REGISTER - FACULTY ONLINE CLASSES',
                'period_label' => 'Jun 27, 2026 - Jul 10, 2026',
                'batch_labels' => ['Batch No. 0003 — Posted'],
            ],
        );

        $response = ReportPdfDownload::stream($result, 'Payroll_Register_Wide_Test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Payroll_Register_Wide_Test.pdf', (string) $response->headers->get('Content-Disposition'));
    }
}
