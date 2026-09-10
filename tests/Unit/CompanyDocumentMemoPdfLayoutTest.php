<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Services\CompanyDocumentMemoRenderService;
use App\Support\CompanyDocumentMemoDompdfPreparer;
use App\Support\CompanyDocumentMemoPdfLayout;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyDocumentMemoPdfLayoutTest extends TestCase
{
    public function test_build_preserves_saved_designer_positions_at_preview_width(): void
    {
        $layout = CompanyDocumentMemoPdfLayout::build([
            [
                'type' => 'short_text',
                'label' => 'Memo To',
                'field_key' => 'memo_to',
                'width' => 'full',
                'sort_order' => 1,
                'settings' => ['pos_x' => 0, 'pos_y' => 68],
            ],
            [
                'type' => 'image',
                'label' => 'Image',
                'width' => 'full',
                'sort_order' => 8,
                'settings' => ['pos_x' => 0, 'pos_y' => 70, 'image_width' => 436, 'image_height' => 194],
            ],
        ]);

        $this->assertSame(640, $layout['canvas_width']);
        $this->assertSame(68, $layout['elements'][0]['layout']['y']);
        $this->assertSame(70, $layout['elements'][1]['layout']['y']);
        $this->assertSame(194, $layout['elements'][1]['layout']['height_px']);
        $this->assertSame('436px', $layout['elements'][1]['layout']['width_css']);
        $this->assertSame('100%', $layout['elements'][0]['layout']['width_css']);
        $this->assertGreaterThan(300, $layout['canvas_height']);
    }

    public function test_build_for_pdf_uses_legal_page_and_paginates_overflow(): void
    {
        $layout = CompanyDocumentMemoPdfLayout::buildForPdf([
            [
                'type' => 'heading',
                'label' => 'Internal Memo',
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 16],
            ],
            [
                'type' => 'short_text',
                'label' => 'Overflow field',
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 2000],
            ],
        ]);

        $this->assertSame(640, CompanyDocumentMemoPdfLayout::legalContentWidthPx());
        $this->assertSame(88, CompanyDocumentMemoPdfLayout::previewSidePaddingPx());
        $this->assertSame(20, CompanyDocumentMemoPdfLayout::pdfContentBoxSize(38, 8, 8));
        $this->assertSame(598, CompanyDocumentMemoPdfLayout::pdfContentBoxSize(
            CompanyDocumentMemoPdfLayout::pdfFieldBorderBoxWidth(640),
            12,
            12,
        ));
        $this->assertSame(CompanyDocumentMemoPdfLayout::legalContentWidthPx(), $layout['canvas_width']);
        $this->assertSame(CompanyDocumentMemoPdfLayout::legalContentHeightPx(), $layout['canvas_height']);
        $this->assertSame(2, $layout['page_count']);
        $this->assertCount(2, $layout['pages']);
        $this->assertSame(16, $layout['pages'][0]['elements'][0]['layout']['y']);
        $this->assertSame(2000 - CompanyDocumentMemoPdfLayout::legalContentHeightPx(), $layout['pages'][1]['elements'][0]['layout']['y']);

        $paper = CompanyDocumentMemoPdfLayout::pageSizePoints();
        $this->assertSame(612.0, $paper['width']);
        $this->assertSame(1008.0, $paper['height']);
    }

    public function test_build_clamps_image_that_would_overflow_the_right_edge(): void
    {
        $layout = CompanyDocumentMemoPdfLayout::build([
            [
                'type' => 'image',
                'label' => 'Image',
                'settings' => ['pos_x' => 300, 'pos_y' => 70, 'image_width' => 436, 'image_height' => 194],
            ],
        ]);

        $this->assertSame(204, $layout['elements'][0]['layout']['x']);
        $this->assertSame('436px', $layout['elements'][0]['layout']['width_css']);
    }

    public function test_build_scales_image_wider_than_the_canvas(): void
    {
        $layout = CompanyDocumentMemoPdfLayout::build([
            [
                'type' => 'image',
                'label' => 'Image',
                'settings' => ['pos_x' => 0, 'pos_y' => 70, 'image_width' => 800, 'image_height' => 200],
            ],
        ]);

        $this->assertSame(0, $layout['elements'][0]['layout']['x']);
        $this->assertSame('640px', $layout['elements'][0]['layout']['width_css']);
        $this->assertSame(160, $layout['elements'][0]['layout']['height_px']);
    }

    public function test_build_auto_positions_unsaved_elements_like_preview(): void
    {
        $layout = CompanyDocumentMemoPdfLayout::build([
            [
                'type' => 'heading',
                'label' => 'Internal Memo',
                'width' => 'full',
                'sort_order' => 0,
                'settings' => ['label_align' => 'top'],
            ],
            [
                'type' => 'short_text',
                'label' => 'Memo To',
                'field_key' => 'memo_to',
                'width' => 'full',
                'sort_order' => 1,
                'settings' => ['label_align' => 'top'],
            ],
        ], CompanyDocumentMemoPdfLayout::DESIGNER_REFERENCE_WIDTH);

        $this->assertSame(CompanyDocumentMemoPdfLayout::CANVAS_INSET, $layout['elements'][0]['layout']['y']);
        $this->assertGreaterThan(
            $layout['elements'][0]['layout']['y'],
            $layout['elements'][1]['layout']['y'],
        );
    }

    public function test_display_value_formats_date_like_preview(): void
    {
        $value = CompanyDocumentMemoPdfLayout::displayValue([
            'type' => 'date',
            'label' => 'Date',
            'field_key' => 'memo_date',
            'settings' => [],
        ], 0, ['memo_date' => 'Date']);

        $this->assertSame(now()->format('m/d/Y'), $value);
    }
}

class CompanyDocumentMemoPdfParityTest extends TestCase
{
    public function test_render_pdf_matches_designer_layout_with_image_and_signature(): void
    {
        Storage::fake('local');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mNk+M9Qz0AEYBxVSF+FABJADveWkH6oAAAAAElFTkSuQmCC');
        Storage::disk('local')->put('company-documents/sample-stamp.png', $png);

        $signatureDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $stampDataUrl = 'data:image/png;base64,'.base64_encode($png);

        $form = new CompanyDocumentForm([
            'code' => 'hr_internal_memo_absent',
            'name' => 'Internal Memo (HR-MEMO-1)',
            'description' => 'Standard company memo for HR announcements.',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
        ]);

        $elements = [
            [
                'type' => CompanyDocumentElement::TYPE_HEADING,
                'label' => 'Internal Memo',
                'sort_order' => 0,
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 16],
            ],
            [
                'type' => CompanyDocumentElement::TYPE_SHORT_TEXT,
                'label' => 'Memo To',
                'field_key' => 'memo_to',
                'sort_order' => 1,
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 68],
            ],
            [
                'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
                'label' => 'Employee: Angel May Martinez · Absences: 18',
                'sort_order' => 7,
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 520],
            ],
            [
                'type' => 'image',
                'label' => 'Sample stamp',
                'sort_order' => 8,
                'width' => 'full',
                'settings' => [
                    'pos_x' => 0,
                    'pos_y' => 70,
                    'image_width' => 436,
                    'image_height' => 194,
                    'image_opacity' => 35,
                    'image_data_url' => $stampDataUrl,
                ],
                'options' => ['path' => 'company-documents/sample-stamp.png'],
            ],
            [
                'type' => CompanyDocumentElement::TYPE_SIGNATURE,
                'label' => 'Author Signature',
                'field_key' => 'author_signature',
                'sort_order' => 6,
                'width' => 'full',
                'settings' => ['pos_x' => 0, 'pos_y' => 430],
            ],
        ];

        $preview = [
            'form' => $form,
            'elements' => $elements,
            'preview_values' => [
                'author_signature' => [
                    'mode' => 'draw',
                    'dataUrl' => $signatureDataUrl,
                    'fileName' => 'signature.png',
                ],
            ],
        ];

        $layout = CompanyDocumentMemoPdfLayout::build($elements);
        $html = view('pdf.company-document-memo-document', [
            'form' => $preview['form'],
            'layout' => $layout,
            'previewValues' => $preview['preview_values'],
        ])->render();

        $this->assertStringContainsString('top:70px', $html);
        $this->assertStringContainsString('z-index:30', $html);
        $this->assertStringContainsString('Angel May Martinez', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('memo-pdf-form-column', $html);
        $this->assertStringContainsString('memo-pdf-field-box', $html);
        $this->assertStringContainsString('border-radius: 6px', $html);
        $this->assertStringContainsString('height: 20px', $html);
        $this->assertStringContainsString('width: 598px', $html);
        $this->assertStringContainsString('margin-left:', $html);

        $preparer = new CompanyDocumentMemoDompdfPreparer();
        $prepared = $preparer->prepare($html);
        $this->assertStringContainsString('src="image-1.png"', $prepared['html']);
        $preparer->cleanup($prepared['cleanup']);

        $service = app(CompanyDocumentMemoRenderService::class);
        $pdf = $service->renderPdf($preview);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
        $this->assertGreaterThan(5000, strlen($pdf));
    }

    public function test_materialize_fits_large_stamp_into_designer_box(): void
    {
        $wide = imagecreatetruecolor(646, 361);
        imagealphablending($wide, false);
        imagesavealpha($wide, true);
        $transparent = imagecolorallocatealpha($wide, 0, 0, 0, 127);
        imagefilledrectangle($wide, 0, 0, 645, 360, $transparent);
        ob_start();
        imagepng($wide);
        $png = ob_get_clean();

        $preparer = new CompanyDocumentMemoDompdfPreparer();
        $prepared = $preparer->materializePreviewImages([
            'form' => null,
            'elements' => [[
                'type' => 'image',
                'settings' => [
                    'image_width' => 436,
                    'image_height' => 194,
                    'image_opacity' => 53,
                    'image_data_url' => 'data:image/png;base64,'.base64_encode($png),
                ],
            ]],
            'preview_values' => [],
        ]);

        $path = $prepared['chroot'].'/image-1.png';
        $this->assertFileExists($path);
        $info = getimagesize($path);
        $this->assertSame(436, $info[0]);
        $this->assertSame(194, $info[1]);
        $preparer->cleanup($prepared['cleanup']);
    }
}
