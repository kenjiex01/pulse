<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\CompanyDocumentSubmissionValue;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use App\Support\MemoPdfFilename;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompanyDocumentSendService
{
    public function __construct(
        private readonly CompanyDocumentFormBuilderService $builderService,
        private readonly CompanyDocumentFileService $fileService,
        private readonly CompanyDocumentMemoRenderService $memoRenderService,
        private readonly CompanyDocumentMemoValueResolver $valueResolver,
        private readonly TimekeepingMemoEmailService $emailService,
    ) {}

    /**
     * @return array{submission_id: int}
     */
    public function sendToEmployee(CompanyDocumentForm $form, Employee $employee, User $sender): array
    {
        @set_time_limit(120);

        if (! $form->is_active) {
            throw new RuntimeException('This document template is inactive.');
        }

        $form->load('elements');
        $memoContext = $this->defaultMemoContext();
        $preview = $this->memoRenderService->buildPreviewData($form, $employee, $memoContext);

        $emailAttachments = [[
            'binary' => $this->memoRenderService->renderPdf($preview),
            'filename' => MemoPdfFilename::forStandalone($form, $employee),
            'mime' => 'application/pdf',
        ]];

        if ($form->requires_nte) {
            $emailAttachments = array_merge($emailAttachments, $this->buildNteDocxAttachment($employee, $memoContext));
        }

        $emailTemplates = $this->resolveEmailTemplates($form);

        $result = DB::transaction(function () use ($employee, $form, $sender, $memoContext) {
            $sentAt = now();

            $submission = CompanyDocumentSubmission::query()->create([
                'company_document_form_id' => $form->company_document_form_id,
                'form_version' => $form->version,
                'form_snapshot_json' => $this->builderService->snapshotForm($form),
                'submitted_by_user_id' => $sender->id,
                'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
                'submitted_at' => $sentAt,
                'completed_at' => $sentAt,
            ]);

            foreach ($form->elements as $element) {
                if (! $element->isInput()) {
                    continue;
                }

                $key = (string) $element->field_key;

                if ($element->type === CompanyDocumentElement::TYPE_SIGNATURE) {
                    $row = [
                        'submission_id' => $submission->submission_id,
                        'element_id' => $element->element_id,
                        'field_key' => $key,
                    ];

                    $dataUrl = $this->valueResolver->signatureDataUrl($element);
                    if ($dataUrl !== '') {
                        $row = array_merge($row, $this->fileService->storeSignatureFromDataUrl($dataUrl, $submission->submission_id));
                    }

                    CompanyDocumentSubmissionValue::query()->create($row);

                    continue;
                }

                $value = $this->valueResolver->resolveInputValue($element, $employee, $memoContext);

                CompanyDocumentSubmissionValue::query()->create([
                    'submission_id' => $submission->submission_id,
                    'element_id' => $element->element_id,
                    'field_key' => $key,
                    'value_text' => $value,
                ]);
            }

            CompanyDocumentSendLog::query()->create([
                'company_document_form_id' => $form->company_document_form_id,
                'employee_id' => $employee->employee_id,
                'submission_id' => $submission->submission_id,
                'sent_by_user_id' => $sender->id,
                'sent_at' => $sentAt,
            ]);

            return [
                'submission_id' => (int) $submission->submission_id,
            ];
        });

        $this->emailService->sendWithTemplates(
            $employee,
            $form,
            $memoContext,
            $emailTemplates['subject'],
            $emailTemplates['body'],
            $emailTemplates['cc'],
            $emailAttachments,
        );

        return $result;
    }

    /**
     * @return array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}
     */
    public function defaultMemoContext(): array
    {
        $today = now()->toDateString();

        return [
            'date_from' => $today,
            'date_to' => $today,
            'violation_type' => 'absent',
            'violation_count' => 0,
            'selected_dates' => [],
        ];
    }

    /**
     * @return array{subject: string, body: string, cc: string|null}
     */
    private function resolveEmailTemplates(CompanyDocumentForm $form): array
    {
        $setup = TimekeepingMemoSetup::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->first();

        if ($setup !== null) {
            $subject = trim((string) ($setup->email_subject ?? ''));
            $body = trim((string) ($setup->email_body ?? ''));

            if ($subject !== '' && $body !== '') {
                return [
                    'subject' => $subject,
                    'body' => $body,
                    'cc' => $setup->email_cc,
                ];
            }
        }

        $documentName = trim($form->name) !== '' ? trim($form->name) : trim($form->code);

        return [
            'subject' => $documentName.' — {{employee_full_name}}',
            'body' => "Dear {{employee_full_name}},\n\nPlease find attached your copy of {$documentName}.\n\nThank you.",
            'cc' => null,
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     * @return list<array{binary: string, filename: string, mime: string}>
     */
    private function buildNteDocxAttachment(Employee $employee, array $memoContext): array
    {
        $nteForm = CompanyDocumentForm::activeNteTemplate();

        if ($nteForm === null) {
            throw new RuntimeException('This document requires a Notice to Explain (NTE), but no active NTE template is configured in Company Documents.');
        }

        $nteForm->load('elements');
        $ntePreview = $this->memoRenderService->buildPreviewData($nteForm, $employee, $memoContext);

        return [[
            'binary' => $this->memoRenderService->renderDocx($ntePreview),
            'filename' => MemoPdfFilename::docxForStandalone($nteForm, $employee),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]];
    }
}
