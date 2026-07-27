<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeUploadService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeUploadController extends Controller
{
    public function __construct(private readonly EmployeeUploadService $uploadService) {}

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', Employee::class);

        $path = $this->uploadService->templateFilePath();

        if (! is_readable($path)) {
            abort(500, 'Employee upload template is missing. Please contact support.');
        }

        return response()->download(
            $path,
            'employee_upload_template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function processUpload(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb')],
        ]);

        $parseResult = $this->uploadService->parseUploadedFile($validated['upload_file']);
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

        $result = $this->uploadService->commit($request->user(), $token);

        session()->forget('employee_upload_staging_token');

        SysLogService::record(
            action: 'create',
            table: 'tbl_employees',
            description: 'Imported '.$result['created'].' employee(s) via bulk upload',
        );

        return redirect()
            ->route('employees.index')
            ->with('success', $result['created'].' employee(s) imported successfully with full profile, employment, campus, and salary data.');
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
