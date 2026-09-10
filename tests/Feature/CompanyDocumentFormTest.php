<?php

namespace Tests\Feature;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyDocumentFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_company_documents_index(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('company-documents.index'))
            ->assertOk()
            ->assertSee('Company Documents', false)
            ->assertSee('Preview', false)
            ->assertSee('company-document-preview-modal', false);
    }

    public function test_admin_can_create_memo_template(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('company-documents.store'), [
                'form_context' => 'create-company-document',
                'name' => 'Test Memo Template',
                'code' => 'test_memo_template',
                'document_type' => CompanyDocumentForm::TYPE_MEMO,
                'description' => 'Sample memo for testing.',
                'allow_multiple_submissions' => '1',
                'submit_label' => 'Submit',
                'success_message' => 'Submitted.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_company_document_forms', [
            'code' => 'test_memo_template',
            'name' => 'Test Memo Template',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
        ]);
    }

    public function test_sample_memo_seeder_is_available_after_bootstrap(): void
    {
        $this->assertDatabaseHas('tbl_company_document_forms', [
            'code' => 'hr_internal_memo',
        ]);
    }

    public function test_memo_template_preview_modal_uses_pdf_iframe(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('company-documents.index', ['view_form' => $form->company_document_form_id]))
            ->assertOk()
            ->assertSee('company-documents/'.$form->company_document_form_id.'/preview-html', false)
            ->assertSee('Legal size (8.5 × 14 in)', false);
    }

    public function test_memo_template_preview_html_returns_designer_document(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $response = $this->actingAs(User::query()->firstOrFail())
            ->get(route('company-documents.preview-html', $form));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html = $response->getContent();
        $this->assertStringContainsString('Internal Memo', $html);
        $this->assertStringContainsString('cd-designer-canvas-inner', $html);
        $this->assertStringContainsString('8.5in', $html);
        $this->assertStringContainsString('14in', $html);
        $this->assertStringContainsString('padding: 16px 88px', $html);
        $this->assertStringContainsString('data-memo-preview-desk', $html);
        $this->assertStringContainsString('desk.style.zoom', $html);
        $this->assertStringNotContainsString('memo-pdf-form-column', $html);
        $this->assertStringNotContainsString('memo-pdf-field-box', $html);
    }

    public function test_memo_template_preview_pdf_returns_valid_single_page_pdf(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $response = $this->actingAs(User::query()->firstOrFail())
            ->get(route('company-documents.preview-pdf', $form));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
        $this->assertGreaterThan(5000, strlen($pdf));
        $this->assertMatchesRegularExpression('/MediaBox\s*\[[^\]]*612[^\]]*1008/', $pdf);
    }

    public function test_designer_save_persists_field_positions(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'short_text',
                        'label' => 'Memo To',
                        'field_key' => 'memo_to',
                        'width' => 'half',
                        'is_required' => true,
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 120,
                            'pos_y' => 80,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'memo_to')
            ->firstOrFail();

        $this->assertSame(120, $element->settings_json['pos_x'] ?? null);
        $this->assertSame(80, $element->settings_json['pos_y'] ?? null);
    }

    public function test_designer_paragraph_newlines_persist(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();
        $label = "First line of the memo.\n\nSecond paragraph after a blank line.";

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'paragraph',
                        'label' => $label,
                        'field_key' => 'body_text',
                        'width' => 'full',
                        'is_required' => false,
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 0,
                            'pos_y' => 40,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'body_text')
            ->firstOrFail();

        $this->assertSame($label, $element->label);
        $this->assertStringContainsString("\n\n", $element->label);
    }

    public function test_designer_image_upload_and_dimensions_persist(): void
    {
        Storage::fake('local');

        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('company-documents.designer.upload-image', $form), [
                'image' => UploadedFile::fake()->image('memo-logo.png', 320, 200),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $path = (string) $response->json('path');
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'image',
                        'label' => 'Logo',
                        'width' => 'full',
                        'options' => [
                            'path' => $path,
                            'original_filename' => 'memo-logo.png',
                        ],
                        'settings' => [
                            'pos_x' => 40,
                            'pos_y' => 60,
                            'image_width' => 280,
                            'image_height' => 180,
                            'image_opacity' => 45,
                            'image_rotate' => 15,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('type', 'image')
            ->firstOrFail();

        $this->assertSame($path, $element->options_json['path'] ?? null);
        $this->assertSame(280, $element->settings_json['image_width'] ?? null);
        $this->assertSame(180, $element->settings_json['image_height'] ?? null);
        $this->assertSame(45, $element->settings_json['image_opacity'] ?? null);
        $this->assertSame(15, $element->settings_json['image_rotate'] ?? null);

        $this->actingAs($user)
            ->get(route('company-documents.designer.asset', $form).'?path='.urlencode($path))
            ->assertOk();
    }

    public function test_designer_field_box_size_persists(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'long_text',
                        'label' => 'Message Body',
                        'field_key' => 'message_body',
                        'width' => 'full',
                        'is_required' => true,
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 24,
                            'pos_y' => 80,
                            'box_width' => 420,
                            'box_height' => 160,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'message_body')
            ->firstOrFail();

        $this->assertSame(420, $element->settings_json['box_width'] ?? null);
        $this->assertSame(160, $element->settings_json['box_height'] ?? null);
        $this->assertSame(24, $element->settings_json['pos_x'] ?? null);
    }

    public function test_designer_png_upload_keeps_transparency(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to verify PNG transparency.');
        }

        Storage::fake('local');

        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();
        $pngPath = $this->makeTransparentStampPng();

        try {
            $response = $this->actingAs($user)
                ->post(route('company-documents.designer.upload-image', $form), [
                    'image' => new UploadedFile($pngPath, 'stamp.png', 'image/png', null, true),
                ])
                ->assertOk()
                ->assertJson(['ok' => true]);

            $storedPath = (string) $response->json('path');
            Storage::disk('local')->assertExists($storedPath);

            $loaded = imagecreatefrompng(Storage::disk('local')->path($storedPath));
            $this->assertNotFalse($loaded);
            $alpha = ((imagecolorat($loaded, 0, 0) & 0x7F000000) >> 24);
            imagedestroy($loaded);

            $this->assertGreaterThan(0, $alpha);
        } finally {
            @unlink($pngPath);
        }
    }

    private function makeTransparentStampPng(): string
    {
        $path = sys_get_temp_dir().'/pulse-stamp-'.uniqid('', true).'.png';
        $image = imagecreatetruecolor(24, 24);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, 23, 23, $transparent);
        $red = imagecolorallocatealpha($image, 220, 20, 30, 0);
        imagefilledrectangle($image, 7, 7, 16, 16, $red);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_submission_stores_uploaded_signature_file(): void
    {
        Storage::fake('local');

        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $signature = UploadedFile::fake()->image('author-signature.png', 420, 120);

        $response = $this->actingAs($user)
            ->post(route('company-documents.submissions.store', $form), [
                'values' => [
                    'memo_to' => 'All Staff',
                    'memo_from' => 'HR',
                    'memo_date' => '2026-09-03',
                    'subject' => 'Policy Update',
                    'message_body' => 'Please review the attached policy.',
                ],
                'files' => [
                    'author_signature' => $signature,
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tbl_company_document_submissions', [
            'company_document_form_id' => $form->company_document_form_id,
            'submitted_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('tbl_company_document_submission_values', [
            'field_key' => 'author_signature',
            'original_filename' => 'author-signature.png',
        ]);

        $storedPath = \App\Models\CompanyDocumentSubmissionValue::query()
            ->where('field_key', 'author_signature')
            ->value('file_path');

        $this->assertNotEmpty($storedPath);
        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_designer_save_persists_merge_tag_elements(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'merge_tag',
                        'label' => 'Employee Full name',
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 40,
                            'pos_y' => 40,
                            'tag_key' => 'employee_full_name',
                        ],
                    ],
                    [
                        'type' => 'merge_tag',
                        'label' => 'Current datetime',
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 40,
                            'pos_y' => 100,
                            'tag_key' => 'current_datetime',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tbl_company_document_elements', [
            'company_document_form_id' => $form->company_document_form_id,
            'type' => 'merge_tag',
            'label' => 'Employee Full name',
        ]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('type', 'merge_tag')
            ->where('label', 'Employee Full name')
            ->firstOrFail();

        $this->assertSame('employee_full_name', $element->settings_json['tag_key'] ?? null);
    }

    public function test_designer_save_persists_signature_preview_drawing(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'signature',
                        'label' => 'Author Signature',
                        'field_key' => 'author_signature',
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 40,
                            'pos_y' => 40,
                            'signature_preview' => [
                                'mode' => 'draw',
                                'dataUrl' => $dataUrl,
                                'fileName' => 'signature.png',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'author_signature')
            ->firstOrFail();

        $this->assertSame('draw', $element->settings_json['signature_preview']['mode'] ?? null);
        $this->assertSame($dataUrl, $element->settings_json['signature_preview']['dataUrl'] ?? null);
    }

    public function test_designer_page_includes_tags_palette_category(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('company-documents.designer', $form))
            ->assertOk()
            ->assertSee('Employee Full name', false)
            ->assertSee('Count of lates', false)
            ->assertSee('Late dates', false)
            ->assertSee('Absent dates', false)
            ->assertSee('Current datetime', false);
    }

    public function test_designer_paragraph_font_settings_persist(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'paragraph',
                        'label' => 'Employee Name: {{employee_full_name}}',
                        'field_key' => 'memo_body',
                        'width' => 'full',
                        'is_required' => false,
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 0,
                            'pos_y' => 120,
                            'font_family' => 'Georgia, serif',
                            'font_size' => 18,
                            'font_color' => '#111827',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'memo_body')
            ->firstOrFail();

        $this->assertSame('Georgia, serif', $element->settings_json['font_family'] ?? null);
        $this->assertSame(18, $element->settings_json['font_size'] ?? null);
        $this->assertSame('#111827', $element->settings_json['font_color'] ?? null);
    }

    public function test_designer_paragraph_inline_formatting_persists(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();
        $label = 'Employee Name: <b>{{employee_full_name}}</b>';

        $this->actingAs($user)
            ->putJson(route('company-documents.designer.elements', $form), [
                'elements' => [
                    [
                        'type' => 'paragraph',
                        'label' => $label,
                        'field_key' => 'formatted_body',
                        'width' => 'full',
                        'is_required' => false,
                        'settings' => [
                            'label_align' => 'top',
                            'pos_x' => 0,
                            'pos_y' => 160,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $element = CompanyDocumentElement::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->where('field_key', 'formatted_body')
            ->firstOrFail();

        $this->assertSame($label, $element->label);
    }
}
