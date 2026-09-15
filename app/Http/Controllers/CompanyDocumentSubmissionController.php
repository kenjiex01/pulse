<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSubmission;
use App\Models\CompanyDocumentSubmissionValue;
use App\Services\CompanyDocumentFileService;
use App\Services\CompanyDocumentFormBuilderService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyDocumentSubmissionController extends Controller
{
    public function __construct(
        private readonly CompanyDocumentFormBuilderService $builderService,
        private readonly CompanyDocumentFileService $fileService,
    ) {}

    public function index(CompanyDocumentForm $companyDocumentForm): View
    {
        $this->authorize('view', $companyDocumentForm);

        $submissions = CompanyDocumentSubmission::query()
            ->with('submitter:id,name,email')
            ->where('company_document_form_id', $companyDocumentForm->company_document_form_id)
            ->orderByDesc('submitted_at')
            ->paginate(25);

        SysLogService::record(
            action: 'read',
            table: 'tbl_company_document_submissions',
            description: 'Viewed submissions for template: '.$companyDocumentForm->code,
        );

        return view('company-documents.submissions.index', [
            'form' => $companyDocumentForm,
            'submissions' => $submissions,
        ]);
    }

    public function create(CompanyDocumentForm $companyDocumentForm): View|RedirectResponse
    {
        $this->authorize('view', $companyDocumentForm);

        if (! $companyDocumentForm->is_active) {
            return redirect()
                ->route('company-documents.index')
                ->with('error', 'This template is inactive.');
        }

        $companyDocumentForm->load(['elements', 'icctOffense']);

        return view('company-documents.submissions.create', [
            'form' => $companyDocumentForm,
        ]);
    }

    public function store(Request $request, CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('view', $companyDocumentForm);

        if (! $companyDocumentForm->is_active) {
            return redirect()
                ->route('company-documents.index')
                ->with('error', 'This template is inactive.');
        }

        $companyDocumentForm->load('elements');

        $values = $request->input('values', []);
        if (! is_array($values)) {
            $values = [];
        }

        $errors = [];
        foreach ($companyDocumentForm->elements as $element) {
            if (! $element->isInput()) {
                continue;
            }

            $key = (string) $element->field_key;
            $isFile = in_array($element->type, CompanyDocumentElement::fileBasedTypes(), true);
            $empty = $isFile
                ? ! $request->hasFile("files.$key")
                : blank($values[$key] ?? null);

            if ($element->is_required && $empty) {
                $errors[$key] = 'This field is required.';
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $submission = DB::transaction(function () use ($request, $companyDocumentForm, $values) {
            $submission = CompanyDocumentSubmission::query()->create([
                'company_document_form_id' => $companyDocumentForm->company_document_form_id,
                'form_version' => $companyDocumentForm->version,
                'form_snapshot_json' => $this->builderService->snapshotForm($companyDocumentForm),
                'submitted_by_user_id' => $request->user()?->id,
                'status' => CompanyDocumentSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            foreach ($companyDocumentForm->elements as $element) {
                if (! $element->isInput()) {
                    continue;
                }

                $key = (string) $element->field_key;
                $row = [
                    'submission_id' => $submission->submission_id,
                    'element_id' => $element->element_id,
                    'field_key' => $key,
                ];

                if (in_array($element->type, CompanyDocumentElement::fileBasedTypes(), true)) {
                    if ($request->hasFile("files.$key")) {
                        $file = $request->file("files.$key");
                        if ($element->type === CompanyDocumentElement::TYPE_SIGNATURE) {
                            $row['file_path'] = $this->fileService->storeSignature($file, $submission->submission_id);
                            $row['original_filename'] = $file->getClientOriginalName() ?: 'signature.png';
                            $row['mime_type'] = $file->getMimeType();
                        } else {
                            $stored = $this->fileService->storeSubmissionFile($file, $submission->submission_id, $element);
                            $row = array_merge($row, $stored);
                        }
                    }
                } else {
                    $value = $values[$key] ?? null;
                    if (is_array($value)) {
                        $row['value_json'] = $value;
                    } else {
                        $row['value_text'] = $value !== null ? (string) $value : null;
                    }
                }

                CompanyDocumentSubmissionValue::query()->create($row);
            }

            if ($companyDocumentForm->requiresApproval()) {
                $companyDocumentForm->loadMissing('approvals.assignees');
                $this->builderService->seedSubmissionApprovals($submission, $companyDocumentForm);
            } else {
                $submission->update([
                    'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            return $submission->fresh(['form']);
        });

        SysLogService::record(
            action: 'create',
            table: 'tbl_company_document_submissions',
            recordId: $submission->submission_id,
            newValues: $submission->logSnapshot(),
            description: 'Submitted company document for template: '.$companyDocumentForm->code,
        );

        return redirect()
            ->route('company-documents.submissions.show', [$companyDocumentForm, $submission])
            ->with('success', $companyDocumentForm->success_message ?: 'Document submitted successfully.');
    }

    public function show(CompanyDocumentForm $companyDocumentForm, CompanyDocumentSubmission $submission): View
    {
        $this->authorize('view', $companyDocumentForm);

        abort_unless(
            (int) $submission->company_document_form_id === (int) $companyDocumentForm->company_document_form_id,
            404,
        );

        $submission->load(['values', 'submitter:id,name,email', 'submissionApprovals.assignee:id,name,email']);

        SysLogService::record(
            action: 'read',
            table: 'tbl_company_document_submissions',
            recordId: $submission->submission_id,
            description: 'Viewed company document submission #'.$submission->submission_id,
        );

        return view('company-documents.submissions.show', [
            'form' => $companyDocumentForm,
            'submission' => $submission,
            'snapshot' => $submission->form_snapshot_json ?? [],
        ]);
    }
}
