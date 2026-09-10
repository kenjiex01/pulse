<?php

namespace Tests\Feature;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentElement;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use App\Services\TimekeepingMemoAttendanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TimekeepingMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_memo_setup_page_loads(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('timekeeping.memo-setup.index'))
            ->assertOk()
            ->assertSee('Memo Setup')
            ->assertSee('Late memo template');
    }

    public function test_memo_setup_saves_template_mapping(): void
    {
        $user = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->create([
            'code' => 'test_late_memo',
            'name' => 'Late Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        $payload = [
            'late_form_id' => $form->company_document_form_id,
            'undertime_form_id' => null,
            'absent_form_id' => null,
        ];

        foreach (['late', 'undertime', 'absent'] as $type) {
            $payload[$type.'_email_subject'] = ucfirst($type).' memo subject';
            $payload[$type.'_email_body'] = 'Hello {{employee_full_name}}, this is your '.strtolower($type).' memo.';
            $payload[$type.'_email_cc'] = $type === 'late' ? 'hr@example.com' : null;
        }

        $this->actingAs($user)
            ->put(route('timekeeping.memo-setup.update'), $payload)
            ->assertRedirect(route('timekeeping.memo-setup.index'));

        $setup = TimekeepingMemoSetup::query()->where('violation_type', 'late')->firstOrFail();
        $this->assertSame($form->company_document_form_id, $setup->company_document_form_id);
        $this->assertSame('Late memo subject', $setup->email_subject);
        $this->assertStringContainsString('{{employee_full_name}}', (string) $setup->email_body);
        $this->assertSame('hr@example.com', $setup->email_cc);
    }

    public function test_memo_setup_requires_email_subject_and_body(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('timekeeping.memo-setup.index'))
            ->put(route('timekeeping.memo-setup.update'), [
                'late_form_id' => null,
                'undertime_form_id' => null,
                'absent_form_id' => null,
            ])
            ->assertRedirect(route('timekeeping.memo-setup.index'))
            ->assertSessionHasErrors([
                'late_email_subject',
                'late_email_body',
                'undertime_email_subject',
                'undertime_email_body',
                'absent_email_subject',
                'absent_email_body',
            ]);
    }

    public function test_memo_index_requires_filters_before_results(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('timekeeping.memo.index'))
            ->assertOk()
            ->assertSee('Apply Filters');

        $this->actingAs($user)
            ->get(route('timekeeping.memo.index', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'late',
                'min_count' => 1,
            ]))
            ->assertOk()
            ->assertSee('Employee');
    }

    public function test_memo_preview_requires_applied_filters(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('timekeeping.memo.preview', $employee))
            ->assertStatus(422);
    }

    public function test_memo_preview_renders_resolved_template(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'preview_late_memo',
            'name' => 'Preview Late Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee: {{employee_full_name}} · Count: {{violation_count}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 16, 'pos_y' => 16],
        ]);

        TimekeepingMemoSetup::query()->updateOrCreate(
            ['violation_type' => 'late'],
            ['company_document_form_id' => $form->company_document_form_id],
        );

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('late');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-09-05',
                    'time_in' => '09:30',
                    'time_out' => '18:00',
                    'minutes' => 30,
                    'memo_sent' => false,
                ],
            ]);
            $mock->shouldReceive('campusLabel')->with(Mockery::type(Employee::class))->andReturn('Test Campus');
        });

        $this->actingAs($user)
            ->get(route('timekeeping.memo.preview', [
                'employee' => $employee->employee_id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'late',
            ]))
            ->assertOk()
            ->assertSee('Memo Preview')
            ->assertSee('Preview Late Memo')
            ->assertSee($employee->full_name)
            ->assertSee('Legal size (8.5 × 14 in)', false)
            ->assertSee('timekeeping/memo/'.$employee->employee_id.'/preview-html', false);
    }

    public function test_memo_preview_pdf_endpoint_returns_pdf(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'preview_late_memo_pdf',
            'name' => 'Preview Late Memo PDF',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee: {{employee_full_name}} · Count: {{violation_count}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 16, 'pos_y' => 16],
        ]);

        TimekeepingMemoSetup::query()->updateOrCreate(
            ['violation_type' => 'late'],
            ['company_document_form_id' => $form->company_document_form_id],
        );

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('late');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-09-05',
                    'time_in' => '09:30',
                    'time_out' => '18:00',
                    'minutes' => 30,
                    'memo_sent' => false,
                ],
            ]);
            $mock->shouldReceive('campusLabel')->with(Mockery::type(Employee::class))->andReturn('Test Campus');
        });

        $response = $this->actingAs($user)
            ->get(route('timekeeping.memo.preview-pdf', [
                'employee' => $employee->employee_id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'late',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_memo_preview_pdf_matches_send_attachment_pipeline(): void
    {
        $employee = $this->makeEmployee();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'preview_late_memo_pdf_match',
            'name' => 'Preview Late Memo PDF Match',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee: {{employee_full_name}} · Count: {{violation_count}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 16, 'pos_y' => 16],
        ]);

        TimekeepingMemoSetup::query()->updateOrCreate(
            ['violation_type' => 'late'],
            ['company_document_form_id' => $form->company_document_form_id],
        );

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('late');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-09-05',
                    'time_in' => '09:30',
                    'time_out' => '18:00',
                    'minutes' => 30,
                    'memo_sent' => false,
                ],
            ]);
            $mock->shouldReceive('campusLabel')->with(Mockery::type(Employee::class))->andReturn('Test Campus');
        });

        $previewService = app(\App\Services\TimekeepingMemoPreviewService::class);
        $renderService = app(\App\Services\CompanyDocumentMemoRenderService::class);

        $previewPdf = $previewService->renderPdfForEmployee(
            $employee,
            '2026-09-01',
            '2026-09-07',
            'late',
            null,
        );

        $payload = $previewService->buildForEmployee(
            $employee,
            '2026-09-01',
            '2026-09-07',
            'late',
            null,
        );

        $sendPdf = $renderService->renderPdf([
            'form' => $payload['form'],
            'elements' => $payload['elements'],
            'preview_values' => $payload['preview_values'],
        ]);

        $this->assertSame(strlen($previewPdf), strlen($sendPdf));
        $this->assertSame(
            preg_match_all('/\/Type\s*\/Page[^s]/', $previewPdf),
            preg_match_all('/\/Type\s*\/Page[^s]/', $sendPdf),
        );
    }

    public function test_memo_preview_includes_saved_author_signature(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $form = CompanyDocumentForm::query()->create([
            'code' => 'preview_absent_memo_sig',
            'name' => 'Preview Absent Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_SIGNATURE,
            'label' => 'Author Signature',
            'field_key' => 'author_signature',
            'is_required' => true,
            'sort_order' => 1,
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => 16,
                'pos_y' => 16,
                'signature_preview' => [
                    'mode' => 'draw',
                    'dataUrl' => $dataUrl,
                    'fileName' => 'signature.png',
                ],
            ],
        ]);

        TimekeepingMemoSetup::query()->updateOrCreate(
            ['violation_type' => 'absent'],
            ['company_document_form_id' => $form->company_document_form_id],
        );

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('absent');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-09-05',
                    'time_in' => null,
                    'time_out' => null,
                    'minutes' => 0,
                    'memo_sent' => false,
                ],
            ]);
            $mock->shouldReceive('campusLabel')->with(Mockery::type(Employee::class))->andReturn('Test Campus');
        });

        $response = $this->actingAs($user)
            ->get(route('timekeeping.memo.preview', [
                'employee' => $employee->employee_id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'absent',
            ]));

        $response->assertOk()
            ->assertSee('Legal size (8.5 × 14 in)', false)
            ->assertSee('preview-html', false);

        $pdf = $this->actingAs($user)
            ->get(route('timekeeping.memo.preview-pdf', [
                'employee' => $employee->employee_id,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'absent',
            ]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf->getContent()));
    }

    private function makeEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_number' => 'MEMO-PREVIEW-'.uniqid(),
            'first_name' => 'Memo',
            'last_name' => 'Preview',
            'email' => 'memo.preview.'.uniqid().'@example.com',
        ]);
    }
}
