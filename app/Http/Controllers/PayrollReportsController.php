<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\Reports\ReportBatchOptionsService;
use App\Services\SysLogService;
use App\Support\PayrollReportsModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportsController extends Controller
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
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
        $selectedReport = $selectedReportId > 0
            ? PayrollReportsModule::resolveReport($selectedReportId, $classification)
            : null;

        SysLogService::record(
            action: 'read',
            table: 'payroll_reports',
            description: 'Opened payroll reports page',
        );

        return view('payroll.reports.index', [
            'classifications' => PayrollReportsModule::activeClassifications(),
            'classification' => $classification,
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'processedBatches' => $this->batchOptions->processedBatchesForUser($request->user()),
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

        return view($optionsConfig['view'], [
            'report' => $report,
            'processedBatches' => $this->batchOptions->processedBatchesForUser($request->user()),
            'detailColumns' => config('payroll_reports.detail_columns', []),
            'sortColumns' => config('payroll_reports.sort_columns', []),
            'groupColumns' => config('payroll_reports.group_columns', []),
        ]);
    }

    public function generate(Request $request): View|StreamedResponse
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

        if (($validated['output_format'] ?? 'html') === 'excel') {
            return $generator->downloadExcel($result);
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
}
