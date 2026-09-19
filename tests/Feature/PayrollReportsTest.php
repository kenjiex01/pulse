<?php

namespace Tests\Feature;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
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
            ->assertSee('data-payslip-batch-select', false)
            ->assertSee('PDF Delivery')
            ->assertSee('data-payslip-pdf-mode-wrap', false);
    }

    public function test_generate_payslip_html_matches_batch_net_pay_labels(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $response = $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'html',
            ]);

        $response->assertOk();
        $response->assertSee('Basic Income');
        $response->assertSee('95.75');
        $response->assertSee('15 min');
        $response->assertSee('Late');
        $response->assertDontSee('0.25 hrs');
        $response->assertDontSee('1.00 days');
        $response->assertSee('Mins');
    }

    public function test_generate_payslip_html_keeps_mins_column_without_late_deduction(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user, includeLate: false);

        $response = $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'html',
            ]);

        $response->assertOk();
        $response->assertSee('Hours');
        $response->assertSee('Mins');
        $response->assertDontSee('Late');
    }

    public function test_generate_payslip_combined_pdf_downloads(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $response = $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'pdf',
                'payslip_pdf_mode' => 'combined',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_generate_payslip_individual_pdf_zip_downloads(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $response = $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'pdf',
                'payslip_pdf_mode' => 'individual_zip',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        $zipBinary = $response->streamedContent();
        $zipPath = tempnam(sys_get_temp_dir(), 'payslip_test_zip_');
        file_put_contents($zipPath, $zipBinary);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString('Fixture Roselyn Test Jul', $zip->getNameIndex(0));
        $this->assertStringEndsWith('.pdf', $zip->getNameIndex(0));

        $pdfContent = $zip->getFromIndex(0);
        $zip->close();
        @unlink($zipPath);

        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    public function test_generate_payslip_rejects_invalid_pdf_mode(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payslip')->firstOrFail();
        [$batch, $employee] = $this->makePostedPayslipFixture($user);

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'payroll_batch_id' => $batch->payroll_batch_id,
                'employee_ids' => [$employee->employee_id],
                'output_format' => 'pdf',
                'payslip_pdf_mode' => 'invalid',
            ])
            ->assertSessionHasErrors('payslip_pdf_mode');
    }

    /**
     * @return array{0: PayrollBatch, 1: Employee}
     */
    private function makePostedPayslipFixture(User $user, bool $includeLate = true): array
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PSLIP-001',
            'first_name' => 'Roselyn',
            'middle_name' => 'Test',
            'last_name' => 'Fixture',
            'email' => 'payslip.fixture@example.com',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 2,
            'dt_from' => '2026-07-27 00:00:00',
            'dt_to' => '2026-08-10 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 4,
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
            'dt_processed' => now(),
            'processed_by_id' => $user->id,
            'dt_posted' => now(),
            'posted_by_id' => $user->id,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        $basicType = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => $basicType->income_type_id,
            'hours' => 95.75,
            'taxable' => 7181.25,
            'non_taxable' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);

        $lateType = DeductionType::query()->where('deduction_type_code', 'LTDE')->firstOrFail();
        if ($includeLate) {
            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $lateType->deduction_type_id,
                'hours' => 0.25,
                'days' => 1,
                'employee_amount' => 18.75,
                'employer_amount' => 0,
                'is_editable' => false,
                'is_deletable' => false,
                'is_manual' => false,
            ]);
        }

        $philType = DeductionType::query()->where('deduction_type_code', 'PHIM')->firstOrFail();
        PayrollDeduction::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'deduction_type_id' => $philType->deduction_type_id,
            'employee_amount' => 250,
            'employer_amount' => 0,
            'is_editable' => false,
            'is_deletable' => false,
            'is_manual' => false,
        ]);

        return [$batch->fresh(['payrollCalendar']), $employee];
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

    public function test_reports_index_shows_memo_under_human_resource(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.index', ['classification' => 'human-resource']))
            ->assertOk()
            ->assertSee('Memo');
    }

    public function test_report_options_partial_loads_for_memo(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Memo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', [
                'report' => $report->report_id,
                'classification' => 'human-resource',
            ]))
            ->assertOk()
            ->assertSee('Memo Options')
            ->assertSee('company_document_form_ids')
            ->assertSee('Select all');
    }

    public function test_generate_memo_report_preview(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Memo')->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_verbal_reprimand')->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-MEMO-RPT',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'email' => 'ana.santos.memo@example.com',
        ]);

        CompanyDocumentSendLog::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'submission_id' => null,
            'sent_by_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'human-resource',
                'report_id' => $report->report_id,
                'output_format' => 'html',
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
                'company_document_form_ids' => [$form->company_document_form_id],
            ])
            ->assertOk()
            ->assertSee('Memo')
            ->assertSee('Ana Santos')
            ->assertSee('Verbal Reprimand Record')
            ->assertSee('First Offense')
            ->assertSee('Company Documents');
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
