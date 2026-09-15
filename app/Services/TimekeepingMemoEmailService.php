<?php

namespace App\Services;

use App\Mail\TimekeepingMemoMail;
use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TimekeepingMemoEmailService
{
    public function __construct(
        private readonly CompanyDocumentMergeTagService $mergeTagService,
    ) {}

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     * @param  list<array{binary: string, filename: string, mime?: string}>  $attachments
     */
    public function sendForEmployee(
        Employee $employee,
        TimekeepingMemoSetup $setup,
        array $memoContext,
        CompanyDocumentForm $form,
        array $attachments,
    ): void {
        $this->ensureMailIsConfigured();

        if ($attachments === []) {
            throw new RuntimeException('No memo email attachments were generated.');
        }

        $subjectTemplate = trim((string) ($setup->email_subject ?? ''));
        $bodyTemplate = trim((string) ($setup->email_body ?? ''));

        if ($subjectTemplate === '' || $bodyTemplate === '') {
            throw new RuntimeException(
                'Email subject and body must be configured in Memo Setup for '
                .TimekeepingMemoSetup::labelForType((string) $setup->violation_type).'.',
            );
        }

        $email = trim((string) ($employee->email ?? ''));
        if ($email === '') {
            throw new RuntimeException('Employee has no email address on file.');
        }

        $subject = $this->mergeTagService->resolveInlineTags($subjectTemplate, $employee, $memoContext);
        $body = $this->mergeTagService->resolveInlineTags($bodyTemplate, $employee, $memoContext);
        $cc = TimekeepingMemoSetup::parseCcList($setup->email_cc);

        Mail::to($email)->send(new TimekeepingMemoMail(
            $subject,
            $body,
            (string) $form->name,
            $attachments,
            $cc,
        ));
    }

    private function ensureMailIsConfigured(): void
    {
        $mailer = config('mail.default');

        if ($mailer === 'ses') {
            if (filled(config('services.ses.key')) && filled(config('services.ses.secret'))) {
                return;
            }

            throw ValidationException::withMessages([
                'mail' => 'Email is not configured. Set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in pulse/.env (or leave empty to reuse DB_BACKUP_S3_* keys), then restart the app.',
            ]);
        }

        if ($mailer !== 'smtp') {
            return;
        }

        if (filled(config('mail.mailers.smtp.password')) && filled(config('mail.mailers.smtp.username'))) {
            return;
        }

        throw ValidationException::withMessages([
            'mail' => 'Email is not configured. Set MAIL_USERNAME and MAIL_PASSWORD (Google App Password) in pulse/.env, then restart the app.',
        ]);
    }
}
