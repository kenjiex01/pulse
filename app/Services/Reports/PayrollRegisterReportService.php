<?php

namespace App\Services\Reports;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchStatus;
use App\Models\Report;
use App\Models\User;
use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRegisterReportService
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
        private readonly PayrollRegisterRowBuilder $registerRowBuilder,
        private readonly PayrollRegisterExcelExporter $excelExporter,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Report $report, array $options, User $user): ReportGenerationResult
    {
        $dataset = $this->buildDataset($options, $user);

        return new ReportGenerationResult(
            title: $report->title,
            headers: $dataset['headers'],
            rows: $dataset['rows'],
            meta: $dataset['meta'],
        );
    }

    public function downloadExcel(ReportGenerationResult $result): StreamedResponse
    {
        if (($result->meta['layout'] ?? null) === 'icct_per_hour') {
            return $this->excelExporter->stream(
                $result->meta['register_rows'] ?? [],
                $result->meta,
            );
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Register');

        $sheet->fromArray([$result->title], null, 'A1');
        $sheet->fromArray($result->headers, null, 'A3');
        $sheet->fromArray($result->rows, null, 'A4');

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Payroll_Register_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $batchIds = collect($options['payroll_batch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $sortBy = (string) ($options['sort_by'] ?? 'employee_name');
        $employeeType = strtolower((string) ($options['employee_type'] ?? 'staff'));
        if (! in_array($employeeType, ['staff', 'admin'], true)) {
            $employeeType = 'staff';
        }

        $batches = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details.employee.campus.parentCampus.parentCampus',
                'details.employee.campusAssignments.campus.parentCampus.parentCampus',
                'details.employee.employmentInformations.salary',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
                'details.leaves.leaveType',
            ])
            ->whereIn('payroll_batch_id', $batchIds)
            ->whereIn('payroll_batch_status_id', [
                PayrollBatchStatus::PROCESSED,
                PayrollBatchStatus::POSTED,
            ])
            ->get();

        if ($batches->isEmpty()) {
            return [
                'headers' => ['Message'],
                'rows' => [['No processed or posted payroll batches were found for the selected filters.']],
                'meta' => ['batch_count' => 0, 'employee_count' => 0],
            ];
        }

        $layoutConfig = 'payroll_register_staff_layout';
        $layout = config($layoutConfig);
        $subtitle = $employeeType === 'admin'
            ? (string) ($layout['subtitle_admin'] ?? 'PAYROLL REGISTER - ADMIN')
            : (string) ($layout['subtitle_staff'] ?? 'PAYROLL REGISTER - STAFF');

        $registerRows = $this->registerRowBuilder->buildForBatches($batches, $user, $employeeType);
        $registerRows = $this->registerRowBuilder->sortRegisterRows($registerRows, $sortBy);
        $table = $this->registerRowBuilder->buildLayoutTable($registerRows, $layoutConfig);

        return [
            'headers' => $table['headers'],
            'rows' => $table['rows'],
            'meta' => [
                'layout' => 'icct_per_hour',
                'register_layout' => $employeeType,
                'layout_config' => $layoutConfig,
                'sheet_group' => 'campus',
                'subheaders' => $table['subheaders'],
                'highlight_indices' => $table['highlight_indices'],
                'register_rows' => $registerRows,
                'company_name' => $layout['company_name'] ?? config('app.name'),
                'subtitle' => $subtitle,
                'period_label' => $this->registerRowBuilder->periodTitleForBatches($batches),
                'sheet_title' => $this->registerRowBuilder->sheetTitleForBatches($batches),
                'employee_type' => $employeeType,
                'batch_count' => $batches->count(),
                'employee_count' => count($table['rows']),
                'batch_labels' => $batches->map(fn (PayrollBatch $batch) => $this->batchOptions->batchLabel($batch))->values()->all(),
            ],
        ];
    }
}
