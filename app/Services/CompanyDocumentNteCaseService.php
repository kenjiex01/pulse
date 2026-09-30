<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentNteCase;
use App\Models\CompanyDocumentSendLog;
use App\Models\User;
use Carbon\CarbonInterface;
class CompanyDocumentNteCaseService
{
    public function openCaseForSendLog(CompanyDocumentSendLog $sendLog, CompanyDocumentForm $form): ?CompanyDocumentNteCase
    {
        if (! $form->expectsWebNteResponse()) {
            return null;
        }

        $sentAt = $sendLog->sent_at ?? now();
        $dueAt = $sentAt->copy()->addDays($form->nteResponseDays())->endOfDay();

        return CompanyDocumentNteCase::query()->create([
            'company_document_send_log_id' => $sendLog->company_document_send_log_id,
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $sendLog->employee_id,
            'sent_at' => $sentAt,
            'due_at' => $dueAt,
            'status' => CompanyDocumentNteCase::STATUS_PENDING,
        ]);
    }

    public function markOverduePendingCases(): int
    {
        return CompanyDocumentNteCase::query()
            ->where('status', CompanyDocumentNteCase::STATUS_PENDING)
            ->where('due_at', '<', now())
            ->update(['status' => CompanyDocumentNteCase::STATUS_OVERDUE]);
    }

    public function markReceivedManually(CompanyDocumentNteCase $case, User $user): CompanyDocumentNteCase
    {
        if (! $case->isOpen()) {
            throw new \RuntimeException('This NTE case is already closed.');
        }

        $case->update([
            'status' => CompanyDocumentNteCase::STATUS_RECEIVED,
            'responded_at' => now(),
            'response_snapshot_json' => [
                'source' => 'manual',
                'marked_by_user_id' => $user->id,
                'marked_at' => now()->toIso8601String(),
            ],
        ]);

        return $case->fresh();
    }

    /**
     * @return array{subject: string, body: string, cc: string|null}
     */
    public function appendWebNteInstructionsToEmail(
        array $templates,
        CompanyDocumentForm $form,
        CarbonInterface $sentAt,
    ): array {
        if (! $form->expectsWebNteResponse()) {
            return $templates;
        }

        $dueDate = $sentAt->copy()->addDays($form->nteResponseDays())->format('F j, Y');
        $portalUrl = trim((string) config('skolaris.employee_portal_url'));

        $extra = "\n\nPlease submit your written explanation (Notice to Explain) on Skolaris"
            .($portalUrl !== '' ? ' ('.$portalUrl.')' : '')
            .' on or before '.$dueDate.'.';

        return [
            'subject' => $templates['subject'],
            'body' => rtrim($templates['body']).$extra,
            'cc' => $templates['cc'] ?? null,
        ];
    }
}
