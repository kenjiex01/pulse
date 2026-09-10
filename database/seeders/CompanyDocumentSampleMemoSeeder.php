<?php

namespace Database\Seeders;

use App\Models\CompanyDocumentApproval;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter HR memo template for Company Documents. Idempotent by form code.
 */
class CompanyDocumentSampleMemoSeeder extends Seeder
{
    public function run(): void
    {
        $form = CompanyDocumentForm::query()->updateOrCreate(
            ['code' => 'hr_internal_memo'],
            [
                'name' => 'Internal Memo (HR-MEMO-1)',
                'description' => 'Standard company memo for HR announcements, policy updates, and internal communications.',
                'document_type' => CompanyDocumentForm::TYPE_MEMO,
                'allow_multiple_submissions' => true,
                'is_active' => true,
                'submit_label' => 'Submit Memo',
                'success_message' => 'Your memo has been submitted successfully.',
                'version' => 1,
                'sort_order' => 1,
            ],
        );

        $this->clearApprovals($form);

        if ($form->elements()->exists()) {
            return;
        }

        $elements = [
            ['type' => 'heading', 'label' => 'Internal Memo', 'sort_order' => 0],
            ['type' => 'short_text', 'label' => 'Memo To', 'field_key' => 'memo_to', 'is_required' => true, 'sort_order' => 1],
            ['type' => 'short_text', 'label' => 'Memo From', 'field_key' => 'memo_from', 'is_required' => true, 'sort_order' => 2],
            ['type' => 'date', 'label' => 'Date', 'field_key' => 'memo_date', 'is_required' => true, 'sort_order' => 3],
            ['type' => 'short_text', 'label' => 'Subject', 'field_key' => 'subject', 'is_required' => true, 'sort_order' => 4],
            ['type' => 'long_text', 'label' => 'Message Body', 'field_key' => 'message_body', 'is_required' => true, 'sort_order' => 5],
            ['type' => 'signature', 'label' => 'Author Signature', 'field_key' => 'author_signature', 'is_required' => true, 'sort_order' => 6],
        ];

        foreach ($elements as $element) {
            CompanyDocumentElement::query()->create(array_merge($element, [
                'company_document_form_id' => $form->company_document_form_id,
            ]));
        }
    }

    private function clearApprovals(CompanyDocumentForm $form): void
    {
        if ($form->document_type !== CompanyDocumentForm::TYPE_MEMO) {
            return;
        }

        CompanyDocumentApproval::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->each(function (CompanyDocumentApproval $approval): void {
                $approval->assignees()->forceDelete();
                $approval->forceDelete();
            });
    }
}
