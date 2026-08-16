<?php

namespace App\Services\Reports;

use App\Models\DeductionType;
use App\Models\EmployeeEmploymentInformation;
use App\Models\Report;
use App\Models\User;
use App\Support\GovernmentIdNumbers;
use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagibigContributionReportService
{
    /** @var array<int, string> */
    private const SECTION_ORDER = ['staff', 'faculty'];

    /** @var array<string, string> */
    private const SECTION_LABELS = [
        'staff' => 'Staff',
        'faculty' => 'Faculty',
    ];

    public function __construct(
        private readonly PayrollContributionBatchSupport $batchSupport,
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
        $sheet->setTitle((string) config('government_contribution_reports.pagibig.excel_sheet_title', 'Pag-ibig'));

        $meta = $result->meta;
        $sections = $meta['sections'] ?? [];
        $grandTotals = $meta['grand_totals'] ?? $this->sumTotals([]);
        $lastColumn = 'J';

        $row = 1;
        foreach ([
            (string) ($meta['company_name'] ?? config('app.name')),
            (string) ($meta['company_address'] ?? ''),
            (string) config('government_contribution_reports.pagibig.title_line_3'),
            'SSS# '.(string) config('government_contribution_reports.pagibig_employer_sss', ''),
            (string) ($meta['period_label'] ?? ''),
        ] as $line) {
            if ($line === '' || $line === 'SSS# ') {
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

        $sheet->setCellValue("A{$headerRow}", 'BIRTHDATE');
        $sheet->setCellValue("C{$headerRow}", 'T I N');
        $sheet->mergeCells("H{$headerRow}:J{$headerRow}");
        $sheet->setCellValue("H{$headerRow}", 'MONTHLY CONTRIBUTION');
        $sheet->setCellValue("D{$subHeaderRow}", 'No.');
        $sheet->setCellValue("E{$subHeaderRow}", 'LAST NAME');
        $sheet->setCellValue("F{$subHeaderRow}", 'FIRST NAME');
        $sheet->setCellValue("G{$subHeaderRow}", 'MIDDLE NAME');
        $sheet->setCellValue("H{$subHeaderRow}", 'EMPLOYEE');
        $sheet->setCellValue("I{$subHeaderRow}", 'EMPLOYER');
        $sheet->setCellValue("J{$subHeaderRow}", 'Total');
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$subHeaderRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $row = $dataStartRow;
        $hasData = false;

        foreach ($sections as $index => $section) {
            $employeeRows = $section['employee_rows'] ?? [];

            if ($employeeRows === []) {
                continue;
            }

            if ($index > 0) {
                $row = $this->writeSectionHeaderRow($sheet, $row, (string) ($section['label'] ?? ''));
            }

            foreach ($employeeRows as $employeeRow) {
                $this->writeEmployeeRow($sheet, $row, $employeeRow);
                $row++;
            }

            $hasData = true;
            $row++;
            $this->writeTotalRow($sheet, $row, 'Sub-total', $section['totals'] ?? $this->sumTotals([]));
            $row += 2;
        }

        if ($hasData) {
            $sheet->getStyle("A{$headerRow}:{$lastColumn}".($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("H{$dataStartRow}:J".($row - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $this->writeTotalRow($sheet, $row, 'GRAND TOTAL', $grandTotals);
        $row += 2;
        $this->writeTotalRow($sheet, $row, 'Net', $grandTotals);
        $row += 2;

        $sheet->setCellValue("A{$row}", 'PREPARED BY:');
        $sheet->setCellValue("E{$row}", 'NOTED BY:');
        $sheet->setCellValue("H{$row}", 'APPROVED BY:');
        $row += 2;
        $sheet->setCellValue("A{$row}", (string) config('government_contribution_reports.pagibig.prepared_by', ''));
        $sheet->setCellValue("E{$row}", (string) config('government_contribution_reports.pagibig.noted_by', ''));
        $sheet->setCellValue("H{$row}", (string) config('government_contribution_reports.pagibig.approved_by', ''));
        $row++;
        $sheet->setCellValue("E{$row}", (string) config('government_contribution_reports.pagibig.noted_by_title', ''));
        $sheet->setCellValue("H{$row}", (string) config('government_contribution_reports.pagibig.approved_by_title', ''));

        for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($lastColumn); $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Pagibig_Contribution_'.now()->format('Ymd_His'),
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
            'details.employee.employmentInformations',
        ]);

        if ($batches->isEmpty()) {
            return $this->emptyDataset();
        }

        $this->batchSupport->assertSamePayMonthAndYear($batches);

        $pagibigTypeId = (int) DeductionType::query()
            ->where('deduction_type_code', 'PIBG')
            ->value('deduction_type_id');

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
                        'section' => $this->resolveSection($employee->user_type),
                        'birthdate' => $this->formatBirthdate($employee->birth_date),
                        'pagibig_number' => GovernmentIdNumbers::normalize((string) ($employee->pagibig_number ?? '')) ?? '',
                        'tin_number' => GovernmentIdNumbers::normalize(
                            (string) ($employee->tin_number ?? ''),
                        ) ?? '',
                        'last_name' => trim((string) $employee->last_name),
                        'first_name' => trim((string) $employee->first_name),
                        'middle_name' => trim((string) $employee->middle_name),
                        'employee_share' => 0.0,
                        'employer_share' => 0.0,
                    ];
                }

                foreach ($detail->deductions as $deduction) {
                    if ((int) $deduction->deduction_type_id !== $pagibigTypeId) {
                        continue;
                    }

                    $byEmployee[$employeeId]['employee_share'] += (float) $deduction->employee_amount;
                    $byEmployee[$employeeId]['employer_share'] += (float) $deduction->employer_amount;
                }
            }
        }

        $groupedRows = collect($byEmployee)
            ->map(function (array $row) {
                $employeeShare = round((float) $row['employee_share'], 2);
                $employerShare = round((float) $row['employer_share'], 2);
                $total = round($employeeShare + $employerShare, 2);

                if ($employeeShare <= 0 && $employerShare <= 0) {
                    return null;
                }

                return [
                    'section' => $row['section'],
                    'birthdate' => $row['birthdate'],
                    'pagibig_number' => $row['pagibig_number'],
                    'tin_number' => $row['tin_number'],
                    'last_name' => $row['last_name'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'employee_share' => $employeeShare,
                    'employer_share' => $employerShare,
                    'total_contribution' => $total,
                    'sort_name' => strtolower(trim($row['last_name'].' '.$row['first_name'].' '.$row['middle_name'])),
                ];
            })
            ->filter()
            ->groupBy('section');

        $sections = [];
        $allEmployeeRows = [];
        $formattedRows = [];

        foreach (self::SECTION_ORDER as $sectionKey) {
            $employeeRows = collect($groupedRows->get($sectionKey, collect()))
                ->sortBy('sort_name', SORT_NATURAL)
                ->values()
                ->map(function (array $row, int $index) {
                    $row['no'] = $index + 1;

                    return $row;
                })
                ->all();

            if ($employeeRows === []) {
                continue;
            }

            $sections[] = [
                'key' => $sectionKey,
                'label' => self::SECTION_LABELS[$sectionKey],
                'employee_rows' => $employeeRows,
                'totals' => $this->sumTotals($employeeRows),
            ];

            foreach ($employeeRows as $row) {
                $allEmployeeRows[] = $row;
                $formattedRows[] = [
                    $row['birthdate'],
                    $row['pagibig_number'],
                    $row['tin_number'],
                    (string) $row['no'],
                    $row['last_name'],
                    $row['first_name'],
                    $row['middle_name'],
                    $this->money($row['employee_share']),
                    $this->money($row['employer_share']),
                    $this->money($row['total_contribution']),
                ];
            }
        }

        $grandTotals = $this->sumTotals($allEmployeeRows);
        $batchMeta = $this->batchSupport->batchMeta($batches);
        $company = $this->batchSupport->companyMeta();

        return [
            'headers' => [
                'Birthdate',
                'Pag-IBIG No.',
                'TIN',
                'No.',
                'Last Name',
                'First Name',
                'Middle Name',
                'Employee',
                'Employer',
                'Total',
            ],
            'rows' => $formattedRows,
            'meta' => array_merge($company, $batchMeta, [
                'layout' => 'pagibig',
                'sections' => $sections,
                'employee_rows' => $allEmployeeRows,
                'totals' => $grandTotals,
                'grand_totals' => $grandTotals,
                'employee_count' => count($allEmployeeRows),
            ]),
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function emptyDataset(): array
    {
        $company = $this->batchSupport->companyMeta();
        $emptyTotals = $this->sumTotals([]);

        return [
            'headers' => ['Message'],
            'rows' => [],
            'meta' => array_merge($company, [
                'layout' => 'pagibig',
                'sections' => [],
                'employee_rows' => [],
                'totals' => $emptyTotals,
                'grand_totals' => $emptyTotals,
                'batch_count' => 0,
                'employee_count' => 0,
                'period_label' => '',
                'pay_year' => 0,
                'calendar_month' => 0,
                'batch_labels' => [],
            ]),
        ];
    }

    private function resolveSection(?string $userType): string
    {
        return $userType === EmployeeEmploymentInformation::TYPE_FACULTY ? 'faculty' : 'staff';
    }

    /**
     * @param  array<int, array<string, mixed>>  $employeeRows
     * @return array{employee_share: float, employer_share: float, total_contribution: float}
     */
    private function sumTotals(array $employeeRows): array
    {
        $employeeShare = round(collect($employeeRows)->sum('employee_share'), 2);
        $employerShare = round(collect($employeeRows)->sum('employer_share'), 2);

        return [
            'employee_share' => $employeeShare,
            'employer_share' => $employerShare,
            'total_contribution' => round($employeeShare + $employerShare, 2),
        ];
    }

    private function writeSectionHeaderRow(Worksheet $sheet, int $row, string $label): int
    {
        $sheet->setCellValue("D{$row}", 'No.');
        $sheet->setCellValue("E{$row}", $label);
        $sheet->setCellValue("H{$row}", 'EMPLOYEE');
        $sheet->setCellValue("I{$row}", 'EMPLOYER');
        $sheet->setCellValue("J{$row}", 'Total');
        $sheet->getStyle("D{$row}:J{$row}")->getFont()->setBold(true);

        return $row + 1;
    }

    /**
     * @param  array<string, mixed>  $employeeRow
     */
    private function writeEmployeeRow(Worksheet $sheet, int $row, array $employeeRow): void
    {
        $sheet->setCellValue("A{$row}", $employeeRow['birthdate']);
        $sheet->setCellValue("B{$row}", $employeeRow['pagibig_number']);
        $sheet->setCellValue("C{$row}", $employeeRow['tin_number']);
        $sheet->setCellValue("D{$row}", $employeeRow['no']);
        $sheet->setCellValue("E{$row}", $employeeRow['last_name']);
        $sheet->setCellValue("F{$row}", $employeeRow['first_name']);
        $sheet->setCellValue("G{$row}", $employeeRow['middle_name']);
        $this->setMoneyCell($sheet, "H{$row}", $employeeRow['employee_share']);
        $this->setMoneyCell($sheet, "I{$row}", $employeeRow['employer_share']);
        $this->setMoneyCell($sheet, "J{$row}", $employeeRow['total_contribution']);
    }

    /**
     * @param  array{employee_share: float, employer_share: float, total_contribution: float}  $totals
     */
    private function writeTotalRow(Worksheet $sheet, int $row, string $label, array $totals): void
    {
        $sheet->setCellValue("E{$row}", $label);
        $this->setMoneyCell($sheet, "H{$row}", $totals['employee_share']);
        $this->setMoneyCell($sheet, "I{$row}", $totals['employer_share']);
        $this->setMoneyCell($sheet, "J{$row}", $totals['total_contribution']);
    }

    private function formatBirthdate(mixed $birthDate): string
    {
        if ($birthDate === null || $birthDate === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($birthDate)->format('m/d/y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function setMoneyCell(Worksheet $sheet, string $cell, float $amount): void
    {
        $sheet->setCellValue($cell, $amount);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
