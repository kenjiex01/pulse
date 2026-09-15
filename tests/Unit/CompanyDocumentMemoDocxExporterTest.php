<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Services\CompanyDocumentMemoRenderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class CompanyDocumentMemoDocxExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_render_docx_produces_valid_docx_for_nte_template(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_notice_to_explain')->firstOrFail();
        $service = app(CompanyDocumentMemoRenderService::class);
        $preview = $service->buildSamplePreviewData($form);

        $docx = $service->renderDocx($preview);

        $this->assertStringStartsWith('PK', $docx);
        $this->assertGreaterThan(2000, strlen($docx));
        $xml = $this->documentXml($docx);
        $this->assertStringNotContainsString('position:absolute', $xml);
        $this->assertStringContainsString('ICCT Colleges Foundation, Inc.', $xml);

        $path = tempnam(sys_get_temp_dir(), 'pulse-nte-docx-').'.docx';
        file_put_contents($path, $docx);

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path));
            $this->assertNotFalse($zip->locateName('word/document.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_render_docx_includes_resolved_paragraph_text(): void
    {
        $form = CompanyDocumentForm::query()->create([
            'code' => 'docx_export_test',
            'name' => 'DOCX Export Test',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee: {{employee_full_name}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top'],
        ]);

        $service = app(CompanyDocumentMemoRenderService::class);
        $docx = $service->renderDocx($service->buildSamplePreviewData($form->fresh('elements')));

        $path = tempnam(sys_get_temp_dir(), 'pulse-docx-text-').'.docx';
        file_put_contents($path, $docx);

        try {
            $zip = new ZipArchive();
            $zip->open($path);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertIsString($xml);
            $this->assertStringContainsString('Employee:', $xml);
            $this->assertStringContainsString('Juan Dela Cruz', $xml);
            $this->assertStringNotContainsString('position:absolute', $xml);
        } finally {
            @unlink($path);
        }
    }

    private function documentXml(string $docxBinary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pulse-docx-xml-').'.docx';
        file_put_contents($path, $docxBinary);

        try {
            $zip = new ZipArchive();
            $zip->open($path);

            return (string) $zip->getFromName('word/document.xml');
        } finally {
            @unlink($path);
        }
    }
}
