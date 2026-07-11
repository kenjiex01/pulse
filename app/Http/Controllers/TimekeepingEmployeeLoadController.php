<?php

namespace App\Http\Controllers;

use App\Models\RawEmployeeLoadTransaction;
use App\Services\EmployeeLoadTemplateService;
use App\Services\EmployeeLoadUploadService;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\TimekeepingEmployeeLoad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimekeepingEmployeeLoadController extends Controller
{
    public function __construct(
        private readonly EmployeeLoadUploadService $uploadService,
        private readonly EmployeeLoadTemplateService $templateService,
    ) {}

    public function index(Request $request): View
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();

        $records = RawEmployeeLoadTransaction::query()
            ->with('uploadedBy')
            ->withCount('entries')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('filename', 'like', "%{$search}%")
                        ->orWhere('enrollment_period_label', 'like', "%{$search}%")
                        ->orWhere('batch_no', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('dt_uploaded')
            ->orderByDesc('employee_load_transaction_id')
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'raw_employee_load_transactions',
                description: 'Viewed Employee Load upload batches ('.$records->total().' batches)',
            );
        }

        $stagingToken = (string) session('employee_load_staging_token', '');
        $openPreview = $request->boolean('preview') && $stagingToken !== '';
        $staging = $openPreview ? $this->uploadService->getStaging($request->user(), $stagingToken) : null;

        $viewTransaction = null;

        if ($request->filled('view')) {
            $viewTransaction = RawEmployeeLoadTransaction::query()
                ->with(['uploadedBy', 'entries' => fn ($q) => $q->orderBy('session_date')->orderBy('employee_load_entry_id')])
                ->find($request->integer('view'));
        }

        $viewData = [
            'records' => $records,
            'search' => $search,
            'listColumns' => TimekeepingEmployeeLoad::listColumns(),
            'openUpload' => $request->boolean('upload'),
            'openPreview' => $openPreview,
            'staging' => $staging,
            'stagingToken' => $stagingToken,
            'viewTransaction' => $viewTransaction,
        ];

        if ($request->ajax()) {
            return view('timekeeping.employee-load._results', $viewData);
        }

        return view('timekeeping.employee-load.index', $viewData);
    }

    public function downloadTemplate(Request $request): StreamedResponse|RedirectResponse
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'add');

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        try {
            $content = $this->uploadService->buildTemplateContent(
                $validated['date_from'],
                $validated['date_to'],
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimekeepingEmployeeLoad::routeName('index'), ['upload' => 1])
                ->with('error', $exception->getMessage());
        }

        $filename = 'employee_load_template_'.$validated['date_from'].'_to_'.$validated['date_to'].'.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function processUpload(Request $request): RedirectResponse
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'add');

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'upload_file' => ['required', 'file', 'max:15360'],
        ]);

        try {
            $result = $this->uploadService->parseUploadedFile(
                $request->file('upload_file'),
                $validated['date_from'],
                $validated['date_to'],
            );

            $period = $this->resolvePeriodQuietly($validated['date_from'], $validated['date_to']);

            $token = $this->uploadService->createStagingToken($request->user(), [
                'enrollment_period_id' => $period['id'] ?? null,
                'enrollment_period_label' => $period['label'] ?? null,
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
            ], $result);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimekeepingEmployeeLoad::routeName('index'), ['upload' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session(['employee_load_staging_token' => $token]);

        return redirect()
            ->route(TimekeepingEmployeeLoad::routeName('index'), ['preview' => 1])
            ->with('success', 'File parsed. Review the preview before loading to the database.');
    }

    public function commitUpload(Request $request): RedirectResponse
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'add');

        $validated = $request->validate([
            'staging_token' => ['required', 'string'],
        ]);

        try {
            $transaction = $this->uploadService->commit($request->user(), $validated['staging_token']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimekeepingEmployeeLoad::routeName('index'), ['preview' => 1])
                ->with('error', $exception->getMessage());
        }

        session()->forget('employee_load_staging_token');

        SysLogService::record(
            action: 'create',
            table: 'raw_employee_load_transactions',
            recordId: $transaction->employee_load_transaction_id,
            description: 'Uploaded employee load batch #'.$transaction->formattedBatchNo().' ('.$transaction->filename.')',
        );

        return redirect()
            ->route(TimekeepingEmployeeLoad::routeName('index'), ['view' => $transaction->employee_load_transaction_id])
            ->with('success', 'Employee load successfully loaded to the database.');
    }

    public function discardStaging(Request $request): RedirectResponse
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'add');

        $token = (string) $request->input('staging_token', session('employee_load_staging_token'));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
        }

        session()->forget('employee_load_staging_token');

        return redirect()
            ->route(TimekeepingEmployeeLoad::routeName('index'), ['upload' => 1])
            ->with('success', 'Upload cancelled.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        TimekeepingEmployeeLoad::authorize($request->user(), 'delete');

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:raw_employee_load_transactions,employee_load_transaction_id'],
        ]);

        $deleted = DB::transaction(function () use ($validated) {
            $records = RawEmployeeLoadTransaction::query()
                ->whereIn('employee_load_transaction_id', $validated['selected_ids'])
                ->get();

            foreach ($records as $record) {
                SysLogService::record(
                    action: 'delete',
                    table: 'raw_employee_load_transactions',
                    recordId: $record->employee_load_transaction_id,
                    description: 'Purged employee load batch #'.$record->formattedBatchNo().' ('.$record->filename.')',
                );

                $record->delete();
            }

            return $records->count();
        });

        return redirect()
            ->route(TimekeepingEmployeeLoad::routeName('index'))
            ->with('success', $deleted.' batch'.($deleted === 1 ? '' : 'es').' purged.');
    }

    /**
     * Resolve the enrollment period for a date range without failing the upload
     * if resolution is not possible (the period is stored for reference only).
     *
     * @return array{id?: int, label?: string}
     */
    private function resolvePeriodQuietly(string $dateFrom, string $dateTo): array
    {
        try {
            return $this->templateService->resolvePeriodForRange($dateFrom, $dateTo);
        } catch (RuntimeException) {
            return [];
        }
    }
}
