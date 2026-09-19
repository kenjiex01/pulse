<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyDocument\StoreCompanyDocumentFormRequest;
use App\Http\Requests\CompanyDocument\UpdateCompanyDocumentFormRequest;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\Employee;
use App\Models\LuIcctOffense;
use App\Models\User;
use App\Services\CompanyDocumentFileService;
use App\Services\CompanyDocumentMemoRenderService;
use App\Services\CompanyDocumentSendService;
use App\Services\SysLogService;
use App\Support\CompanyDocumentSendHistorySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyDocumentFormController extends Controller
{
    public function __construct(
        private readonly CompanyDocumentFileService $fileService,
        private readonly CompanyDocumentMemoRenderService $memoRenderService,
        private readonly CompanyDocumentSendService $sendService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CompanyDocumentForm::class);

        $search = $request->string('search')->trim()->toString();

        $forms = CompanyDocumentForm::query()
            ->with(['elements', 'icctOffense'])
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

        $sendHistoryByForm = CompanyDocumentSendLog::query()
            ->with([
                'employee:employee_id,employee_number,first_name,middle_name,last_name,suffix',
                'sender:id,name',
            ])
            ->whereIn('company_document_form_id', $forms->pluck('company_document_form_id'))
            ->orderByDesc('sent_at')
            ->limit(500)
            ->get()
            ->groupBy('company_document_form_id');

        $sendSummaryByForm = $sendHistoryByForm->map(
            fn ($logs) => CompanyDocumentSendHistorySummary::byEmployee($logs),
        );

        return view('company-documents.index', [
            'forms' => $forms,
            'search' => $search,
            'documentTypes' => CompanyDocumentForm::documentTypes(),
            'icctOffenses' => LuIcctOffense::catalogForSelection($forms->pluck('icct_offense_id')),
            'openCreate' => $request->boolean('create'),
            'sendEmployees' => $this->employeesForSend($request->user()),
            'sendSummaryByForm' => $sendSummaryByForm,
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
        $isMemo = $payload['document_type'] === CompanyDocumentForm::TYPE_MEMO;
        $payload['requires_nte'] = $isMemo && $request->boolean('requires_nte');
        $payload['is_nte'] = $isMemo && $request->boolean('is_nte');
        $payload['icct_offense_id'] = ($isMemo && ! $payload['is_nte'])
            ? ($payload['icct_offense_id'] ?? null)
            : null;

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
        $isMemo = $payload['document_type'] === CompanyDocumentForm::TYPE_MEMO;
        $payload['requires_nte'] = $isMemo && $request->boolean('requires_nte');
        $payload['is_nte'] = $isMemo && $request->boolean('is_nte');
        $payload['icct_offense_id'] = ($isMemo && ! $payload['is_nte'])
            ? ($payload['icct_offense_id'] ?? null)
            : null;

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
        $willActivate = ! $companyDocumentForm->is_active;

        if ($willActivate && $companyDocumentForm->is_nte) {
            $existing = CompanyDocumentForm::conflictingActiveNte($companyDocumentForm->company_document_form_id);

            if ($existing !== null) {
                return redirect()
                    ->route('company-documents.index')
                    ->with(
                        'error',
                        'Cannot activate this template as NTE. Another active memo is already set as NTE ('.$existing->name.'). Uncheck Set as NTE on that template first.',
                    );
            }
        }

        $companyDocumentForm->update(['is_active' => $willActivate]);

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

    public function send(Request $request, CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        @set_time_limit(300);

        $this->authorize('send', $companyDocumentForm);

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
        ]);

        $result = $this->sendToEmployees($companyDocumentForm, $validated['employee_ids'], $request->user());

        SysLogService::record(
            action: 'create',
            table: 'tbl_company_document_send_logs',
            description: 'Sent company document "'.$companyDocumentForm->name.'" to '.$result['sent'].' employee(s)',
        );

        if ($result['sent'] === 0) {
            return redirect()
                ->route('company-documents.index', $request->only('search'))
                ->with('error', $result['errors'][0] ?? 'No documents were sent.');
        }

        $message = 'Document sent to '.$result['sent'].' employee(s).';
        if ($result['errors'] !== []) {
            $message .= ' Some failed: '.implode('; ', array_slice($result['errors'], 0, 3));
            if (count($result['errors']) > 3) {
                $message .= ' (+'.(count($result['errors']) - 3).' more)';
            }
        }

        return redirect()
            ->route('company-documents.index', $request->only('search'))
            ->with('success', $message);
    }

    public function sendOne(Request $request, CompanyDocumentForm $companyDocumentForm): JsonResponse
    {
        @set_time_limit(120);

        $this->authorize('send', $companyDocumentForm);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:tbl_employees,employee_id'],
        ]);

        $employee = Employee::query()->find($validated['employee_id']);
        if ($employee === null) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        try {
            $result = $this->sendService->sendToEmployee($companyDocumentForm, $employee, $request->user());
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Unable to send document.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send document. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'employee_id' => (int) $employee->employee_id,
            'employee_name' => trim($employee->full_name),
            'submission_id' => $result['submission_id'],
        ]);
    }

    public function sendBatchComplete(Request $request, CompanyDocumentForm $companyDocumentForm): JsonResponse
    {
        $this->authorize('send', $companyDocumentForm);

        $validated = $request->validate([
            'sent' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);

        if ($validated['sent'] > 0) {
            SysLogService::record(
                action: 'create',
                table: 'tbl_company_document_send_logs',
                description: 'Sent company document "'.$companyDocumentForm->name.'" to '.$validated['sent'].' of '.$validated['total'].' employee(s)',
            );
        }

        return response()->json(['success' => true]);
    }

    /**
     * @param  list<int|string>  $employeeIds
     * @return array{sent: int, errors: list<string>}
     */
    private function sendToEmployees(CompanyDocumentForm $form, array $employeeIds, User $sender): array
    {
        $sent = 0;
        $errors = [];

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::query()->find($employeeId);
            if ($employee === null) {
                continue;
            }

            try {
                $this->sendService->sendToEmployee($form, $employee, $sender);
                $sent++;
            } catch (\RuntimeException $exception) {
                $errors[] = trim($employee->full_name).': '.$exception->getMessage();
            }
        }

        return [
            'sent' => $sent,
            'errors' => $errors,
        ];
    }

    public function duplicate(CompanyDocumentForm $companyDocumentForm): RedirectResponse
    {
        $this->authorize('create', CompanyDocumentForm::class);

        $companyDocumentForm->load(['elements', 'approvals.assignees']);

        $copy = $companyDocumentForm->replicate(['version']);
        $copy->code = $this->generateCode($companyDocumentForm->code.' copy');
        $copy->name = $companyDocumentForm->name.' (Copy)';
        $copy->is_active = false;
        $copy->is_nte = false;
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

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeesForSend(?User $user)
    {
        $query = Employee::query()
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($user && ! $user->isAdmin()) {
            $query->where(function ($q) {
                $q->whereNull('is_confidential')
                    ->orWhere('is_confidential', false);
            });
        }

        return $query->get([
            'employee_id',
            'employee_number',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'is_confidential',
        ]);
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
