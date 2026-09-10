<?php

namespace App\Services;

use App\Models\CompanyDocumentApproval;
use App\Models\CompanyDocumentApprovalAssignee;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSubmission;
use App\Models\CompanyDocumentSubmissionApproval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyDocumentFormBuilderService
{
    public function snapshotForm(CompanyDocumentForm $form): array
    {
        $form->loadMissing(['elements', 'approvals.assignees']);

        return [
            'company_document_form_id' => $form->company_document_form_id,
            'code' => $form->code,
            'name' => $form->name,
            'description' => $form->description,
            'document_type' => $form->document_type,
            'version' => $form->version,
            'submit_label' => $form->submit_label,
            'success_message' => $form->success_message,
            'settings' => $form->settings_json ?? [],
            'elements' => $form->elements->map(fn ($element) => [
                'element_id' => $element->element_id,
                'type' => $element->type,
                'label' => $element->label,
                'field_key' => $element->field_key,
                'placeholder' => $element->placeholder,
                'help_text' => $element->help_text,
                'is_required' => (bool) $element->is_required,
                'options' => $element->options_json ?? [],
                'validation' => $element->validation_json ?? [],
                'conditional' => $element->conditional_json ?? [],
                'settings' => $element->settings_json ?? [],
                'width' => $element->width,
                'sort_order' => $element->sort_order,
            ])->values()->all(),
            'approvals' => $form->approvals->map(fn ($approval) => [
                'approval_id' => $approval->approval_id,
                'step_number' => $approval->step_number,
                'name' => $approval->name,
                'mode' => $approval->mode,
                'optional' => (bool) $approval->optional,
                'sla_hours' => $approval->sla_hours,
                'instructions' => $approval->instructions,
                'assignees' => $approval->assignees->map(fn ($assignee) => [
                    'assignee_type' => $assignee->assignee_type,
                    'user_id' => $assignee->user_id,
                    'role_id' => $assignee->role_id,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{approval: CompanyDocumentApproval, user_ids: array<int>}>
     */
    public function resolveAssignees(CompanyDocumentForm $form, CompanyDocumentSubmission $submission): array
    {
        $form->loadMissing('approvals.assignees');
        $steps = [];

        foreach ($form->approvals as $approval) {
            $userIds = collect();
            foreach ($approval->assignees as $assignee) {
                $userIds = $userIds->merge($this->resolveAssignee($assignee));
            }

            $steps[] = [
                'approval' => $approval,
                'user_ids' => $userIds->filter()->unique()->values()->all(),
            ];
        }

        return $steps;
    }

    /**
     * @return array<int>
     */
    private function resolveAssignee(CompanyDocumentApprovalAssignee $assignee): array
    {
        if ($assignee->assignee_type === CompanyDocumentApprovalAssignee::TYPE_USER && $assignee->user_id) {
            return [(int) $assignee->user_id];
        }

        if ($assignee->assignee_type === CompanyDocumentApprovalAssignee::TYPE_ROLE && $assignee->role_id) {
            return DB::table('tbl_user_roles')
                ->where('role_id', $assignee->role_id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    public function seedSubmissionApprovals(CompanyDocumentSubmission $submission, CompanyDocumentForm $form): void
    {
        foreach ($this->resolveAssignees($form, $submission) as $step) {
            /** @var CompanyDocumentApproval $approval */
            $approval = $step['approval'];

            foreach ($step['user_ids'] as $userId) {
                CompanyDocumentSubmissionApproval::query()->create([
                    'submission_id' => $submission->submission_id,
                    'approval_id' => $approval->approval_id,
                    'step_number' => $approval->step_number,
                    'step_name' => $approval->name,
                    'mode' => $approval->mode,
                    'optional' => (bool) $approval->optional,
                    'assignee_user_id' => $userId,
                    'status' => CompanyDocumentSubmissionApproval::STATUS_PENDING,
                ]);
            }
        }

        $hasApprovals = CompanyDocumentSubmissionApproval::query()
            ->where('submission_id', $submission->submission_id)
            ->exists();

        if ($hasApprovals) {
            $submission->update([
                'status' => CompanyDocumentSubmission::STATUS_IN_APPROVAL,
                'current_step' => 1,
            ]);
        } else {
            $submission->update([
                'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    public function advanceAfterApproval(CompanyDocumentSubmission $submission): void
    {
        $submission->loadMissing('submissionApprovals');

        $currentStep = (int) $submission->current_step;
        if ($currentStep <= 0) {
            return;
        }

        $stepRows = $submission->submissionApprovals
            ->where('step_number', $currentStep);

        if ($stepRows->isEmpty()) {
            return;
        }

        $mode = (string) $stepRows->first()->mode;
        $optional = (bool) $stepRows->first()->optional;

        if ($stepRows->contains('status', CompanyDocumentSubmissionApproval::STATUS_REJECTED)) {
            $submission->update([
                'status' => CompanyDocumentSubmission::STATUS_REJECTED,
                'completed_at' => now(),
            ]);

            return;
        }

        $approved = $stepRows->where('status', CompanyDocumentSubmissionApproval::STATUS_APPROVED);
        $pending = $stepRows->where('status', CompanyDocumentSubmissionApproval::STATUS_PENDING);

        $stepComplete = match ($mode) {
            CompanyDocumentApproval::MODE_ALL_OF => $pending->isEmpty() && $approved->isNotEmpty(),
            CompanyDocumentApproval::MODE_ANY_OF => $approved->isNotEmpty(),
            default => $approved->isNotEmpty(),
        };

        if (! $stepComplete && ! $optional) {
            return;
        }

        if (! $stepComplete && $optional) {
            $stepRows->each(function (CompanyDocumentSubmissionApproval $row): void {
                if ($row->status === CompanyDocumentSubmissionApproval::STATUS_PENDING) {
                    $row->update([
                        'status' => CompanyDocumentSubmissionApproval::STATUS_SKIPPED,
                        'acted_at' => now(),
                    ]);
                }
            });
        }

        $nextStep = $currentStep + 1;
        $hasNext = $submission->submissionApprovals->contains('step_number', $nextStep);

        if ($hasNext) {
            $submission->update([
                'status' => CompanyDocumentSubmission::STATUS_IN_APPROVAL,
                'current_step' => $nextStep,
            ]);

            return;
        }

        $submission->update([
            'status' => CompanyDocumentSubmission::STATUS_APPROVED,
            'current_step' => 0,
            'completed_at' => now(),
        ]);
    }

    public function pendingApprovalsForUser(int $userId): Collection
    {
        return CompanyDocumentSubmissionApproval::query()
            ->with(['submission.form', 'submission.submitter'])
            ->where('assignee_user_id', $userId)
            ->where('status', CompanyDocumentSubmissionApproval::STATUS_PENDING)
            ->whereHas('submission', function ($query) {
                $query->where('status', CompanyDocumentSubmission::STATUS_IN_APPROVAL);
            })
            ->orderByDesc('created_at')
            ->get();
    }
}
