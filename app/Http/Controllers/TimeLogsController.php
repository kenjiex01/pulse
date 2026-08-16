<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\RawTimekeepingTransaction;
use App\Models\TeachingLoadPullBatch;
use App\Models\TeachingLoadSession;
use App\Models\TimeCaptureFormat;
use App\Services\SysLogService;
use App\Services\BiometricLogsS3PullService;
use App\Services\TimeLogsDtrUploadService;
use App\Services\TeachingLoadPullService;
use App\Services\TimeLogsUploadService;
use App\Support\LiveTable;
use App\Support\TimeLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeLogsController extends Controller
{
    public function __construct(
        private readonly TimeLogsUploadService $uploadService,
        private readonly TimeLogsDtrUploadService $dtrUploadService,
        private readonly TeachingLoadPullService $teachingLoadPullService,
        private readonly BiometricLogsS3PullService $biometricLogsS3PullService,
    ) {}

    public function index(Request $request, string $tab): View
    {
        $tab = TimeLogs::resolveTab($tab);
        TimeLogs::authorize($request->user(), 'view');

        $config = TimeLogs::config($tab);
        $search = $request->string('search')->trim()->toString();
        $isTeachingLoads = TimeLogs::isSkolarisPullTab($tab);

        $recordsQuery = TimeLogs::query($tab);

        if ($isTeachingLoads) {
            $recordsQuery = $recordsQuery
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('batch_no', 'like', '%'.$search.'%')
                            ->orWhereHas('pulledBy', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'like', '%'.$search.'%');
                            });
                    });
                })
                ->orderByDesc('pulled_at')
                ->orderByDesc('teaching_load_pull_batch_id');
        } else {
            $recordsQuery = $recordsQuery
                ->when($search !== '', function ($query) use ($config, $search, $tab) {
                    $query->where(function ($searchQuery) use ($config, $search, $tab) {
                        foreach ($config['search'] as $column) {
                            $searchQuery->orWhere($column, 'like', '%'.$search.'%');
                        }

                        $searchQuery->orWhereHas('uploadedBy', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%'.$search.'%');
                        });

                        if (TimeLogs::requiresCampus($tab)) {
                            $searchQuery->orWhereHas('campus', function ($campusQuery) use ($search) {
                                $campusQuery->where('campus_name', 'like', '%'.$search.'%');
                            });
                        }
                    });
                })
                ->orderByDesc('timekeeping_transaction_id');
        }

        $records = $recordsQuery
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: $config['log_table'],
                description: 'Viewed '.$config['name'].' list ('.$records->total().' records)',
            );
        }

        $viewData = [
            'tab' => $tab,
            'tabs' => TimeLogs::tabs(),
            'config' => $config,
            'records' => $records,
            'search' => $search,
            'isTeachingLoads' => $isTeachingLoads,
            'skolarisListError' => null,
            'pullEmployees' => $isTeachingLoads
                ? TimeLogs::eligiblePullEmployeesQuery($request->string('pull_search')->trim()->toString())
                    ->limit(200)
                    ->get()
                : collect(),
            'pullSearch' => $request->string('pull_search')->trim()->toString(),
            'formats' => TimeCaptureFormat::query()->orderBy('device_name')->get(),
            'dtrCampuses' => TimeLogs::dtrCampuses(),
            'requiresCampus' => TimeLogs::requiresCampus($tab),
            's3PullConfigured' => ! $isTeachingLoads && $this->biometricLogsS3PullService->isConfigured(),
            's3PullCampuses' => ! $isTeachingLoads
                ? Campus::query()->where('is_active', true)->orderBy('campus_name')->get(['campus_id', 'campus_code', 'campus_name'])
                : collect(),
            's3PullYear' => (int) now(config('backup.cloud.timezone', 'Asia/Manila'))->format('Y'),
            's3PullMonth' => (int) now(config('backup.cloud.timezone', 'Asia/Manila'))->format('m'),
            'openUpload' => ! $isTeachingLoads && ($request->boolean('upload') || $request->boolean('create')) && ! $request->boolean('preview') && ! $request->boolean('s3_pull'),
            'openS3Pull' => ! $isTeachingLoads && $request->boolean('s3_pull'),
            'openPull' => $isTeachingLoads && ($request->boolean('pull') || $request->boolean('create')),
            'openPreview' => ! $isTeachingLoads && $request->boolean('preview') && session('time_logs_staging_token'),
            'openViewId' => $request->input('view'),
            'openPullBatchId' => $request->integer('view_pull'),
            'openPullEmployeeId' => $request->integer('view_pull_employee'),
            'openPullEmployeeBatchId' => $request->integer('pull_batch'),
            'stagingToken' => old('staging_token', session('time_logs_staging_token')),
            'viewTransaction' => null,
            'viewPullBatch' => null,
            'viewPullEmployee' => null,
            'viewPullEmployeeRows' => collect(),
            'viewPullEmployeeSummary' => null,
        ];

        if ($viewData['openViewId'] && ! $isTeachingLoads) {
            $viewData['viewTransaction'] = RawTimekeepingTransaction::query()
                ->where('timekeeping_transaction_type_id', $config['transaction_type_id'])
                ->with([
                    'uploadedBy',
                    'campus',
                    'timeCaptureFormat',
                    'inAndOutRecords' => fn ($query) => $query->with('employee')->orderBy('dt_datetime')->orderBy('timekeeping_inandout_id'),
                    'timeLogRecords' => fn ($query) => $query->with('employee'),
                ])
                ->find($viewData['openViewId']);
        }

        if ($viewData['openPreview'] && $viewData['stagingToken']) {
            $viewData['staging'] = $this->uploadService->getStaging($request->user(), (string) $viewData['stagingToken'])
                ?? $this->dtrUploadService->getStaging($request->user(), (string) $viewData['stagingToken']);
            $viewData['isDtrStaging'] = ($viewData['staging']['parser'] ?? null) === 'dtr';
            $viewData['previewFormat'] = ! ($viewData['isDtrStaging'] ?? false) && isset($viewData['staging']['format_id'])
                ? TimeCaptureFormat::query()->find($viewData['staging']['format_id'])
                : null;
            $viewData['previewCampus'] = isset($viewData['staging']['campus_id'])
                ? TimeLogs::dtrCampuses()->firstWhere('campus_id', (int) $viewData['staging']['campus_id'])
                : null;
        }

        if ($isTeachingLoads && $viewData['openPullBatchId']) {
            $viewData['viewPullBatch'] = TeachingLoadPullBatch::query()
                ->with(['pulledBy', 'sessions.employee'])
                ->withCount('sessions as records_count')
                ->find($viewData['openPullBatchId']);
        }

        if ($isTeachingLoads && $viewData['openPullEmployeeBatchId'] && $viewData['openPullEmployeeId']) {
            $viewData['viewPullEmployeeRows'] = TeachingLoadSession::query()
                ->with(['employee', 'pullBatch'])
                ->where('teaching_load_pull_batch_id', $viewData['openPullEmployeeBatchId'])
                ->where('employee_id', $viewData['openPullEmployeeId'])
                ->orderBy('session_date')
                ->orderBy('time_in')
                ->get();

            $viewData['viewPullEmployee'] = $viewData['viewPullEmployeeRows']->first()?->employee;
            $viewData['viewPullEmployeeSummary'] = $viewData['viewPullEmployeeRows']->first()?->pullBatch;
        }

        if ($request->ajax()) {
            return view($isTeachingLoads ? 'timekeeping.time-logs._teaching-loads-results' : 'timekeeping.time-logs._results', $viewData);
        }

        return view('timekeeping.time-logs.index', $viewData);
    }

    public function downloadTemplate(Request $request, TimeCaptureFormat $timeCaptureFormat): StreamedResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $format = TimeCaptureFormat::query()->with('fields')->findOrFail($timeCaptureFormat->timecapture_format_id);
        $content = $this->uploadService->buildTemplateContent($format);
        $filename = 'timelogs_template_'.$format->device_name.'.txt';

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => 'text/plain'],
        );
    }

    public function processUpload(Request $request): RedirectResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $tab = TimeLogs::resolveTab($request->input('tab'));

        $rules = [
            'tab' => ['required', Rule::in(array_keys(TimeLogs::tabs()))],
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb', 15360)],
        ];

        if (TimeLogs::requiresCampus($tab)) {
            $rules['campus_id'] = [
                'required',
                Rule::exists('tbl_campuses', 'campus_id')
                    ->whereIn('campus_code', TimeLogs::dtrCampusCodes())
                    ->whereNull('deleted_at'),
            ];
        } else {
            $rules['timecapture_format_id'] = ['required', 'exists:tbl_timecapture_formats,timecapture_format_id'];
        }

        $validated = $request->validate($rules);

        try {
            if (TimeLogs::requiresCampus($tab)) {
                $campus = Campus::query()->findOrFail((int) $validated['campus_id']);
                $result = $this->dtrUploadService->parseUploadedFile($request->file('upload_file'), $campus);
                $token = $this->dtrUploadService->createStagingToken(
                    $request->user(),
                    $campus->campus_id,
                    $result,
                    $tab,
                );
            } else {
                $format = TimeCaptureFormat::query()->with('fields')->findOrFail((int) $validated['timecapture_format_id']);
                $result = $this->uploadService->parseUploadedFile($request->file('upload_file'), $format);
                $token = $this->uploadService->createStagingToken(
                    $request->user(),
                    $format->timecapture_format_id,
                    $result,
                    $tab,
                );
            }
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimeLogs::routeName('tab'), ['tab' => $tab, 'upload' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session(['time_logs_staging_token' => $token]);

        return redirect()
            ->route(TimeLogs::routeName('tab'), [
                'tab' => $tab,
                'preview' => 1,
            ])
            ->with('success', 'File parsed. Review the preview before loading to the database.');
    }

    public function commit(Request $request): RedirectResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $validated = $request->validate([
            'staging_token' => ['required', 'string'],
        ]);

        $staging = $this->uploadService->getStaging($request->user(), $validated['staging_token'])
            ?? $this->dtrUploadService->getStaging($request->user(), $validated['staging_token']);
        $tab = TimeLogs::resolveTab(is_array($staging) ? ($staging['tab'] ?? null) : null);
        $isDtr = is_array($staging) && ($staging['parser'] ?? null) === 'dtr';

        try {
            $result = $isDtr
                ? $this->dtrUploadService->commit($request->user(), $validated['staging_token'])
                : $this->uploadService->commit($request->user(), $validated['staging_token']);
            $transaction = $result['transaction'];
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimeLogs::routeName('tab'), ['tab' => $tab, 'preview' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session()->forget('time_logs_staging_token');

        $config = TimeLogs::config($tab);

        SysLogService::record(
            action: 'create',
            table: 'raw_timekeeping_transactions',
            recordId: $transaction->timekeeping_transaction_id,
            description: 'Uploaded '.$config['name'].' batch #'.$transaction->batch_no.' ('.$transaction->filename.')',
        );

        $successMessage = 'Time logs successfully loaded to the database ('.$result['inserted'].' record(s)).';

        if (($result['skipped_duplicates'] ?? 0) > 0) {
            $successMessage .= ' '.$result['skipped_duplicates'].' duplicate record(s) were skipped.';
        }

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => $tab, 'view' => $transaction->timekeeping_transaction_id])
            ->with('success', $successMessage);
    }

    public function discardStaging(Request $request): RedirectResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $token = (string) $request->input('staging_token', session('time_logs_staging_token'));
        $staging = $token !== '' ? (
            $this->uploadService->getStaging($request->user(), $token)
            ?? $this->dtrUploadService->getStaging($request->user(), $token)
        ) : null;
        $tab = TimeLogs::resolveTab(is_array($staging) ? ($staging['tab'] ?? null) : $request->input('tab'));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
            $this->dtrUploadService->discardStaging($request->user(), $token);
        }

        session()->forget('time_logs_staging_token');

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => $tab, 'upload' => 1])
            ->with('success', 'Upload cancelled.');
    }

    public function show(Request $request, string $tab, RawTimekeepingTransaction $transaction): View|RedirectResponse
    {
        $tab = TimeLogs::resolveTab($tab);
        TimeLogs::authorize($request->user(), 'view');

        if (TimeLogs::isSkolarisPullTab($tab)) {
            abort(404);
        }

        $config = TimeLogs::config($tab);

        if ((int) $transaction->timekeeping_transaction_type_id !== (int) $config['transaction_type_id']) {
            abort(404);
        }

        $transaction->load([
            'uploadedBy',
            'campus',
            'timeCaptureFormat',
            'inAndOutRecords' => fn ($query) => $query->with('employee')->orderBy('dt_datetime')->orderBy('timekeeping_inandout_id'),
            'timeLogRecords' => fn ($query) => $query->with('employee'),
        ]);

        return view('timekeeping.time-logs._show-content', [
            'tab' => $tab,
            'config' => $config,
            'transaction' => $transaction,
        ]);
    }

    public function destroy(Request $request, string $tab): RedirectResponse
    {
        $tab = TimeLogs::resolveTab($tab);

        if (TimeLogs::isSkolarisPullTab($tab)) {
            abort(404);
        }

        TimeLogs::authorize($request->user(), 'delete');

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:raw_timekeeping_transactions,timekeeping_transaction_id'],
        ]);

        $config = TimeLogs::config($tab);
        $ids = $validated['selected_ids'];

        $deleted = DB::transaction(function () use ($config, $ids) {
            $records = RawTimekeepingTransaction::query()
                ->whereIn('timekeeping_transaction_id', $ids)
                ->where('timekeeping_transaction_type_id', $config['transaction_type_id'])
                ->get();

            foreach ($records as $record) {
                SysLogService::record(
                    action: 'delete',
                    table: 'raw_timekeeping_transactions',
                    recordId: $record->timekeeping_transaction_id,
                    description: 'Purged time logs batch #'.$record->batch_no.' ('.$record->filename.')',
                );

                $record->delete();
            }

            return $records->count();
        });

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => $tab])
            ->with('success', $deleted.' batch'.($deleted === 1 ? '' : 'es').' purged.');
    }

    public function pullBiometricLogsFromS3(Request $request): RedirectResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'campus_id' => ['nullable', 'integer', 'exists:tbl_campuses,campus_id'],
            'collector_folder' => ['nullable', 'string', 'max:128'],
            'tab' => ['nullable', 'string'],
        ]);

        $tab = TimeLogs::resolveTab($validated['tab'] ?? 'time-in-out');

        if (TimeLogs::isSkolarisPullTab($tab)) {
            $tab = 'time-in-out';
        }

        try {
            $summary = $this->biometricLogsS3PullService->pull(
                user: $request->user(),
                year: (int) $validated['year'],
                month: (int) $validated['month'],
                campusId: isset($validated['campus_id']) ? (int) $validated['campus_id'] : null,
                collectorFolder: $validated['collector_folder'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimeLogs::routeName('tab'), ['tab' => $tab, 's3_pull' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $message = sprintf(
            'S3 pull finished: %d file(s) scanned, %d imported, %d skipped, %d punch(es) inserted, %d duplicate(s) skipped, %d unmatched biometric ID(s).',
            $summary['files_scanned'],
            $summary['files_imported'],
            $summary['files_skipped'],
            $summary['punches_inserted'],
            $summary['punches_skipped_duplicates'],
            $summary['punches_unmatched'],
        );

        if ($summary['errors'] !== []) {
            $message .= ' Errors: '.implode(' | ', array_slice($summary['errors'], 0, 3));
        }

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => $tab])
            ->with($summary['errors'] !== [] && $summary['files_imported'] === 0 ? 'error' : 'success', $message);
    }

    public function listBiometricS3Folders(Request $request): JsonResponse
    {
        TimeLogs::authorize($request->user(), 'view');

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if (! $this->biometricLogsS3PullService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'S3 is not configured.',
                'folders' => [],
            ], 422);
        }

        $folders = $this->biometricLogsS3PullService->listCollectorFolders(
            (int) $validated['year'],
            (int) $validated['month'],
        );

        return response()->json([
            'success' => true,
            'folders' => $folders,
        ]);
    }

    public function startTeachingLoadPull(Request $request): JsonResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
        ]);

        try {
            $result = $this->teachingLoadPullService->startJob(
                $request->user(),
                $validated['date_from'],
                $validated['date_to'],
                $validated['employee_ids'],
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'token' => $result['token'],
            'total' => $result['total'],
        ]);
    }

    public function stepTeachingLoadPull(Request $request): JsonResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $validated = $request->validate([
            'job_token' => ['required', 'string'],
        ]);

        try {
            $progress = $this->teachingLoadPullService->processNext($validated['job_token']);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(array_merge(['success' => true], $progress));
    }

}
