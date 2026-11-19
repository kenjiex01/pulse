<?php

namespace Tests\Unit;

use App\Mail\TimekeepingMemoMail;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentMemoSequence;
use App\Models\CompanyDocumentNteCase;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\Employee;
use App\Models\User;
use App\Services\CompanyDocumentMemoControlNumberService;
use App\Services\CompanyDocumentMemoRenderService;
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

    public function test_send_with_web_nte_creates_case_and_single_pdf_attachment(): void
    {
        Mail::fake();

        $employee = Employee::query()->create([
            'employee_number' => 'DOC-NTE-001',
            'first_name' => 'Nina',
            'last_name' => 'Torres',
            'email' => 'nina.torres@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'nte_letter_send',
            'name' => 'Notice to Explain',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_nte' => true,
            'expects_web_nte_response' => true,
            'nte_response_days' => 5,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Dear {{employee_full_name}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 16, 'pos_y' => 16],
        ]);

        app(CompanyDocumentSendService::class)->sendToEmployee(
            $form,
            $employee,
            User::query()->firstOrFail(),
        );

        $this->assertDatabaseHas('tbl_company_document_nte_cases', [
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'status' => CompanyDocumentNteCase::STATUS_PENDING,
        ]);

        Mail::assertSent(TimekeepingMemoMail::class, function (TimekeepingMemoMail $mail): bool {
            return count($mail->attachments()) === 1;
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

    public function test_each_sent_memo_gets_the_next_control_number(): void
    {
        Mail::fake();

        $sender = User::query()->firstOrFail();
        $form = CompanyDocumentForm::query()->create([
            'code' => 'control_no_memo',
            'name' => 'Control Number Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);
        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Control No. {{control_no}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top'],
        ]);

        $first = Employee::query()->create([
            'employee_number' => 'DOC-CTRL-001',
            'first_name' => 'Lara',
            'last_name' => 'Santos',
            'email' => 'lara.santos@example.com',
        ]);
        $second = Employee::query()->create([
            'employee_number' => 'DOC-CTRL-002',
            'first_name' => 'Mark',
            'last_name' => 'Reyes',
            'email' => 'mark.reyes@example.com',
        ]);

        $service = app(CompanyDocumentSendService::class);
        $year = now('Asia/Manila')->year;

        $service->sendToEmployee($form, $first, $sender);
        $service->sendToEmployee($form, $second, $sender);

        $this->assertDatabaseHas('tbl_company_document_send_logs', [
            'employee_id' => $first->employee_id,
            'control_no' => $year.'-INV/MM-001',
        ]);
        $this->assertDatabaseHas('tbl_company_document_send_logs', [
            'employee_id' => $second->employee_id,
            'control_no' => $year.'-INV/MM-002',
        ]);

        $copyContext = $service->memoContextForEmployeeCopy($form, $first);
        $html = app(CompanyDocumentMemoRenderService::class)->renderHtml(
            app(CompanyDocumentMemoRenderService::class)->buildPreviewData($form, $first, $copyContext),
        );

        $this->assertStringContainsString('Control No. '.$year.'-INV/MM-001', $html);

        $service->sendCopiesToAdditionalEmails($form, [$first->employee_id], ['hr.copy@example.com'], $sender);

        $this->assertSame(2, (int) CompanyDocumentMemoSequence::query()->where('year', $year)->value('last_number'));
    }

    public function test_one_company_document_send_shares_a_control_number_and_the_setup_moves_forward(): void
    {
        Mail::fake();

        $sender = User::query()->firstOrFail();
        $numbers = app(CompanyDocumentMemoControlNumberService::class);
        $numbers->assignNext(106);
        $year = now('Asia/Manila')->year;
        $form = CompanyDocumentForm::query()->create([
            'code' => 'shared_control_memo',
            'name' => 'Shared Control Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);
        $employees = collect([
            ['DOC-SHARE-001', 'Ana', 'Cruz', 'ana.cruz@example.com'],
            ['DOC-SHARE-002', 'Ben', 'Diaz', 'ben.diaz@example.com'],
            ['DOC-SHARE-003', 'Cora', 'Flores', 'cora.flores@example.com'],
        ])->map(fn (array $row) => Employee::query()->create([
            'employee_number' => $row[0],
            'first_name' => $row[1],
            'last_name' => $row[2],
            'email' => $row[3],
        ]));

        $service = app(CompanyDocumentSendService::class);
        $batchKey = '11111111-1111-4111-8111-111111111111';
        $shared = $year.'-INV/MM-106';

        foreach ($employees as $employee) {
            $service->sendToEmployee(
                $form,
                $employee,
                $sender,
                $service->controlNumberForBatch((int) $sender->id, (int) $form->company_document_form_id, $batchKey),
            );
        }

        foreach ($employees as $employee) {
            $this->assertDatabaseHas('tbl_company_document_send_logs', [
                'employee_id' => $employee->employee_id,
                'control_no' => $shared,
            ]);
        }

        $this->assertSame(107, $numbers->status()['next_number']);

        foreach (['DOC-MEMO-001', 'DOC-MEMO-002', 'DOC-MEMO-003'] as $index => $number) {
            $employee = Employee::query()->create([
                'employee_number' => $number,
                'first_name' => 'Memo',
                'last_name' => 'Employee '.$index,
                'email' => 'memo'.$index.'@example.com',
            ]);
            $service->sendToEmployee($form, $employee, $sender);
        }

        $this->assertSame(110, $numbers->status()['next_number']);
        $this->assertSame($year.'-INV/MM-107', CompanyDocumentSendLog::query()->where('employee_id', Employee::query()->where('employee_number', 'DOC-MEMO-001')->value('employee_id'))->value('control_no'));
        $this->assertSame($year.'-INV/MM-109', CompanyDocumentSendLog::query()->where('employee_id', Employee::query()->where('employee_number', 'DOC-MEMO-003')->value('employee_id'))->value('control_no'));
    }
}
