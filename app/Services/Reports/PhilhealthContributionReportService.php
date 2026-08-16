<?php

namespace App\Services\Reports;

use App\Models\DeductionType;
use App\Models\Report;
use App\Models\User;
use App\Services\GovernmentDeductionPayrollService;
use App\Support\GovernmentIdNumbers;
use App\Support\PhilhealthDeductionTypes;
use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhilhealthContributionReportService
{
    public function __construct(
        private readonly PayrollContributionBatchSupport $batchSupport,
        private readonly GovernmentDeductionPayrollService $governmentPayroll,
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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle((string) config('government_contribution_reports.philhealth.excel_sheet_title', 'Philhealth'));

        $meta = $result->meta;
        $employeeRows = $meta['employee_rows'] ?? [];
        $totals = $meta['totals'] ?? [];
        $lastColumn = 'K';

        $row = 1;
        foreach ([
            (string) ($meta['company_name'] ?? config('app.name')),
            (string) ($meta['company_address'] ?? ''),
            (string) config('government_contribution_reports.philhealth.title_line_3'),
            (string) config('government_contribution_reports.philhealth.title_line_4'),
            (string) ($meta['period_label'] ?? ''),
        ] as $line) {
            if ($line === '') {
                continue;
            }

            $sheet->setCellValue("A{$row}", $line);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $headerRow = 7;
        $subHeaderRow = 8;
        $dataStartRow = 9;

        $sheet->setCellValue("A{$headerRow}", 'No.');
        $sheet->mergeCells("B{$headerRow}:D{$headerRow}");
        $sheet->setCellValue("B{$headerRow}", 'STAFF');
        $sheet->mergeCells("E{$headerRow}:F{$headerRow}");
        $sheet->setCellValue("E{$headerRow}", 'PHIL. HEALTH INSURANCE');
        $sheet->mergeCells("G{$headerRow}:H{$headerRow}");
        $sheet->setCellValue("G{$headerRow}", '');
        $sheet->setCellValue("I{$headerRow}", 'Total');
        $sheet->setCellValue("J{$headerRow}", '');
        $sheet->setCellValue("K{$headerRow}", '');

        $sheet->setCellValue("E{$subHeaderRow}", 'EMPLOYEE');
        $sheet->setCellValue("F{$subHeaderRow}", 'EMPLOYER');
        $sheet->setCellValue("G{$subHeaderRow}", 'EMPLOYEE');
        $sheet->setCellValue("H{$subHeaderRow}", 'EMPLOYER');
        $sheet->setCellValue("I{$subHeaderRow}", 'Contribution');
        $sheet->setCellValue("J{$subHeaderRow}", 'Gross');

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $row = $dataStartRow;

        foreach ($employeeRows as $employeeRow) {
            $sheet->setCellValue("A{$row}", $employeeRow['no']);
            $sheet->setCellValue("B{$row}", $employeeRow['last_name']);
            $sheet->setCellValue("C{$row}", $employeeRow['first_name']);
            $sheet->setCellValue("D{$row}", $employeeRow['middle_name']);
            $this->setMoneyCell($sheet, "E{$row}", $employeeRow['employee_share']);
            $this->setMoneyCell($sheet, "F{$row}", $employeeRow['employer_share']);
            $sheet->setCellValue("G{$row}", '-');
            $sheet->setCellValue("H{$row}", '-');
            $this->setMoneyCell($sheet, "I{$row}", $employeeRow['total_contribution']);
            $this->setMoneyCell($sheet, "J{$row}", $employeeRow['gross']);
            $sheet->setCellValue("K{$row}", $employeeRow['philhealth_number']);
            $row++;
        }

        if ($employeeRows !== []) {
            $sheet->getStyle("A{$headerRow}:{$lastColumn}".($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("E{$dataStartRow}:J".($row - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $row += 2;
        $this->writeTotalRow($sheet, $row, 'SUB-TOTAL', $totals);
        $row += 4;
        $this->writeTotalRow($sheet, $row, 'GRAND TOTAL', $totals);
        $row += 2;
        $this->writeTotalRow($sheet, $row, 'Net', $totals, labelColumn: 'C');
        $row += 3;

        $sheet->setCellValue("A{$row}", 'PREPARED BY:');
        $sheet->setCellValue("C{$row}", 'NOTED BY:');
        $sheet->setCellValue("F{$row}", 'APPROVED BY:');
        $row += 2;
        $sheet->setCellValue("A{$row}", (string) config('government_contribution_reports.philhealth.prepared_by', ''));
        $sheet->setCellValue("C{$row}", (string) config('government_contribution_reports.philhealth.noted_by', ''));
        $sheet->setCellValue("F{$row}", (string) config('government_contribution_reports.philhealth.approved_by', ''));
        $row++;
        $sheet->setCellValue("C{$row}", (string) config('government_contribution_reports.philhealth.noted_by_title', ''));
        $sheet->setCellValue("F{$row}", (string) config('government_contribution_reports.philhealth.approved_by_title', ''));

        for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Philhealth_Contribution_'.now()->format('Ymd_His'),
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
            ->unique()
            ->values()
            ->all();

        $batches = $this->batchSupport->loadProcessedBatches($batchIds);

        if ($batches->isEmpty()) {
            return $this->emptyDataset();
        }

        $this->batchSupport->assertSamePayMonthAndYear($batches);

        $philTypeIds = DeductionType::query()
            ->whereIn('deduction_type_code', PhilhealthDeductionTypes::EXCLUSIVE_CODES)
            ->pluck('deduction_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $byEmployee = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! $this->batchSupport->detailIsVisible($detail, $user)) {
                    continue;
                }

                $employee = $detail->employee;

                if ($employee === null) {
                    continue;
                }

                $employeeId = (int) $detail->employee_id;

                if (! isset($byEmployee[$employeeId])) {
                    $byEmployee[$employeeId] = [
                        'employee_id' => $employeeId,
                        'last_name' => trim((string) $employee->last_name),
                        'first_name' => trim((string) $employee->first_name),
                        'middle_name' => trim((string) $employee->middle_name),
                        'philhealth_number' => GovernmentIdNumbers::normalize(
                            (string) ($employee->philhealth_number ?? ''),
                        ) ?? '',
                        'employee_share' => 0.0,
                        'employer_share' => 0.0,
                        'gross' => 0.0,
                    ];
                }

                foreach ($detail->deductions as $deduction) {
                    if (! in_array((int) $deduction->deduction_type_id, $philTypeIds, true)) {
                        continue;
                    }

                    $byEmployee[$employeeId]['employee_share'] += (float) $deduction->employee_amount;
                    $byEmployee[$employeeId]['employer_share'] += (float) $deduction->employer_amount;
                }

                $byEmployee[$employeeId]['gross'] += $this->governmentPayroll->philhealthContributionBase($detail, $batch);
            }
        }

        $employeeRows = collect($byEmployee)
            ->map(function (array $row) {
                $employeeShare = round((float) $row['employee_share'], 2);
                $employerShare = round((float) $row['employer_share'], 2);
                $total = round($employeeShare + $employerShare, 2);
                $gross = round((float) $row['gross'], 2);

                if ($employeeShare <= 0 && $employerShare <= 0) {
                    return null;
                }

                return [
                    'last_name' => $row['last_name'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'employee_share' => $employeeShare,
                    'employer_share' => $employerShare,
                    'total_contribution' => $total,
                    'gross' => $gross > 0 ? $gross : null,
                    'philhealth_number' => $row['philhealth_number'],
                    'sort_name' => strtolower(trim($row['last_name'].' '.$row['first_name'].' '.$row['middle_name'])),
                ];
            })
            ->filter()
            ->sortBy('sort_name', SORT_NATURAL)
            ->values()
            ->map(function (array $row, int $index) {
                $row['no'] = $index + 1;

                return $row;
            })
            ->all();

        $totals = $this->sumTotals($employeeRows);
        $batchMeta = $this->batchSupport->batchMeta($batches);
        $company = $this->batchSupport->companyMeta();

        $formattedRows = array_map(fn (array $row) => [
            (string) $row['no'],
            $row['last_name'],
            $row['first_name'],
            $row['middle_name'],
            $this->money($row['employee_share']),
            $this->money($row['employer_share']),
            '-',
            '-',
            $this->money($row['total_contribution']),
            $row['gross'] !== null ? $this->money((float) $row['gross']) : '',
            $row['philhealth_number'],
        ], $employeeRows);

        return [
            'headers' => [
                'No.',
                'Last Name',
                'First Name',
                'Middle Name',
                'Employee Share',
                'Employer Share',
                '',
                '',
                'Total Contribution',
                'Gross',
                'PhilHealth No.',
            ],
            'rows' => $formattedRows,
            'meta' => array_merge($company, $batchMeta, [
                'layout' => 'philhealth',
                'employee_rows' => $employeeRows,
                'totals' => $totals,
                'employee_count' => count($employeeRows),
            ]),
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function emptyDataset(): array
    {
        $company = $this->batchSupport->companyMeta();

        return [
            'headers' => ['Message'],
            'rows' => [],
            'meta' => array_merge($company, [
                'layout' => 'philhealth',
                'employee_rows' => [],
                'totals' => $this->sumTotals([]),
                'batch_count' => 0,
                'employee_count' => 0,
                'period_label' => '',
                'pay_year' => 0,
                'calendar_month' => 0,
                'batch_labels' => [],
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employeeRows
     * @return array{employee_share: float, employer_share: float, total_contribution: float, gross: float}
     */
    private function sumTotals(array $employeeRows): array
    {
        $employeeShare = round(collect($employeeRows)->sum('employee_share'), 2);
        $employerShare = round(collect($employeeRows)->sum('employer_share'), 2);

        return [
            'employee_share' => $employeeShare,
            'employer_share' => $employerShare,
            'total_contribution' => round($employeeShare + $employerShare, 2),
            'gross' => round(collect($employeeRows)->sum(fn ($row) => (float) ($row['gross'] ?? 0)), 2),
        ];
    }

    /**
     * @param  array{employee_share: float, employer_share: float, total_contribution: float, gross: float}  $totals
     */
    private function writeTotalRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        string $label,
        array $totals,
        string $labelColumn = 'B',
    ): void {
        $sheet->setCellValue("{$labelColumn}{$row}", $label);
        $this->setMoneyCell($sheet, "E{$row}", $totals['employee_share']);
        $this->setMoneyCell($sheet, "F{$row}", $totals['employer_share']);
        $sheet->setCellValue("G{$row}", '0');
        $sheet->setCellValue("H{$row}", '0');
        $this->setMoneyCell($sheet, "I{$row}", $totals['total_contribution']);
        $this->setMoneyCell($sheet, "J{$row}", $totals['gross']);
    }

    private function setMoneyCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, float $amount): void
    {
        if ($amount == 0.0) {
            $sheet->setCellValue($cell, 0);

            return;
        }

        $sheet->setCellValue($cell, $amount);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
