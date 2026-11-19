<?php

namespace Tests\Feature;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentMemoSequence;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\Employee;
use App\Models\TimekeepingMemoSendLog;
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
            ->assertSee('Late memo template')
            ->assertSee('Count')
            ->assertSee('Next control number')
            ->assertSee('name="control_number"', false)
            ->assertSee('name="late_occurrence_count"', false)
            ->assertSee('name="undertime_occurrence_count"', false)
            ->assertSee('name="absent_occurrence_count"', false);
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
            'control_number' => 1,
            'late_form_id' => $form->company_document_form_id,
            'undertime_form_id' => null,
            'absent_form_id' => null,
        ];

        foreach (['late', 'undertime', 'absent'] as $type) {
            $payload[$type.'_occurrence_count'] = $type === 'late' ? 3 : 1;
            $payload[$type.'_email_subject'] = ucfirst($type).' memo subject';
            $payload[$type.'_email_body'] = 'Hello {{employee_full_name}}, this is your '.strtolower($type).' memo.';
            $payload[$type.'_email_cc'] = $type === 'late' ? 'hr@example.com' : null;
        }

        $this->actingAs($user)
            ->put(route('timekeeping.memo-setup.update'), $payload)
            ->assertRedirect(route('timekeeping.memo-setup.index'));

        $setup = TimekeepingMemoSetup::query()->where('violation_type', 'late')->firstOrFail();
        $this->assertSame($form->company_document_form_id, $setup->company_document_form_id);
        $this->assertSame(3, $setup->occurrence_count);
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
                'late_occurrence_count' => 1,
                'undertime_occurrence_count' => 1,
                'absent_occurrence_count' => 1,
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

    public function test_memo_setup_rejects_a_control_number_that_was_already_sent(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();
        $form = CompanyDocumentForm::query()->where('document_type', CompanyDocumentForm::TYPE_MEMO)->firstOrFail();
        $late = TimekeepingMemoSetup::query()->where('violation_type', 'late')->firstOrFail();

        CompanyDocumentMemoSequence::query()->create([
            'year' => (int) now('Asia/Manila')->year,
            'last_number' => 165,
        ]);

        foreach ([160 => '2026-11-04', 165 => '2026-11-05'] as $number => $workDate) {
            TimekeepingMemoSendLog::query()->create([
                'employee_id' => $employee->employee_id,
                'violation_type' => 'late',
                'work_date' => $workDate,
                'company_document_form_id' => $form->company_document_form_id,
                'control_no' => now('Asia/Manila')->year.'-INV/MM-'.$number,
                'sent_at' => now(),
            ]);
        }

        $payload = [
            'control_number' => 160,
            'late_form_id' => $form->company_document_form_id,
            'undertime_form_id' => null,
            'absent_form_id' => null,
        ];

        foreach (['late', 'undertime', 'absent'] as $type) {
            $payload[$type.'_occurrence_count'] = 9;
            $payload[$type.'_email_subject'] = 'Should not save';
            $payload[$type.'_email_body'] = 'Should not save';
        }

        $this->actingAs($user)
            ->from(route('timekeeping.memo-setup.index'))
            ->put(route('timekeeping.memo-setup.update'), $payload)
            ->assertRedirect(route('timekeeping.memo-setup.index'))
            ->assertSessionHasErrors('control_number')
            ->assertSessionHas('error', 'Control number '.now('Asia/Manila')->year.'-INV/MM-160 was already sent. The last control number is '.now('Asia/Manila')->year.'-INV/MM-165.');

        $this->assertSame($late->occurrence_count, $late->fresh()->occurrence_count);
        $this->assertSame(165, CompanyDocumentMemoSequence::query()->where('year', (int) now('Asia/Manila')->year)->value('last_number'));

        $payload['control_number'] = 166;
        foreach (['late', 'undertime', 'absent'] as $type) {
            $payload[$type.'_occurrence_count'] = $type === 'late' ? 4 : 1;
            $payload[$type.'_email_subject'] = ucfirst($type).' kept';
            $payload[$type.'_email_body'] = 'Kept body';
        }

        $this->actingAs($user)
            ->put(route('timekeeping.memo-setup.update'), $payload)
            ->assertRedirect(route('timekeeping.memo-setup.index'))
            ->assertSessionHas('success');

        $this->assertSame(4, $late->fresh()->occurrence_count);
        $this->assertSame(165, CompanyDocumentMemoSequence::query()->where('year', (int) now('Asia/Manila')->year)->value('last_number'));
    }

    public function test_sent_memos_tab_lists_each_send_and_previews_the_sent_document(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();
        $form = CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->firstOrFail();
        $submission = CompanyDocumentSubmission::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'form_version' => $form->version,
            'submitted_by_user_id' => $user->id,
            'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);

        foreach (['2026-11-04', '2026-11-05'] as $workDate) {
            TimekeepingMemoSendLog::query()->create([
                'employee_id' => $employee->employee_id,
                'violation_type' => 'late',
                'work_date' => $workDate,
                'company_document_form_id' => $form->company_document_form_id,
                'submission_id' => $submission->submission_id,
                'control_no' => '2026-INV/MM-106',
                'sent_by_user_id' => $user->id,
                'sent_at' => '2026-11-19 09:00:00',
            ]);
        }

        $otherSubmission = CompanyDocumentSubmission::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'form_version' => $form->version,
            'submitted_by_user_id' => $user->id,
            'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);
        $otherEmployee = $this->makeEmployee();
        CompanyDocumentSendLog::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $otherEmployee->employee_id,
            'submission_id' => $otherSubmission->submission_id,
            'control_no' => '2026-INV/MM-050',
            'sent_by_user_id' => $user->id,
            'sent_at' => '2026-11-18 09:00:00',
        ]);

        $page = $this->actingAs($user)
            ->get(route('timekeeping.memo.index', ['tab' => 'sent']))
            ->assertOk()
            ->assertSee('Sent memos')
            ->assertSee('All memo types')
            ->assertSee('id="sent-memo-search"', false)
            ->assertSee('data-sent-memo-type', false)
            ->assertSee('data-sent-control-number', false)
            ->assertSee($employee->full_name)
            ->assertSee($otherEmployee->full_name)
            ->assertSee('2026-INV/MM-106')
            ->assertSee('2026-INV/MM-050')
            ->assertSee($form->name)
            ->getContent();

        $this->assertSame(1, substr_count($page, 'data-memo-control="2026-inv/mm-106"'));

        $this->actingAs($user)
            ->get(route('timekeeping.memo.sent-preview-html', $submission))
            ->assertOk()
            ->assertSee('class="letterhead"', false)
            ->assertSee('2026-INV/MM-106')
            ->assertSee('November 19, 2026');
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
            ->assertSee('Employee')
            ->assertSee('data-client-table-search', false)
            ->assertDontSee('data-live-table-search', false);
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

    public function test_memo_preview_html_uses_the_company_document_letterhead(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();
        $form = CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->firstOrFail();

        TimekeepingMemoSetup::query()->updateOrCreate(
            ['violation_type' => 'late'],
            ['company_document_form_id' => $form->company_document_form_id],
        );

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('late');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-11-04',
                    'time_in' => '09:30',
                    'time_out' => '18:00',
                    'minutes' => 30,
                    'memo_sent' => false,
                ],
            ]);
        });

        $sample = app(\App\Services\CompanyDocumentMemoRenderService::class)
            ->renderDocumentHtml(
                app(\App\Services\CompanyDocumentMemoRenderService::class)->buildSamplePreviewData($form),
                true,
            );

        $memoHtml = $this->actingAs($user)
            ->get(route('timekeeping.memo.preview-html', [
                'employee' => $employee->employee_id,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-19',
                'violation_type' => 'late',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="letterhead"', $sample);
        $this->assertStringContainsString('class="letterhead"', $memoHtml);
        $this->assertStringContainsString('M E M O R A N D U M', $memoHtml);
        $this->assertStringContainsString('ICCT COLLEGES FOUNDATION, INC.', $memoHtml);
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

    public function test_memo_list_minimum_count_cannot_go_below_setup(): void
    {
        TimekeepingMemoSetup::query()->where('violation_type', 'late')->update([
            'occurrence_count' => 4,
        ]);

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('timekeeping.memo.index', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'late',
                'min_count' => 1,
            ]))
            ->assertOk()
            ->assertSee('value="4"', false);
    }

    public function test_memo_send_is_blocked_until_the_setup_count_is_reached(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->makeEmployee();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'count_gate_late_memo',
            'name' => 'Count Gate Late Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        TimekeepingMemoSetup::query()->where('violation_type', 'late')->update([
            'company_document_form_id' => $form->company_document_form_id,
            'occurrence_count' => 3,
            'email_subject' => 'Late memo',
            'email_body' => 'Body',
        ]);

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
        });

        $this->actingAs($user)
            ->from(route('timekeeping.memo.index'))
            ->post(route('timekeeping.memo.send', $employee), [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-07',
                'violation_type' => 'late',
            ])
            ->assertRedirect(route('timekeeping.memo.index'))
            ->assertSessionHas('error', 'A late memo is sent after 3 occurrence(s). This employee has 1 in the selected period.');
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
