<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\DocumentType;
use App\Services\EmployeeCredentialPreviewService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeCredentialController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $maxKb = (int) config('uploads.max_file_kb', 15360);

        try {
            $validated = $request->validate([
                'document_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('tbl_document_types', 'document_type_id')->where(function ($query) {
                        $query->where('is_active', true)->whereNull('deleted_at');
                    }),
                ],
                'description' => ['nullable', 'string', 'max:255'],
                'attachment' => [
                    'required',
                    'file',
                    'max:'.$maxKb,
                    'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,txt,csv',
                ],
            ], [], [
                'document_type_id' => 'document type',
                'attachment' => 'file attachment',
            ]);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('employees.edit', [
                'employee' => $employee,
                'tab' => 'credentials',
            ]));
        }

        $documentType = DocumentType::query()->findOrFail((int) $validated['document_type_id']);
        $description = trim((string) ($validated['description'] ?? ''));
        if ($description === '') {
            $description = $documentType->type_name;
        }

        $file = $validated['attachment'];
        $directory = 'employee-credentials/'.$employee->employee_id;
        $storedPath = $file->store($directory, 'local');

        $credential = EmployeeCredential::query()->create([
            'employee_id' => $employee->employee_id,
            'document_type_id' => $documentType->document_type_id,
            'description' => $description,
            'original_filename' => (string) $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
        ]);
        $credential->setRelation('documentType', $documentType);

        SysLogService::record(
            action: 'create',
            table: 'tbl_employee_credentials',
            recordId: $credential->employee_credential_id,
            newValues: [
                'employee_id' => $employee->employee_id,
                'document_type_id' => $credential->document_type_id,
                'description' => $credential->description,
                'original_filename' => $credential->original_filename,
            ],
            description: 'Uploaded employee credential for '.$employee->employee_number.': '.$credential->displayLabel(),
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'credentials'])
            ->with('success', 'Credential file uploaded.');
    }

    public function preview(
        Employee $employee,
        EmployeeCredential $credential,
        EmployeeCredentialPreviewService $previewService,
    ): Response|BinaryFileResponse {
        $this->authorize('view', $employee);
        $this->ensureBelongsToEmployee($employee, $credential);

        SysLogService::record(
            action: 'read',
            table: 'tbl_employee_credentials',
            recordId: $credential->employee_credential_id,
            description: 'Previewed employee credential for '.$employee->employee_number.': '.$credential->description,
        );

        return $previewService->respond($credential);
    }

    public function content(
        Employee $employee,
        EmployeeCredential $credential,
        EmployeeCredentialPreviewService $previewService,
    ): BinaryFileResponse {
        $this->authorize('view', $employee);
        $this->ensureBelongsToEmployee($employee, $credential);

        return $previewService->content($credential);
    }

    public function download(Employee $employee, EmployeeCredential $credential): StreamedResponse
    {
        $this->authorize('view', $employee);
        $this->ensureBelongsToEmployee($employee, $credential);

        if (! Storage::disk('local')->exists($credential->stored_path)) {
            abort(404, 'Credential file not found.');
        }

        SysLogService::record(
            action: 'read',
            table: 'tbl_employee_credentials',
            recordId: $credential->employee_credential_id,
            description: 'Downloaded employee credential for '.$employee->employee_number.': '.$credential->description,
        );

        return Storage::disk('local')->download(
            $credential->stored_path,
            $credential->original_filename,
        );
    }

    public function destroy(Employee $employee, EmployeeCredential $credential): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->ensureBelongsToEmployee($employee, $credential);

        $old = [
            'description' => $credential->description,
            'original_filename' => $credential->original_filename,
        ];

        $credential->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_employee_credentials',
            recordId: $credential->employee_credential_id,
            oldValues: $old,
            description: 'Soft-deleted employee credential for '.$employee->employee_number.': '.$old['description'],
        );

        return redirect()
            ->route('employees.edit', ['employee' => $employee, 'tab' => 'credentials'])
            ->with('success', 'Credential file removed.');
    }

    private function ensureBelongsToEmployee(Employee $employee, EmployeeCredential $credential): void
    {
        if ((int) $credential->employee_id !== (int) $employee->employee_id) {
            abort(404);
        }
    }
}
