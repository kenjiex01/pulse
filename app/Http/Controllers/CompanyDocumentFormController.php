<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyDocument\StoreCompanyDocumentFormRequest;
use App\Http\Requests\CompanyDocument\UpdateCompanyDocumentFormRequest;
use App\Models\CompanyDocumentForm;
use App\Services\CompanyDocumentFileService;
use App\Services\CompanyDocumentMemoRenderService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyDocumentFormController extends Controller
{
    public function __construct(
        private readonly CompanyDocumentFileService $fileService,
        private readonly CompanyDocumentMemoRenderService $memoRenderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CompanyDocumentForm::class);

        $search = $request->string('search')->trim()->toString();

        $forms = CompanyDocumentForm::query()
            ->with(['elements'])
            ->withCount(['elements'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        SysLogService::record(
            action: 'read',
            table: 'tbl_company_document_forms',
            description: 'Viewed Company Documents list ('.$forms->count().' templates)',
        );

        return view('company-documents.index', [
            'forms' => $forms,
            'search' => $search,
            'documentTypes' => CompanyDocumentForm::documentTypes(),
            'openCreate' => $request->boolean('create'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', CompanyDocumentForm::class);

        return redirect()->route('company-documents.index', ['create' => 1]);
    }

    public function store(StoreCompanyDocumentFormRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['code'] = trim((string) ($payload['code'] ?? '')) ?: $this->generateCode($payload['name']);
        $payload['created_by'] = $request->user()?->id;
        $payload['sort_order'] = (int) CompanyDocumentForm::query()->max('sort_order') + 1;
        $payload['allow_multiple_submissions'] = $request->boolean('allow_multiple_submissions', true);
        $payload['submit_label'] = $payload['submit_label'] ?? 'Submit';
        $payload['success_message'] = $payload['success_message'] ?? 'Document submitted successfully.';

        $form = CompanyDocumentForm::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: 'tbl_company_document_forms',
            recordId: $form->company_document_form_id,
            newValues: $form->logSnapshot(),
            description: 'Created company document template: '.$form->code,
        );

        return redirect()
            ->route('company-documents.designer', $form)
            ->with('success', 'Template created. Design the memo fields in the builder.');
    }

    public function show(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('view', $companyDocumentForm);

        return redirect()->route('company-documents.index', [
            'view_form' => $companyDocumentForm->company_document_form_id,
        ]);
    }

    public function previewHtml(CompanyDocumentForm $companyDocumentForm): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $companyDocumentForm);

        $preview = $this->memoRenderService->buildSamplePreviewData($companyDocumentForm);
        $html = $this->memoRenderService->renderDocumentHtml($preview, true);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function previewPdf(CompanyDocumentForm $companyDocumentForm): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $companyDocumentForm);

        $pdf = $this->memoRenderService->renderSamplePdf($companyDocumentForm);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$companyDocumentForm->code.'-preview.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function previewAsset(Request $request, CompanyDocumentForm $companyDocumentForm): BinaryFileResponse
    {
        $this->authorize('view', $companyDocumentForm);

        $path = $request->string('path')->trim()->toString();
        abort_if($path === '', 404);

        try {
            $this->fileService->assertDesignerAssetPath((int) $companyDocumentForm->company_document_form_id, $path);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }

    public function edit(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('update', $companyDocumentForm);

        return redirect()->route('company-documents.index', [
            'edit_form' => $companyDocumentForm->company_document_form_id,
        ]);
    }

    public function update(UpdateCompanyDocumentFormRequest $request, CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $oldValues = $companyDocumentForm->logSnapshot();
        $payload = $request->validated();
        $payload['allow_multiple_submissions'] = $request->boolean('allow_multiple_submissions');

        $companyDocumentForm->update($payload);

        SysLogService::record(
            action: 'update',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            oldValues: $oldValues,
            newValues: $companyDocumentForm->fresh()->logSnapshot(),
            description: 'Updated company document template: '.$companyDocumentForm->code,
        );

        return redirect()
            ->route('company-documents.index')
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('delete', $companyDocumentForm);

        if (! $companyDocumentForm->is_active) {
            $oldValues = $companyDocumentForm->logSnapshot();
            $code = $companyDocumentForm->code;
            $id = $companyDocumentForm->company_document_form_id;

            $companyDocumentForm->delete();

            SysLogService::record(
                action: 'delete',
                table: 'tbl_company_document_forms',
                recordId: $id,
                oldValues: $oldValues,
                description: 'Deleted company document template: '.$code,
            );

            return redirect()
                ->route('company-documents.index')
                ->with('success', 'Template deleted successfully.');
        }

        return redirect()
            ->route('company-documents.index')
            ->with('error', 'Deactivate the template before deleting it.');
    }

    public function toggle(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('update', $companyDocumentForm);

        $oldValues = $companyDocumentForm->logSnapshot();
        $companyDocumentForm->update(['is_active' => ! $companyDocumentForm->is_active]);

        SysLogService::record(
            action: 'update',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            oldValues: $oldValues,
            newValues: $companyDocumentForm->fresh()->logSnapshot(),
            description: 'Toggled company document template active state: '.$companyDocumentForm->code,
        );

        return redirect()
            ->route('company-documents.index')
            ->with('success', $companyDocumentForm->is_active ? 'Template activated.' : 'Template deactivated.');
    }

    public function duplicate(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('create', CompanyDocumentForm::class);

        $companyDocumentForm->load(['elements', 'approvals.assignees']);

        $copy = $companyDocumentForm->replicate(['version']);
        $copy->code = $this->generateCode($companyDocumentForm->code.' copy');
        $copy->name = $companyDocumentForm->name.' (Copy)';
        $copy->is_active = false;
        $copy->version = 1;
        $copy->created_by = auth()->id();
        $copy->sort_order = (int) CompanyDocumentForm::query()->max('sort_order') + 1;
        $copy->save();

        foreach ($companyDocumentForm->elements as $element) {
            $newElement = $element->replicate();
            $newElement->company_document_form_id = $copy->company_document_form_id;
            $newElement->save();
        }

        foreach ($companyDocumentForm->approvals as $approval) {
            $newApproval = $approval->replicate();
            $newApproval->company_document_form_id = $copy->company_document_form_id;
            $newApproval->save();

            foreach ($approval->assignees as $assignee) {
                $newAssignee = $assignee->replicate();
                $newAssignee->approval_id = $newApproval->approval_id;
                $newAssignee->save();
            }
        }

        SysLogService::record(
            action: 'create',
            table: 'tbl_company_document_forms',
            recordId: $copy->company_document_form_id,
            newValues: $copy->logSnapshot(),
            description: 'Duplicated company document template from: '.$companyDocumentForm->code,
        );

        return redirect()
            ->route('company-documents.designer', $copy)
            ->with('success', 'Template duplicated. Review the copy before activating.');
    }

    private function generateCode(string $name): string
    {
        $base = Str::slug(Str::limit($name, 60, ''), '_');
        $base = $base !== '' ? $base : 'memo_template';
        $code = $base;
        $index = 2;

        while (CompanyDocumentForm::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$index;
            $index++;
        }

        return Str::limit($code, 80, '');
    }
}
