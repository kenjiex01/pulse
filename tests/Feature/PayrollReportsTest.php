<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_reports_index_loads_for_admin(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.index'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Payroll Register')
            ->assertSee('SSS Monthly Contribution');
    }

    public function test_report_options_partial_loads_for_payroll_register(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payroll Register')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('Payroll Register Options')
            ->assertSee('payroll_batch_ids')
            ->assertSee('Employee Type')
            ->assertSee('Staff')
            ->assertSee('one worksheet per campus');
    }

    public function test_report_options_partial_loads_for_bir_tax_withheld(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', "BIR Employees' Tax Withheld")->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('BIR Employees')
            ->assertSee('payroll_batch_ids')
            ->assertSee('minimum wage earner');
    }

    public function test_report_options_partial_loads_for_sss_contribution(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'SSS Monthly Contribution')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('SSS Monthly Contribution Options')
            ->assertSee('same pay month and pay year');
    }

    public function test_report_options_partial_loads_for_philhealth_contribution(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'PhilHealth Contribution')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('PhilHealth Contribution Options')
            ->assertSee('Philhealth');
    }

    public function test_report_options_partial_loads_for_pagibig_contribution(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Pag-IBIG Contribution')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('Pag-IBIG Contribution Options')
            ->assertSee('Pag-ibig');
    }

    public function test_report_options_partial_loads_for_payslip(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('Payslip Options')
            ->assertSee('Posted Payroll Batch')
            ->assertSee('data-payslip-batch-select', false);
    }

    public function test_report_options_partial_loads_for_bir_1601c(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'BIR Form 1601-C')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('BIR Form 1601-C Options')
            ->assertSee('Posted Payroll Batch')
            ->assertSee('payroll_batch_ids')
            ->assertSee('same pay month and pay year')
            ->assertSee('Include 13th month pay (whole year)')
            ->assertSee('data-batch-employee-batch-select', false)
            ->assertSee('data-payroll-batch-month-guard', false);
    }

    public function test_report_options_partial_loads_for_bir_2316(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'BIR Form 2316')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('BIR Form 2316 Options')
            ->assertSee('Payroll Year')
            ->assertDontSee('Posted Payroll Batch')
            ->assertSee('data-employees-param="pay_year"', false);
    }

    public function test_batch_employees_returns_empty_for_non_posted_batch(): void
    {
        $user = User::query()->firstOrFail();
        $batch = \App\Models\PayrollBatch::query()->first();

        if ($batch === null) {
            $this->markTestSkipped('No payroll batch seeded.');

            return;
        }

        $this->actingAs($user)
            ->getJson(route('payroll.reports.batch-employees', ['payroll_batch_id' => $batch->payroll_batch_id]))
            ->assertOk()
            ->assertJson(['employees' => []]);
    }

    public function test_generate_payslip_requires_batch_and_employees(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors(['payroll_batch_id', 'employee_ids']);
    }

    public function test_generate_bir_1601c_requires_batch_and_employees(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'BIR Form 1601-C')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors(['payroll_batch_ids', 'employee_ids']);
    }

    public function test_generate_bir_2316_requires_year_and_employees(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'BIR Form 2316')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors(['pay_year', 'employee_ids']);
    }

    public function test_generate_requires_processed_batch_selection(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payroll Register')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors('payroll_batch_ids');
    }

    public function test_reports_index_shows_human_resource_historical_data(): void
    {
        $user = User::query()->firstOrFail();
        $employeeReport = Report::query()->where('title', 'Employee')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.index', ['classification' => 'human-resource']))
            ->assertOk()
            ->assertSee('Human Resource')
            ->assertSee('Historical Data');

        $this->actingAs($user)
            ->get(route('payroll.reports.index', [
                'classification' => 'human-resource',
                'report_id' => $employeeReport->report_id,
            ]))
            ->assertOk()
            ->assertSee('Full employee listing: personal, assignments, employment, salary, shift codes');
    }

    public function test_report_options_partial_loads_for_historical_data(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Historical Data')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', [
                'report' => $report->report_id,
                'classification' => 'human-resource',
            ]))
            ->assertOk()
            ->assertSee('Historical Data Options')
            ->assertSee('employee_ids');
    }

    public function test_generate_historical_data_report_preview(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Historical Data')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'human-resource',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertOk()
            ->assertSee('Historical Data');
    }

    public function test_report_options_partial_loads_for_employee_credentials(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Employee')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', [
                'report' => $report->report_id,
                'classification' => 'human-resource',
            ]))
            ->assertOk()
            ->assertSee('Employee Options')
            ->assertSee('employee_ids')
            ->assertDontSee('credential document')
            ->assertDontSee('credential column');
    }

    public function test_generate_employee_credentials_report_preview(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Employee')->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-CRED-RPT',
            'first_name' => 'Liza',
            'middle_name' => 'M',
            'last_name' => 'Reyes',
            'email' => 'liza.reyes.cred@example.com',
        ]);

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'human-resource',
                'report_id' => $report->report_id,
                'output_format' => 'html',
                'employee_ids' => [$employee->employee_id],
            ])
            ->assertOk()
            ->assertSee('Employee')
            ->assertSee('Reyes, Liza M')
            ->assertDontSee('TIN ID')
            ->assertSee('Shift Code')
            ->assertSee('Assignments');
    }

    public function test_reports_index_shows_timekeeping_attendance_view(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.index', ['classification' => 'timekeeping']))
            ->assertOk()
            ->assertSee('Timekeeping')
            ->assertSee('Attendance View');
    }

    public function test_report_options_partial_loads_for_attendance_view(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Attendance View')->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-AV-OPT',
            'first_name' => 'Option',
            'last_name' => 'Picker',
            'email' => 'av.option@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('payroll.reports.options', [
                'report' => $report->report_id,
                'classification' => 'timekeeping',
            ]))
            ->assertOk()
            ->assertSee('Attendance View Options')
            ->assertSee('date_from', false)
            ->assertSee('date_to', false)
            ->assertSee('data-employee-multiselect-search', false)
            ->assertSee('EMP-AV-OPT')
            ->assertSee($employee->full_name);
    }

    public function test_generate_attendance_view_requires_dates_and_employees(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Attendance View')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'timekeeping',
                'report_id' => $report->report_id,
                'output_format' => 'pdf',
            ])
            ->assertSessionHasErrors(['date_from', 'date_to', 'employee_ids']);
    }

    public function test_generate_attendance_view_pdf_includes_selected_employees(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Attendance View')->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-AV-RPT',
            'first_name' => 'Report',
            'last_name' => 'Subject',
            'email' => 'av.report@example.com',
        ]);

        $response = $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'timekeeping',
                'report_id' => $report->report_id,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-14',
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'pdf',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        $text = $this->extractPdfText($pdf);
        $this->assertStringContainsString('Attendance View', $text);
        $this->assertStringContainsString('EMP-AV-RPT', $text);
        $this->assertStringContainsString('08/01/2026', $text);
        $this->assertStringContainsString('08/14/2026', $text);
    }

    private function extractPdfText(string $binary): string
    {
        $payload = $binary;

        if (preg_match_all('/stream\s*\r?\n(.+?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $plain = @gzuncompress($stream) ?: @gzinflate($stream);
                if (is_string($plain) && $plain !== '') {
                    $payload .= "\n".$plain;
                }
            }
        }

        $chunks = [];

        if (preg_match_all('/\\[\\((.*?)\\)\\]\\s*TJ/s', $payload, $matches)) {
            foreach ($matches[1] as $raw) {
                $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $raw);
                $chunks[] = $this->pdfLiteralToUtf8($raw);
            }
        }

        if (preg_match_all('/\\((.*?)\\)\\s*Tj/s', $payload, $matches)) {
            foreach ($matches[1] as $raw) {
                $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $raw);
                $chunks[] = $this->pdfLiteralToUtf8($raw);
            }
        }

        return implode("\n", $chunks);
    }

    private function pdfLiteralToUtf8(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $isUtf16Be = str_starts_with($raw, "\x00")
            || (strlen($raw) >= 2 && (ord($raw[0]) === 0 || ord($raw[1]) === 0));

        if ($isUtf16Be) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');

            return is_string($converted) ? $converted : $raw;
        }

        return $raw;
    }
}
