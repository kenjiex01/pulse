<?php

namespace App\Http\Controllers;

use App\Models\RawTimekeepingTransaction;
use App\Models\TimeCaptureFormat;
use App\Services\SysLogService;
use App\Services\TimeLogsUploadService;
use App\Support\LiveTable;
use App\Support\TimeLogs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeLogsController extends Controller
{
    public function __construct(
        private readonly TimeLogsUploadService $uploadService,
    ) {}

    public function index(Request $request, string $tab): View
    {
        $tab = TimeLogs::resolveTab($tab);
        TimeLogs::authorize($request->user(), 'view');

        $config = TimeLogs::config($tab);
        $search = $request->string('search')->trim()->toString();

        $records = TimeLogs::query($tab)
            ->when($search !== '', function ($query) use ($config, $search) {
                $query->where(function ($searchQuery) use ($config, $search) {
                    foreach ($config['search'] as $column) {
                        $searchQuery->orWhere($column, 'like', '%'.$search.'%');
                    }

                    $searchQuery->orWhereHas('uploadedBy', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    });
                });
            })
            ->orderByDesc('timekeeping_transaction_id')
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
            'formats' => TimeCaptureFormat::query()->orderBy('device_name')->get(),
            'openUpload' => ($request->boolean('upload') || $request->boolean('create')) && ! $request->boolean('preview'),
            'openPreview' => $request->boolean('preview') && session('time_logs_staging_token'),
            'openViewId' => $request->input('view'),
            'stagingToken' => old('staging_token', session('time_logs_staging_token')),
            'viewTransaction' => null,
        ];

        if ($viewData['openViewId']) {
            $viewData['viewTransaction'] = RawTimekeepingTransaction::query()
                ->where('timekeeping_transaction_type_id', $config['transaction_type_id'])
                ->with([
                    'uploadedBy',
                    'timeCaptureFormat',
                    'inAndOutRecords' => fn ($query) => $query->with('employee')->orderBy('dt_datetime')->orderBy('timekeeping_inandout_id'),
                    'timeLogRecords' => fn ($query) => $query->with('employee'),
                ])
                ->find($viewData['openViewId']);
        }

        if ($viewData['openPreview'] && $viewData['stagingToken']) {
            $viewData['staging'] = $this->uploadService->getStaging($request->user(), (string) $viewData['stagingToken']);
            $viewData['previewFormat'] = isset($viewData['staging']['format_id'])
                ? TimeCaptureFormat::query()->find($viewData['staging']['format_id'])
                : null;
        }

        if ($request->ajax()) {
            return view('timekeeping.time-logs._results', $viewData);
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

        $validated = $request->validate([
            'timecapture_format_id' => ['required', 'exists:tbl_timecapture_formats,timecapture_format_id'],
            'upload_file' => ['required', 'file', 'max:10240'],
        ]);

        $format = TimeCaptureFormat::query()->with('fields')->findOrFail((int) $validated['timecapture_format_id']);

        try {
            $result = $this->uploadService->parseUploadedFile($request->file('upload_file'), $format);
            $token = $this->uploadService->createStagingToken($request->user(), $format->timecapture_format_id, $result);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimeLogs::routeName('tab'), ['tab' => TimeLogs::defaultTab(), 'upload' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session(['time_logs_staging_token' => $token]);

        return redirect()
            ->route(TimeLogs::routeName('tab'), [
                'tab' => TimeLogs::defaultTab(),
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

        try {
            $transaction = $this->uploadService->commit($request->user(), $validated['staging_token']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimeLogs::routeName('tab'), ['tab' => TimeLogs::defaultTab(), 'preview' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session()->forget('time_logs_staging_token');

        SysLogService::record(
            action: 'create',
            table: 'raw_timekeeping_transactions',
            recordId: $transaction->timekeeping_transaction_id,
            description: 'Uploaded time logs batch #'.$transaction->batch_no.' ('.$transaction->filename.')',
        );

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => TimeLogs::defaultTab(), 'view' => $transaction->timekeeping_transaction_id])
            ->with('success', 'Time logs successfully loaded to the database.');
    }

    public function discardStaging(Request $request): RedirectResponse
    {
        TimeLogs::authorize($request->user(), 'add');

        $token = (string) $request->input('staging_token', session('time_logs_staging_token'));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
        }

        session()->forget('time_logs_staging_token');

        return redirect()
            ->route(TimeLogs::routeName('tab'), ['tab' => TimeLogs::defaultTab(), 'upload' => 1])
            ->with('success', 'Upload cancelled.');
    }

    public function show(Request $request, string $tab, RawTimekeepingTransaction $transaction): View|RedirectResponse
    {
        $tab = TimeLogs::resolveTab($tab);
        TimeLogs::authorize($request->user(), 'view');

        $config = TimeLogs::config($tab);

        if ((int) $transaction->timekeeping_transaction_type_id !== (int) $config['transaction_type_id']) {
            abort(404);
        }

        $transaction->load([
            'uploadedBy',
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
}
