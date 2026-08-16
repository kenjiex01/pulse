<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeUploadService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeUploadController extends Controller
{
    public function __construct(private readonly EmployeeUploadService $uploadService) {}

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->authorize('create', Employee::class);

        $uploadType = $this->uploadService->normalizeUploadType((string) $request->query('type', 'master-file'));
        $path = $this->uploadService->templateFilePath($uploadType);
        $filename = (string) (($this->uploadService->uploadTypes()[$uploadType]['template_filename'] ?? 'employee_upload_template.xlsx'));

        if (! is_readable($path)) {
            abort(500, 'Employee upload template is missing. Please contact support.');
        }

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function processUpload(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $uploadTypes = array_keys($this->uploadService->uploadTypes());

        $validated = $request->validate([
            'upload_type' => ['required', Rule::in($uploadTypes)],
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb')],
            'disable_required_fields' => ['nullable', 'boolean'],
        ]);

        $uploadType = $this->uploadService->normalizeUploadType($validated['upload_type']);
        $disableRequiredFields = $uploadType === 'master-file'
            && $request->boolean('disable_required_fields');

        $parseResult = $this->uploadService->parseUploadedFile(
            $validated['upload_file'],
            $uploadType,
            $disableRequiredFields,
        );
        $token = $this->uploadService->createStagingToken($request->user(), $parseResult);

        session(['employee_upload_staging_token' => $token]);

        return redirect()
            ->route('employees.index', ['preview' => 1])
            ->with('success', 'Upload parsed. Review the preview before importing.');
    }

    public function commitUpload(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $token = (string) $request->input('staging_token', session('employee_upload_staging_token', ''));

        if ($token === '') {
            throw ValidationException::withMessages([
                'staging_token' => 'Upload preview expired. Please upload the file again.',
            ]);
        }

        $staging = $this->uploadService->getStaging($request->user(), $token);
        $result = $this->uploadService->commit($request->user(), $token);

        session()->forget('employee_upload_staging_token');

        if (($staging['upload_type'] ?? 'master-file') === 'employee-salary') {
            SysLogService::record(
                action: 'update',
                table: 'tbl_employee_salaries',
                description: 'Updated '.$result['updated'].' employee salary record(s) via bulk upload',
            );

            return redirect()
                ->route('employees.index')
                ->with('success', $result['updated'].' salary record(s) updated successfully.');
        }

        SysLogService::record(
            action: 'create',
            table: 'tbl_employees',
            description: 'Imported '.$result['created'].' employee(s) and updated '.$result['updated'].' employee(s) via bulk upload',
        );

        return redirect()
            ->route('employees.index')
            ->with('success', $result['created'].' employee(s) created, '.$result['updated'].' employee(s) updated.');
    }

    public function discardStaging(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $token = (string) $request->input('staging_token', session('employee_upload_staging_token', ''));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
        }

        session()->forget('employee_upload_staging_token');

        return redirect()
            ->route('employees.index')
            ->with('success', 'Upload discarded.');
    }
}
