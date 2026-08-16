<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\Report;
use App\Models\User;
use App\Services\PayrollBatchService;
use App\Services\Reports\ReportBatchOptionsService;
use App\Services\SysLogService;
use App\Support\PayrollReportsModule;
use App\Support\ReportPdfDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportsController extends Controller
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
        private readonly PayrollBatchService $payrollBatchService,
    ) {}

    public function index(Request $request): View
    {
        PayrollReportsModule::authorize($request->user());

        $classificationCode = (string) $request->input(
            'classification',
            PayrollReportsModule::defaultClassificationCode(),
        );

        $classification = PayrollReportsModule::resolveClassification($classificationCode);

        $reports = Report::query()
            ->with(['group', 'fileTypes'])
            ->where('report_classification_id', $classification->report_classification_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Report $report) => $report->group?->name ?? 'Reports');

        $selectedReportId = (int) $request->input('report_id', $reports->flatten(1)->first()?->report_id ?? 0);
        $selectedReport = null;

        if ($selectedReportId > 0) {
            $selectedReport = Report::query()
                ->with(['group', 'fileTypes'])
                ->where('report_id', $selectedReportId)
                ->where('report_classification_id', $classification->report_classification_id)
                ->where('is_active', true)
                ->first();
        }

        if (! $selectedReport) {
            $selectedReport = $reports->flatten(1)->first();
        }

        SysLogService::record(
            action: 'read',
            table: 'payroll_reports',
            description: 'Opened payroll reports page',
        );

        $replayValidation = $request->session()->has('errors');

        return view('payroll.reports.index', [
            'classifications' => PayrollReportsModule::activeClassifications(),
            'classification' => $classification,
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'lazyLoadReportOptions' => ! $replayValidation,
            'processedBatches' => $replayValidation && $selectedReport
                ? $this->batchOptions->processedBatchesForUser($request->user())
                : collect(),
            'postedBatches' => $replayValidation && $selectedReport && in_array($selectedReport->options_key, ['payslip', 'bir-1601c'], true)
                ? $this->batchOptions->postedBatchesForUser($request->user())
                : collect(),
            'payYears' => $replayValidation && $selectedReport && in_array($selectedReport->options_key, ['bir-2316', 'alphalist'], true)
                ? $this->batchOptions->postedPayYearsForUser($request->user())
                : [],
            'employees' => $replayValidation && $selectedReport && in_array($selectedReport->options_key, ['historical-data', 'attendance-view'], true)
                ? $this->employeesForReportOptions($request->user())
                : collect(),
            'detailColumns' => config('payroll_reports.detail_columns', []),
            'sortColumns' => config('payroll_reports.sort_columns', []),
            'groupColumns' => config('payroll_reports.group_columns', []),
        ]);
    }

    public function options(Request $request, Report $report): View
    {
        PayrollReportsModule::authorize($request->user());

        $classification = PayrollReportsModule::resolveClassification(
            (string) $request->input('classification', PayrollReportsModule::defaultClassificationCode()),
        );

        $report = PayrollReportsModule::resolveReport($report->report_id, $classification);
        $optionsConfig = PayrollReportsModule::optionsConfig($report->options_key);

        $viewData = [
            'report' => $report,
            'processedBatches' => $this->batchOptions->processedBatchesForUser($request->user()),
            'detailColumns' => config('payroll_reports.detail_columns', []),
            'sortColumns' => config('payroll_reports.sort_columns', []),
            'groupColumns' => config('payroll_reports.group_columns', []),
        ];

        if (in_array($report->options_key, ['historical-data', 'attendance-view'], true)) {
            $viewData['employees'] = $this->employeesForReportOptions($request->user());
        }

        if (in_array($report->options_key, ['payslip', 'bir-1601c'], true)) {
            $viewData['postedBatches'] = $this->batchOptions->postedBatchesForUser($request->user());
        }

        if (in_array($report->options_key, ['bir-2316', 'alphalist'], true)) {
            $viewData['payYears'] = $this->batchOptions->postedPayYearsForUser($request->user());
        }

        return view($optionsConfig['view'], $viewData);
    }

    public function batchEmployees(Request $request): JsonResponse
    {
        PayrollReportsModule::authorize($request->user());

        $validated = $request->validate([
            'payroll_batch_id' => ['sometimes', 'integer', 'exists:trn_payroll_batches,payroll_batch_id'],
            'payroll_batch_ids' => ['sometimes', 'array', 'min:1'],
            'payroll_batch_ids.*' => ['integer', 'exists:trn_payroll_batches,payroll_batch_id'],
        ]);

        $batchIds = collect($validated['payroll_batch_ids'] ?? [])
            ->when(
                collect($validated['payroll_batch_ids'] ?? [])->isEmpty() && isset($validated['payroll_batch_id']),
                fn ($ids) => $ids->push((int) $validated['payroll_batch_id']),
            )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($batchIds === []) {
            return response()->json(['employees' => []]);
        }

        $batches = PayrollBatch::query()
            ->with([
                'details.employee' => fn ($query) => $query
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
            ])
            ->whereIn('payroll_batch_id', $batchIds)
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->get();

        if ($batches->isEmpty()) {
            return response()->json(['employees' => []]);
        }

        $user = $request->user();
        $employees = $batches
            ->flatMap(fn (PayrollBatch $batch) => $batch->details)
            ->filter(function (PayrollBatchDetail $detail) use ($user) {
                if ($detail->employee === null) {
                    return false;
                }

                if ($user->isAdmin()) {
                    return true;
                }

                return ! (bool) ($detail->employee->is_confidential ?? false);
            })
            ->unique('employee_id')
            ->map(fn (PayrollBatchDetail $detail) => [
                'id' => (int) $detail->employee_id,
                'label' => trim(sprintf(
                    '%s — %s',
                    $detail->employee->employee_number ?? '',
                    $detail->employee->full_name ?? '',
                )),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return response()->json(['employees' => $employees]);
    }

    public function yearEmployees(Request $request): JsonResponse
    {
        PayrollReportsModule::authorize($request->user());

        $validated = $request->validate([
            'pay_year' => ['required', 'integer', 'min:1000', 'max:9999'],
        ]);

        $user = $request->user();
        $payYear = (int) $validated['pay_year'];

        $query = Employee::query()
            ->select([
                'tbl_employees.employee_id',
                'tbl_employees.employee_number',
                'tbl_employees.first_name',
                'tbl_employees.middle_name',
                'tbl_employees.last_name',
                'tbl_employees.suffix',
                'tbl_employees.is_confidential',
            ])
            ->whereExists(function ($sub) use ($payYear, $user) {
                $sub->selectRaw('1')
                    ->from('trn_payroll_batch_details as d')
                    ->join('trn_payroll_batches as b', 'b.payroll_batch_id', '=', 'd.payroll_batch_id')
                    ->join('tbl_payroll_calendar as c', 'c.payroll_calendar_id', '=', 'b.payroll_calendar_id')
                    ->whereColumn('d.employee_id', 'tbl_employees.employee_id')
                    ->where('b.payroll_batch_status_id', PayrollBatchStatus::POSTED)
                    ->where('c.pay_year', $payYear)
                    ->where(function ($locked) use ($user) {
                        $locked->whereNull('b.locked_for_id')
                            ->orWhere('b.locked_for_id', $user->id);
                    })
                    ->whereNull('b.deleted_at');
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! $user->isAdmin()) {
            $query->where(function ($q) {
                $q->whereNull('is_confidential')
                    ->orWhere('is_confidential', false);
            });
        }

        $employees = $query
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => (int) $employee->employee_id,
                'label' => trim(sprintf(
                    '%s — %s',
                    $employee->employee_number ?? '',
                    $employee->full_name ?? '',
                )),
            ])
            ->values()
            ->all();

        return response()->json(['employees' => $employees]);
    }

    public function generate(Request $request): View|StreamedResponse|Response
    {
        PayrollReportsModule::authorize($request->user(), 'add');

        $classification = PayrollReportsModule::resolveClassification(
            (string) $request->input('classification', PayrollReportsModule::defaultClassificationCode()),
        );

        $report = PayrollReportsModule::resolveReport((int) $request->input('report_id'), $classification);
        $optionsConfig = PayrollReportsModule::optionsConfig($report->options_key);
        $validated = $request->validate($optionsConfig['rules']);

        $generatorClass = PayrollReportsModule::generatorClass($report->generator_key);
        $generator = App::make($generatorClass);
        $result = $generator->generate($report, $validated, $request->user());

        SysLogService::record(
            action: 'read',
            table: 'payroll_reports',
            description: "Generated {$report->title} report ({$validated['output_format']})",
        );

        $outputFormat = (string) ($validated['output_format'] ?? 'html');

        if ($outputFormat === 'excel') {
            return $generator->downloadExcel($result);
        }

        // PATHS-style official blank PDF + stamp (exact form UI).
        if (
            method_exists($generator, 'downloadOfficialPdf')
            && (($result->meta['uses_official_pdf'] ?? false) === true)
            && in_array($outputFormat, ['html', 'pdf'], true)
        ) {
            return $generator->downloadOfficialPdf($result, inline: $outputFormat === 'html');
        }

        if ($outputFormat === 'pdf') {
            $baseFilename = str($report->title)->slug('_').'_'.now()->format('Ymd_His');

            return ReportPdfDownload::stream($result, $baseFilename);
        }

        return view('payroll.reports.preview', [
            'preview' => [
                'title' => $result->title,
                'headers' => $result->headers,
                'rows' => $result->rows,
                'meta' => $result->meta,
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function employeesForReportOptions(?User $user)
    {
        $query = Employee::query()
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
}
