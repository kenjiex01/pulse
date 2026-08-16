<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\PayType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\Report;
use App\Models\User;
use App\Services\EmployeeSalaryResolverService;
use App\Support\BirTaxWithheldClassifier;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BirTaxWithheldReportService
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
        private readonly PayrollContributionBatchSupport $batchSupport,
        private readonly EmployeeSalaryResolverService $salaryResolver,
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
        $sheet->setTitle('tax');

        $meta = $result->meta;
        $employeeRows = $meta['employee_rows'] ?? [];
        $lastColumn = 'I';

        $row = 1;
        foreach ([
            (string) ($meta['company_name'] ?? config('app.name')),
            (string) ($meta['company_address'] ?? ''),
            "EMPLOYEES' TAX WITHHELD",
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

        $row++;
        $headerRow = $row;
        $subHeaderRow = $row + 1;
        $dataStartRow = $row + 2;

        $sheet->setCellValue("A{$headerRow}", '');
        $sheet->setCellValue("B{$headerRow}", '');
        $sheet->setCellValue("C{$headerRow}", 'Non Taxable');
        $sheet->setCellValue("D{$headerRow}", 'MWE');
        $sheet->setCellValue("E{$headerRow}", 'TAXABLE Inc.');
        $sheet->setCellValue("F{$headerRow}", 'TAXABLE Inc.');
        $sheet->setCellValue("G{$headerRow}", 'TAX');
        $sheet->setCellValue("H{$headerRow}", 'De minimis');
        $sheet->setCellValue("I{$headerRow}", 'Gross');

        $sheet->setCellValue("C{$subHeaderRow}", 'Overtime');
        $sheet->setCellValue("D{$subHeaderRow}", 'Income');
        $sheet->setCellValue("E{$subHeaderRow}", 'No W/T');
        $sheet->setCellValue("F{$subHeaderRow}", 'with W/T');
        $sheet->setCellValue("G{$subHeaderRow}", 'WITHHELD');
        $sheet->setCellValue("H{$subHeaderRow}", 'Benefit');
        $sheet->setCellValue("I{$subHeaderRow}", 'Income');

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);

        $excelRow = $dataStartRow;
        $line = 1;

        foreach ($employeeRows as $employeeRow) {
            $sheet->setCellValue("A{$excelRow}", $line++);
            $sheet->setCellValue("B{$excelRow}", $employeeRow['employee_name']);
            $sheet->setCellValue("C{$excelRow}", $employeeRow['non_taxable_overtime'] ?: null);
            $sheet->setCellValue("D{$excelRow}", $employeeRow['mwe_income'] ?: null);
            $sheet->setCellValue("E{$excelRow}", $employeeRow['taxable_no_wt'] ?: null);
            $sheet->setCellValue("F{$excelRow}", $employeeRow['taxable_with_wt'] ?: null);
            $sheet->setCellValue("G{$excelRow}", $employeeRow['tax_withheld'] ?: null);
            $sheet->setCellValue("H{$excelRow}", $employeeRow['deminimis_benefit'] ?: null);
            $sheet->setCellValue("I{$excelRow}", $employeeRow['gross_income'] ?: null);
            $excelRow++;
        }

        if ($employeeRows !== []) {
            $lastDataRow = $excelRow - 1;
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastDataRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("C{$dataStartRow}:{$lastColumn}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:{$lastColumn}{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'BIR_Tax_Withheld_'.now()->format('Ymd_His'),
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

        $batches = $this->batchSupport->loadProcessedBatches($batchIds, [
            'details.employee',
            'details.incomes.incomeType',
            'details.deductions.deductionType',
            'payrollCalendar.payType',
        ]);

        if ($batches->isEmpty()) {
            return [
                'headers' => $this->headers(),
                'rows' => [],
                'meta' => [
                    'layout' => 'bir_tax',
                    'batch_count' => 0,
                    'employee_count' => 0,
                    'company_name' => config('app.name'),
                    'company_address' => '',
                    'period_label' => '',
                    'batch_labels' => [],
                    'employee_rows' => [],
                    'totals' => $this->emptyTotals(),
                ],
            ];
        }

        $this->batchSupport->assertSamePayMonthAndYear($batches);
        $batchMeta = $this->batchSupport->batchMeta($batches);
        $companyMeta = $this->batchSupport->companyMeta();

        $employeeRows = $this->buildEmployeeRows($batches, $user);
        $totals = $this->sumTotals($employeeRows);

        $formattedRows = [];
        $line = 1;

        foreach ($employeeRows as $row) {
            $formattedRows[] = [
                (string) $line++,
                $row['employee_name'],
                $this->moneyOrBlank($row['non_taxable_overtime']),
                $this->moneyOrBlank($row['mwe_income']),
                $this->moneyOrBlank($row['taxable_no_wt']),
                $this->moneyOrBlank($row['taxable_with_wt']),
                $this->moneyOrBlank($row['tax_withheld']),
                $this->moneyOrBlank($row['deminimis_benefit']),
                $this->moneyOrBlank($row['gross_income']),
            ];
        }

        return [
            'headers' => $this->headers(),
            'rows' => $formattedRows,
            'meta' => array_merge($batchMeta, $companyMeta, [
                'layout' => 'bir_tax',
                'title_line' => "EMPLOYEES' TAX WITHHELD",
                'employee_count' => count($employeeRows),
                'employee_rows' => $employeeRows,
                'totals' => $totals,
                'subheaders' => [
                    '',
                    '',
                    'Overtime',
                    'Income',
                    'No W/T',
                    'with W/T',
                    'WITHHELD',
                    'Benefit',
                    'Income',
                ],
            ]),
        ];
    }

    /**
     * @param  Collection<int, PayrollBatch>  $batches
     * @return array<int, array<string, mixed>>
     */
    private function buildEmployeeRows(Collection $batches, User $user): array
    {
        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = [];

        foreach ($batches as $batch) {
            $calendar = $batch->payrollCalendar;
            $payTypeId = (int) ($calendar?->pay_type_id ?? 0);

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
                        'employee_name' => $this->formatEmployeeName($employee),
                        'pay_type_id' => $payTypeId,
                        'gross_income' => 0.0,
                        'overtime_amount' => 0.0,
                        'deminimis_benefit' => 0.0,
                        'tax_withheld' => 0.0,
                        'is_above_minimum_wage_earner' => false,
                    ];
                }

                $amounts = $this->detailIncomeAmounts($detail);
                $byEmployee[$employeeId]['gross_income'] += $amounts['gross'];
                $byEmployee[$employeeId]['overtime_amount'] += $amounts['overtime'];
                $byEmployee[$employeeId]['deminimis_benefit'] += $amounts['deminimis'];
                $byEmployee[$employeeId]['tax_withheld'] += $this->detailTaxWithheld($detail);

                if ($calendar?->dt_from && $calendar?->dt_to && $payTypeId > 0) {
                    $salaries = $this->salaryResolver->salariesForPeriod(
                        $employeeId,
                        $payTypeId,
                        $calendar->dt_from,
                        $calendar->dt_to,
                    );

                    if ($salaries->contains(fn ($salary) => (bool) $salary->is_above_minimum_wage_earner)) {
                        $byEmployee[$employeeId]['is_above_minimum_wage_earner'] = true;
                    }

                    if ($byEmployee[$employeeId]['deminimis_benefit'] <= 0) {
                        $byEmployee[$employeeId]['deminimis_benefit'] += $this->salaryDeminimisAmount($salaries);
                    }
                }
            }
        }

        return collect($byEmployee)
            ->map(function (array $row) {
                $classified = BirTaxWithheldClassifier::classify([
                    'gross_income' => round((float) $row['gross_income'], 2),
                    'overtime_amount' => round((float) $row['overtime_amount'], 2),
                    'deminimis_benefit' => round((float) $row['deminimis_benefit'], 2),
                    'is_above_minimum_wage_earner' => (bool) $row['is_above_minimum_wage_earner'],
                    'tax_withheld' => round((float) $row['tax_withheld'], 2),
                    'pay_type_id' => PayType::MONTHLY,
                    // Monthly tax worksheet: compare summed month amounts to TRAIN monthly exempt.
                    'threshold' => BirTaxWithheldClassifier::taxableThresholdForPayType(PayType::MONTHLY),
                ]);

                return array_merge([
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['employee_name'],
                    'is_above_minimum_wage_earner' => (bool) $row['is_above_minimum_wage_earner'],
                ], $classified);
            })
            ->filter(fn (array $row) => (float) $row['gross_income'] > 0
                || (float) $row['tax_withheld'] > 0
                || (float) $row['deminimis_benefit'] > 0)
            ->sortBy('employee_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array{gross: float, overtime: float, deminimis: float}
     */
    private function detailIncomeAmounts(PayrollBatchDetail $detail): array
    {
        $gross = 0.0;
        $overtime = 0.0;
        $deminimis = 0.0;

        foreach ($detail->incomes as $income) {
            $amount = (float) $income->taxable + (float) $income->non_taxable;
            $gross += $amount;

            $code = strtoupper((string) ($income->incomeType?->income_type_code ?? ''));

            if ($code === 'OVRT') {
                $overtime += $amount;
            }

            if (BirTaxWithheldClassifier::isDeminimisIncomeType($income->incomeType)) {
                $deminimis += $amount;
            }
        }

        return [
            'gross' => $gross,
            'overtime' => $overtime,
            'deminimis' => $deminimis,
        ];
    }

    private function detailTaxWithheld(PayrollBatchDetail $detail): float
    {
        $total = 0.0;

        foreach ($detail->deductions as $deduction) {
            $code = strtoupper((string) ($deduction->deductionType?->deduction_type_code ?? ''));

            if ($code === 'WHTX') {
                $total += (float) $deduction->employee_amount;
            }
        }

        return $total;
    }

    /**
     * @param  Collection<int, \App\Models\EmployeeSalary>  $salaries
     */
    private function salaryDeminimisAmount(Collection $salaries): float
    {
        $total = 0.0;

        foreach ($salaries as $salary) {
            foreach ($salary->incomes as $income) {
                if (BirTaxWithheldClassifier::isDeminimisIncomeType($income->incomeType)) {
                    $total += (float) $income->taxable + (float) $income->non_taxable;
                }
            }
        }

        return $total;
    }

    private function formatEmployeeName(Employee $employee): string
    {
        $last = trim((string) $employee->last_name);
        $first = trim((string) $employee->first_name);
        $middle = trim((string) $employee->middle_name);

        $name = $last;

        if ($first !== '') {
            $name .= ($name !== '' ? ', ' : '').$first;
        }

        if ($middle !== '') {
            $name .= ($name !== '' ? ' ' : '').$middle;
        }

        return $name !== '' ? $name : (string) ($employee->full_name ?? '—');
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'No.',
            'Employee Name',
            'Non Taxable Overtime',
            'MWE Income',
            'TAXABLE Inc. No W/T',
            'TAXABLE Inc. with W/T',
            'TAX WITHHELD',
            'De minimis Benefit',
            'Gross Income',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function sumTotals(array $rows): array
    {
        $totals = $this->emptyTotals();

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = round($totals[$key] + (float) ($row[$key] ?? 0), 2);
            }
        }

        return $totals;
    }

    /**
     * @return array<string, float>
     */
    private function emptyTotals(): array
    {
        return [
            'non_taxable_overtime' => 0.0,
            'mwe_income' => 0.0,
            'taxable_no_wt' => 0.0,
            'taxable_with_wt' => 0.0,
            'tax_withheld' => 0.0,
            'deminimis_benefit' => 0.0,
            'gross_income' => 0.0,
        ];
    }

    private function moneyOrBlank(float $amount): string
    {
        return $amount > 0 ? number_format($amount, 2) : '';
    }
}
