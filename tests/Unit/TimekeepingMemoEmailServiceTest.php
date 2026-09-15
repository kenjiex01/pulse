<?php

namespace Tests\Unit;

use App\Mail\TimekeepingMemoMail;
use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use App\Services\TimekeepingMemoEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TimekeepingMemoEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_resolves_merge_tags_and_cc(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);

        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-EMAIL-001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@example.com',
        ]);

        $setup = TimekeepingMemoSetup::query()->where('violation_type', 'late')->firstOrFail();
        $setup->update([
            'email_subject' => 'Late memo for {{employee_full_name}}',
            'email_body' => "Dear {{employee_full_name}},\nDates: {{late_dates}}",
            'email_cc' => 'hr@example.com, supervisor@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'late_memo_email',
            'name' => 'Late Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        app(TimekeepingMemoEmailService::class)->sendForEmployee($employee, $setup->fresh(), [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-07',
            'violation_type' => 'late',
            'violation_count' => 2,
            'selected_dates' => ['2026-09-05', '2026-09-06'],
        ], $form, [
            ['binary' => '%PDF-1.4 test', 'filename' => 'late-memo.pdf', 'mime' => 'application/pdf'],
        ]);

        Mail::assertSent(TimekeepingMemoMail::class, 1);

        /** @var TimekeepingMemoMail $sent */
        $sent = Mail::sent(TimekeepingMemoMail::class)[0];
        $envelope = $sent->envelope();
        $ccEmails = collect($envelope->cc)->map(static fn ($address) => $address->address)->all();
        $attachments = $sent->attachments();

        $this->assertSame('Late memo for '.$employee->full_name, $envelope->subject);
        $this->assertContains('hr@example.com', $ccEmails);
        $this->assertContains('supervisor@example.com', $ccEmails);
        $this->assertCount(1, $attachments);
        $this->assertSame('late-memo.pdf', $attachments[0]->as);
    }

    public function test_send_includes_memo_pdf_and_nte_docx_when_two_attachments_provided(): void
    {
        Mail::fake();
        config(['mail.default' => 'array']);

        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-EMAIL-002',
            'first_name' => 'Ben',
            'last_name' => 'Santos',
            'email' => 'ben.santos@example.com',
        ]);

        $setup = TimekeepingMemoSetup::query()->where('violation_type', 'absent')->firstOrFail();
        $setup->update([
            'email_subject' => 'Absent memo for {{employee_full_name}}',
            'email_body' => 'Please review the attached documents.',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'absent_memo_email',
            'name' => 'Absent Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'requires_nte' => true,
            'is_active' => true,
            'version' => 1,
        ]);

        app(TimekeepingMemoEmailService::class)->sendForEmployee($employee, $setup->fresh(), [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-07',
            'violation_type' => 'absent',
            'violation_count' => 1,
            'selected_dates' => ['2026-09-05'],
        ], $form, [
            ['binary' => '%PDF-1.4 memo', 'filename' => 'absent-memo.pdf', 'mime' => 'application/pdf'],
            [
                'binary' => 'PK'.str_repeat("\0", 10),
                'filename' => 'notice-to-explain.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ]);

        Mail::assertSent(TimekeepingMemoMail::class, 1);

        /** @var TimekeepingMemoMail $sent */
        $sent = Mail::sent(TimekeepingMemoMail::class)[0];
        $attachments = $sent->attachments();

        $this->assertCount(2, $attachments);
        $this->assertSame('absent-memo.pdf', $attachments[0]->as);
        $this->assertSame('notice-to-explain.docx', $attachments[1]->as);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $attachments[1]->mime,
        );
    }
}
