<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocumentSubmission;
use App\Models\CompanyDocumentSubmissionApproval;
use App\Services\CompanyDocumentFileService;
use App\Services\CompanyDocumentFormBuilderService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyDocumentApprovalController extends Controller
{
    public function __construct(
        private readonly CompanyDocumentFormBuilderService $builderService,
        private readonly CompanyDocumentFileService $fileService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('approve', \App\Models\CompanyDocumentForm::class);

        $pending = $this->builderService->pendingApprovalsForUser((int) $request->user()->id);

        SysLogService::record(
            action: 'read',
            table: 'tbl_company_document_submission_approvals',
            description: 'Viewed company document approval queue ('.$pending->count().' pending)',
        );

        return view('company-documents.approvals.index', [
            'pending' => $pending,
        ]);
    }

    public function act(Request $request, CompanyDocumentSubmission $submission): RedirectResponse
    {
        $this->authorize('approve', \App\Models\CompanyDocumentForm::class);

        $validated = $request->validate([
            'submission_approval_id' => ['required', 'integer', 'exists:tbl_company_document_submission_approvals,submission_approval_id'],
            'action' => ['required', 'string', 'in:approve,reject'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $approvalRow = CompanyDocumentSubmissionApproval::query()
            ->where('submission_approval_id', $validated['submission_approval_id'])
            ->where('submission_id', $submission->submission_id)
            ->where('assignee_user_id', $request->user()->id)
            ->where('status', CompanyDocumentSubmissionApproval::STATUS_PENDING)
            ->firstOrFail();

        $signaturePath = null;
        if ($request->hasFile('signature')) {
            $signaturePath = $this->fileService->storeApproverSignature(
                $request->file('signature'),
                $submission->submission_id,
            );
        }

        $approvalRow->update([
            'status' => $validated['action'] === 'approve'
                ? CompanyDocumentSubmissionApproval::STATUS_APPROVED
                : CompanyDocumentSubmissionApproval::STATUS_REJECTED,
            'comment' => $validated['comment'] ?? null,
            'signature_path' => $signaturePath,
            'acted_at' => now(),
        ]);

        $submission->refresh();
        $this->builderService->advanceAfterApproval($submission);

        SysLogService::record(
            action: 'update',
            table: 'tbl_company_document_submissions',
            recordId: $submission->submission_id,
            description: ucfirst($validated['action']).' company document submission #'.$submission->submission_id,
        );

        return redirect()
            ->route('company-documents.approvals.index')
            ->with('success', $validated['action'] === 'approve' ? 'Submission approved.' : 'Submission rejected.');
    }
}
