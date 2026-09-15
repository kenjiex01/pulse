<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Services\CompanyDocumentMemoRenderService;
use App\Support\CompanyDocumentMemoPdfLayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDocumentMemoRenderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_render_html_includes_resolved_fields_and_signature(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-RENDER-001',
            'first_name' => 'Angel',
            'last_name' => 'Martinez',
            'email' => 'angel@example.com',
        ]);

        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $form = CompanyDocumentForm::query()->create([
            'code' => 'render_memo',
            'name' => 'Absent Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_SHORT_TEXT,
            'label' => 'Subject',
            'field_key' => 'subject',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'default_text' => 'Notice of absence'],
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_SIGNATURE,
            'label' => 'Author Signature',
            'field_key' => 'author_signature',
            'sort_order' => 2,
            'settings_json' => [
                'label_align' => 'top',
                'signature_preview' => [
                    'mode' => 'draw',
                    'dataUrl' => $dataUrl,
                    'fileName' => 'signature.png',
                ],
            ],
        ]);

        $service = app(CompanyDocumentMemoRenderService::class);
        $preview = $service->buildPreviewData($form, $employee, [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-07',
            'violation_type' => 'absent',
            'violation_count' => 2,
            'selected_dates' => ['2026-09-05', '2026-09-06'],
        ]);

        $html = $service->renderHtml($preview);

        $this->assertStringContainsString('Absent Memo', $html);
        $this->assertStringContainsString('Notice of absence', $html);
        $this->assertStringContainsString('Author Signature', $html);
        $this->assertStringContainsString($dataUrl, $html);
    }

    public function test_render_pdf_returns_pdf_bytes(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-RENDER-002',
            'first_name' => 'Angel',
            'last_name' => 'Martinez',
            'email' => 'angel2@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'render_memo_pdf',
            'name' => 'Absent Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_SHORT_TEXT,
            'label' => 'Subject',
            'field_key' => 'subject',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'default_text' => 'Notice of absence'],
        ]);

        $service = app(CompanyDocumentMemoRenderService::class);
        $preview = $service->buildPreviewData($form, $employee, [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-07',
            'violation_type' => 'absent',
            'violation_count' => 1,
            'selected_dates' => ['2026-09-05'],
        ]);

        $pdf = $service->renderPdf($preview);

        $this->assertNotSame('', $pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertLessThanOrEqual(2, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_render_pdf_includes_resolved_memo_text(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-RENDER-003',
            'first_name' => 'Criselda',
            'last_name' => 'Bautista',
            'email' => 'criselda@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'render_memo_pdf_text',
            'name' => 'Absent Memo PDF',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_HEADING,
            'label' => 'Internal Memo',
            'sort_order' => 0,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 0, 'pos_y' => 16],
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee Name: {{employee_full_name}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 0, 'pos_y' => 80],
        ]);

        $service = app(CompanyDocumentMemoRenderService::class);
        $preview = $service->buildPreviewData($form, $employee, [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'violation_type' => 'absent',
            'violation_count' => 2,
            'selected_dates' => ['2026-08-05', '2026-08-12'],
        ]);

        $html = view('pdf.company-document-memo-document', [
            'form' => $preview['form'],
            'layout' => CompanyDocumentMemoPdfLayout::buildForOutput(
                collect($preview['elements'])->sortBy('sort_order')->values()->all(),
            ),
            'previewValues' => $preview['preview_values'],
        ])->render();

        $this->assertStringContainsString('Internal Memo', $html);
        $this->assertStringContainsString($employee->full_name, $html);
    }

    public function test_render_pdf_html_fills_full_legal_page_with_canvas_background(): void
    {
        $form = CompanyDocumentForm::query()->create([
            'code' => 'render_memo_pdf_bg',
            'name' => 'Colored Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
            'settings_json' => ['canvas_background_color' => '#F3F4F6'],
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_HEADING,
            'label' => 'Internal Memo',
            'sort_order' => 0,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 0, 'pos_y' => 16],
        ]);

        $service = app(CompanyDocumentMemoRenderService::class);
        $preview = $service->buildSamplePreviewData($form);
        $html = $service->renderDocumentHtml($preview, browserPreview: false);

        $this->assertStringContainsString('background-color: #F3F4F6', $html);
        $this->assertStringContainsString('height: '.CompanyDocumentMemoPdfLayout::legalPageHeightPx().'px', $html);
        $this->assertStringContainsString('width: '.CompanyDocumentMemoPdfLayout::legalPageWidthPx().'px', $html);
        $this->assertStringContainsString('memo-legal-page-inner', $html);
    }
}
