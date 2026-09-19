<?php

namespace Tests\Unit;

use App\Mail\TimekeepingMemoMail;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\Employee;
use App\Models\User;
use App\Services\CompanyDocumentSendService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CompanyDocumentSendServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config(['mail.default' => 'array']);
    }

    public function test_send_creates_submission_and_emails_pdf_to_employee(): void
    {
        Mail::fake();

        $employee = Employee::query()->create([
            'employee_number' => 'DOC-SEND-001',
            'first_name' => 'Elena',
            'last_name' => 'Garcia',
            'email' => 'elena.garcia@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'return_to_work_send',
            'name' => 'Return to Work Form',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_SHORT_TEXT,
            'label' => 'Remarks',
            'field_key' => 'remarks',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'default_text' => 'Dear {{employee_full_name}}'],
        ]);

        $result = app(CompanyDocumentSendService::class)->sendToEmployee(
            $form,
            $employee,
            User::query()->firstOrFail(),
        );

        $this->assertArrayHasKey('submission_id', $result);
        $this->assertDatabaseHas('tbl_company_document_submissions', [
            'submission_id' => $result['submission_id'],
            'company_document_form_id' => $form->company_document_form_id,
            'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('tbl_company_document_send_logs', [
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'submission_id' => $result['submission_id'],
        ]);

        Mail::assertSent(TimekeepingMemoMail::class, function (TimekeepingMemoMail $mail) use ($employee): bool {
            $envelope = $mail->envelope();

            return str_contains($envelope->subject, $employee->full_name)
                && count($mail->attachments()) >= 1;
        });
    }

    public function test_send_fails_when_template_is_inactive(): void
    {
        Mail::fake();

        $employee = Employee::query()->create([
            'employee_number' => 'DOC-SEND-002',
            'first_name' => 'Felix',
            'last_name' => 'Ramos',
            'email' => 'felix.ramos@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'inactive_doc',
            'name' => 'Inactive Form',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => false,
            'version' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('inactive');

        app(CompanyDocumentSendService::class)->sendToEmployee(
            $form,
            $employee,
            User::query()->firstOrFail(),
        );
    }
}
