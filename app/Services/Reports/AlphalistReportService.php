<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use App\Support\AlphalistColumnMapper;
use App\Support\BirFormSettings;
use App\Support\GovernmentIdNumbers;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PATHS-style BIR Alphalist (Schedules 7.1 / 7.3 / 7.4 / 7.5) — Excel only.
 *
 * Amounts reuse Bir2316 / income-type mapping via AlphalistColumnMapper.
 */
class AlphalistReportService
{
    public function __construct(
        private readonly BirFormEmployeeSelection $selection,
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
        $spreadsheet->removeSheetByIndex(0);

        $allSchedules = [
            '7.1' => (array) ($result->meta['schedule_71'] ?? []),
            '7.3' => (array) ($result->meta['schedule_73'] ?? []),
            '7.4' => (array) ($result->meta['schedule_74'] ?? []),
            '7.5' => (array) ($result->meta['schedule_75'] ?? []),
        ];

        $selected = collect($result->meta['selected_schedules'] ?? array_keys($allSchedules))
            ->map(fn ($code) => (string) $code)
            ->filter(fn (string $code) => array_key_exists($code, $allSchedules))
            ->unique()
            ->values()
            ->all();

        if ($selected === []) {
            $selected = array_keys($allSchedules);
        }

        $index = 0;
        foreach ($selected as $code) {
            $rows = $allSchedules[$code];
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle('Schedule '.$code);
            if (in_array($code, ['7.1', '7.3'], true)) {
                $this->writeStandardSchedule($sheet, $code, $rows, $result->meta);
            } elseif ($code === '7.4') {
                $this->writeSchedule74($sheet, $rows, $result->meta);
            } else {
                $this->writeSchedule75($sheet, $rows, $result->meta);
            }
            $index++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $year = (string) ($result->meta['pay_year'] ?? now()->format('Y'));
        $suffix = count($selected) === 1
            ? '_Sched'.str_replace('.', '', $selected[0])
            : '';

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Alphalist_'.$year.$suffix.'_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $resolved = $this->selection->resolveAllForYear($options, $user);
        $payYear = (int) $resolved['pay_year'];
        $yearStart = Carbon::create($payYear, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($payYear, 12, 31)->startOfDay();

        $selectedSchedules = collect($options['schedules'] ?? ['7.1', '7.3', '7.4', '7.5'])
            ->map(fn ($code) => (string) $code)
            ->filter(fn (string $code) => in_array($code, ['7.1', '7.3', '7.4', '7.5'], true))
            ->unique()
            ->values()
            ->all();

        if ($selectedSchedules === []) {
            $selectedSchedules = ['7.1', '7.3', '7.4', '7.5'];
        }

        $bir = BirFormSettings::all();
        $companyTin = (string) $bir['company_tin'];

        $schedule71 = [];
        $schedule73 = [];
        $schedule74 = [];
        $schedule75 = [];

        foreach ($resolved['lines'] as $line) {
            $amounts = AlphalistColumnMapper::fromLine([
                ...$line,
                'tax_status' => (string) ($line['tax_status'] ?? ''),
            ]);

            $from = $this->employmentFromDisplay($line, $yearStart);
            $to = $this->employmentToDisplay($line, $yearEnd, $payYear);

            $row = [
                'tin' => (string) ($line['tin'] ?? ''),
                'tin_formatted' => (string) (($line['tin_formatted'] ?? '') !== ''
                    ? $line['tin_formatted']
                    : GovernmentIdNumbers::format($line['tin'] ?? '', GovernmentIdNumbers::TYPE_TIN)),
                'last_name' => (string) ($line['last_name'] ?? ''),
                'first_name' => (string) ($line['first_name'] ?? ''),
                'middle_name' => (string) ($line['middle_name'] ?? ''),
                'employee_name' => (string) ($line['employee_name'] ?? ''),
                'date_from' => $from,
                'date_to' => $to,
                'amounts' => $amounts,
                'schedule' => $this->classifySchedule($line, $amounts, $yearStart, $yearEnd),
            ];

            match ($row['schedule']) {
                '7.5' => $schedule75[] = $row,
                '7.1' => $schedule71[] = $row,
                '7.4' => $schedule74[] = $row,
                default => $schedule73[] = $row,
            };
        }

        $summaryMap = [
            '7.1' => ['Schedule 7.1 (Terminated before Dec 31)', (string) count($schedule71)],
            '7.3' => ['Schedule 7.3 (Active, no previous employer)', (string) count($schedule73)],
            '7.4' => ['Schedule 7.4 (With previous employer / mid-year hire)', (string) count($schedule74)],
            '7.5' => ['Schedule 7.5 (Minimum wage earners)', (string) count($schedule75)],
        ];
        $summaryRows = array_values(array_map(
            fn (string $code) => $summaryMap[$code],
            $selectedSchedules,
        ));

        return [
            'headers' => ['Schedule', 'Employee Count'],
            'rows' => $summaryRows,
            'meta' => [
                'layout' => 'alphalist',
                'excel_only_preferred' => true,
                'pay_year' => $payYear,
                'period_label' => $resolved['period_label'],
                'batch_label' => $resolved['batch_label'],
                'employee_count' => count($resolved['lines']),
                'selected_schedules' => $selectedSchedules,
                'employer' => [
                    'name' => (string) $bir['company_name'],
                    'tin' => $companyTin,
                    'tin_formatted' => GovernmentIdNumbers::format($companyTin, GovernmentIdNumbers::TYPE_TIN),
                    'address' => (string) $bir['company_address'],
                    'rdo_code' => (string) $bir['company_rdo_code'],
                ],
                'smw_rate_per_day' => (float) $bir['smw_rate_per_day'],
                'smw_rate_per_month' => (float) $bir['smw_rate_per_month'],
                'day_factor' => (float) ($options['day_factor'] ?? 313),
                'schedule_71' => $schedule71,
                'schedule_73' => $schedule73,
                'schedule_74' => $schedule74,
                'schedule_75' => $schedule75,
                'disclaimer' => 'Alphalist schedules '.implode(' / ', $selectedSchedules).'. Amounts are YTD from posted payroll batches in the selected year (BIR 2316 income mapping).',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $amounts
     */
    private function classifySchedule(array $line, array $amounts, Carbon $yearStart, Carbon $yearEnd): string
    {
        if ((bool) ($amounts['is_mwe'] ?? false) || ! (bool) ($line['is_above_minimum_wage_earner'] ?? false)) {
            // Prefer explicit MWE flag from mapper; also treat salary not-above-MWE as 7.5
            // when tax withheld is zero (same intent as PATHS is_deduct_withholding_tax IS NULL).
            if ((bool) ($amounts['is_mwe'] ?? false)) {
                return '7.5';
            }

            if (! (bool) ($line['is_above_minimum_wage_earner'] ?? false)
                && (float) ($line['tax_withheld'] ?? 0) <= 0) {
                return '7.5';
            }
        }

        $status = strtolower((string) ($line['employment_status'] ?? ''));
        $hasOpen = (bool) ($line['has_open_salary'] ?? false);
        $employmentTo = trim((string) ($line['employment_to'] ?? ''));
        $terminated = $status === Employee::STATUS_INACTIVE
            || (! $hasOpen && $employmentTo !== '' && Carbon::parse($employmentTo)->lt($yearEnd));

        if ($terminated) {
            return '7.1';
        }

        $hireDate = trim((string) ($line['hire_date'] ?? ''));
        if ($hireDate !== '' && Carbon::parse($hireDate)->gt($yearStart)) {
            return '7.4';
        }

        return '7.3';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function employmentFromDisplay(array $line, Carbon $yearStart): string
    {
        $raw = trim((string) ($line['employment_from'] ?? $line['hire_date'] ?? ''));
        if ($raw === '') {
            return $yearStart->format('m/d/Y');
        }

        $date = Carbon::parse($raw);

        return ($date->lt($yearStart) ? $yearStart : $date)->format('m/d/Y');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function employmentToDisplay(array $line, Carbon $yearEnd, int $payYear): string
    {
        $raw = trim((string) ($line['employment_to'] ?? ''));
        if ($raw === '') {
            return $yearEnd->format('m/d/Y');
        }

        $date = Carbon::parse($raw);
        if ($date->year > $payYear) {
            return $yearEnd->format('m/d/Y');
        }

        return $date->format('m/d/Y');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function writeStandardSchedule(Worksheet $sheet, string $code, array $rows, array $meta): void
    {
        $titles = [
            '7.1' => 'ALPHALIST OF EMPLOYEES TERMINATED BEFORE DECEMBER 31 (Reported Under BIR Form No. 2316)',
            '7.3' => 'ALPHALIST OF EMPLOYEES AS OF DECEMBER 31 WITH NO PREVIOUS EMPLOYER WITHIN THE YEAR (Reported Under BIR Form No. 2316)',
        ];

        $headers = [
            'SEQ NO (1)',
            'TIN (2)',
            'Last Name (3a)',
            'First Name (3a)',
            'Middle Name (3a)',
            'Date From',
            'Date To',
            'Gross Compensation (4a)',
            '13th Month & Other Benefits (4b)',
            'De Minimis Benefits (4c)',
            'SSS/GSIS/PHIC/Pag-IBIG (4d)',
            'Other Non-Taxable (4e)',
            'Total Non-Taxable (4f)',
            'Basic Salary (4g)',
            '13th Month Taxable (4h)',
            'Other Taxable (4i)',
            'Total Taxable (4j)',
            'Exemption Code (5a)',
            'Exemption Amount (5b)',
            'Premium Health Insurance (6)',
            'Net Taxable (7)',
            'Tax Due (8)',
            'Tax Withheld Jan-Nov (9)',
            'Under Withheld (10a)',
            'Over Withheld (10b)',
            'Tax Withheld Adjusted (11)',
            'Substituted Filing (12)',
        ];

        $this->writeSheetHeader($sheet, 'Schedule '.$code, $titles[$code] ?? '', $meta, count($headers));
        $headerRow = 5;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue([$col + 1, $headerRow], $label);
        }
        $this->styleHeaderRow($sheet, $headerRow, count($headers));

        $excelRow = $headerRow + 1;
        $seq = 0;
        $totals = array_fill_keys([
            '4a', '4b', '4c', '4d', '4e', '4f', '4g', '4h', '4i', '4j', '5b', '6', '7', '8', '9', '10a', '10b', '11',
        ], 0.0);

        foreach ($rows as $row) {
            $seq++;
            /** @var array<string, mixed> $a */
            $a = $row['amounts'];
            $values = [
                $seq,
                (string) $row['tin'],
                (string) $row['last_name'],
                (string) $row['first_name'],
                (string) $row['middle_name'],
                (string) $row['date_from'],
                (string) $row['date_to'],
                (float) $a['4a'],
                (float) $a['4b'],
                (float) $a['4c'],
                (float) $a['4d'],
                (float) $a['4e'],
                (float) $a['4f'],
                (float) $a['4g'],
                (float) $a['4h'],
                (float) $a['4i'],
                (float) $a['4j'],
                (string) $a['5a'],
                (float) $a['5b'],
                (float) $a['6'],
                (float) $a['7'],
                (float) $a['8'],
                (float) $a['9'],
                (float) $a['10a'],
                (float) $a['10b'],
                (float) $a['11'],
                (string) $a['12'],
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $excelRow], $value);
            }

            foreach ($totals as $key => $_) {
                $totals[$key] = round($totals[$key] + (float) $a[$key], 2);
            }

            $excelRow++;
        }

        if ($seq === 0) {
            $sheet->setCellValue('A6', 'No employees in this schedule.');
            $this->autosize($sheet, count($headers));

            return;
        }

        $sheet->setCellValue("E{$excelRow}", 'TOTAL');
        $totalValues = [
            8 => $totals['4a'],
            9 => $totals['4b'],
            10 => $totals['4c'],
            11 => $totals['4d'],
            12 => $totals['4e'],
            13 => $totals['4f'],
            14 => $totals['4g'],
            15 => $totals['4h'],
            16 => $totals['4i'],
            17 => $totals['4j'],
            19 => $totals['5b'],
            20 => $totals['6'],
            21 => $totals['7'],
            22 => $totals['8'],
            23 => $totals['9'],
            24 => $totals['10a'],
            25 => $totals['10b'],
            26 => $totals['11'],
        ];
        foreach ($totalValues as $col => $value) {
            $sheet->setCellValue([$col, $excelRow], $value);
        }
        $sheet->getStyle("A{$excelRow}:AA{$excelRow}")->getFont()->setBold(true);
        $sheet->getStyle('H'.($headerRow + 1).':Q'.$excelRow)
            ->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('S'.($headerRow + 1).':Z'.$excelRow)
            ->getNumberFormat()->setFormatCode('#,##0.00');

        $this->autosize($sheet, count($headers));
    }

    /**
     * Previous employer columns are zero — Pulse does not store prior-employer YTD separately.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function writeSchedule74(Worksheet $sheet, array $rows, array $meta): void
    {
        $headers = [
            'SEQ NO (1)',
            'TIN (2)',
            'Last Name (3a)',
            'First Name (3a)',
            'Middle Name (3a)',
            'Gross (4a)',
            'Prev 13th (4b)',
            'Prev De Minimis (4c)',
            'Prev Statutory (4d)',
            'Prev Other NT (4e)',
            'Prev Total NT (4f)',
            'Prev Basic (4g)',
            'Prev 13th Tax (4h)',
            'Prev Other Tax (4i)',
            'Prev Total Tax (4j)',
            'Pres 13th (4k)',
            'Pres De Minimis (4l)',
            'Pres Statutory (4m)',
            'Pres Other NT (4n)',
            'Pres Total NT (4o)',
            'Pres Basic (4p)',
            'Pres 13th Tax (4q)',
            'Pres Other Tax (4r)',
            'Pres Total Tax (4s)',
            'Total Tax Prev+Pres (4t)',
            'Exemption Code (5a)',
            'Exemption Amount (5b)',
            'Premium (6)',
            'Net Taxable (7)',
            'Tax Due (8)',
            'Tax WH Prev (9a)',
            'Tax WH Pres (9b)',
            'Under (10a)',
            'Over (10b)',
            'Tax WH Adjusted (11)',
        ];

        $this->writeSheetHeader(
            $sheet,
            'Schedule 7.4',
            'ALPHALIST OF EMPLOYEES AS OF DECEMBER 31 WITH PREVIOUS EMPLOYER WITHIN THE YEAR (Reported Under BIR Form No. 2316)',
            $meta,
            count($headers),
        );

        $headerRow = 5;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue([$col + 1, $headerRow], $label);
        }
        $this->styleHeaderRow($sheet, $headerRow, count($headers));

        $excelRow = $headerRow + 1;
        $seq = 0;

        foreach ($rows as $row) {
            $seq++;
            /** @var array<string, mixed> $a */
            $a = $row['amounts'];
            $values = [
                $seq,
                (string) $row['tin'],
                (string) $row['last_name'],
                (string) $row['first_name'],
                (string) $row['middle_name'],
                (float) $a['4a'],
                0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, // previous employer
                (float) $a['4b'],
                (float) $a['4c'],
                (float) $a['4d'],
                (float) $a['4e'],
                (float) $a['4f'],
                (float) $a['4g'],
                (float) $a['4h'],
                (float) $a['4i'],
                (float) $a['4j'],
                (float) $a['4j'],
                (string) $a['5a'],
                (float) $a['5b'],
                (float) $a['6'],
                (float) $a['7'],
                (float) $a['8'],
                0.0,
                (float) $a['9'],
                (float) $a['10a'],
                (float) $a['10b'],
                (float) $a['11'],
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $excelRow], $value);
            }
            $excelRow++;
        }

        if ($seq === 0) {
            $sheet->setCellValue('A6', 'No employees in this schedule.');
        } else {
            $sheet->getStyle('F'.($headerRow + 1).':AI'.($excelRow - 1))
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $this->autosize($sheet, count($headers));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function writeSchedule75(Worksheet $sheet, array $rows, array $meta): void
    {
        $headers = [
            'SEQ NO (1)',
            'TIN (2)',
            'Last Name (3a)',
            'First Name (3a)',
            'Middle Name (3a)',
            'Region (4)',
            'Gross (5a)',
            'Basic/SMW (5b)',
            'Holiday (5c)',
            'Overtime (5d)',
            'NSD (5e)',
            'Hazard (5f)',
            '13th Month (5g)',
            'De Minimis (5h)',
            'Statutory (5i)',
            'Other NT (5j)',
            'Total NT (5k)',
            'Date From (5o)',
            'Date To (5p)',
            'SMW/Day (5r)',
            'SMW/Month (5s)',
            'Factor Days/Year (5u)',
            'Exemption Code (6a)',
            'Tax WH Present (10b)',
            'Tax WH Adjusted (12)',
        ];

        $this->writeSheetHeader(
            $sheet,
            'Schedule 7.5',
            'ALPHALIST OF MINIMUM WAGE EARNERS (Reported Under BIR Form No. 2316)',
            $meta,
            count($headers),
        );

        $headerRow = 5;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue([$col + 1, $headerRow], $label);
        }
        $this->styleHeaderRow($sheet, $headerRow, count($headers));

        $smwDay = (float) ($meta['smw_rate_per_day'] ?? 0);
        $smwMonth = (float) ($meta['smw_rate_per_month'] ?? 0);
        $factor = (float) ($meta['day_factor'] ?? 313);

        $excelRow = $headerRow + 1;
        $seq = 0;

        foreach ($rows as $row) {
            $seq++;
            /** @var array<string, mixed> $a */
            $a = $row['amounts'];
            $basic = (float) $a['4e'] + (float) $a['4g']; // non-tax + tax basic often rolled into NT for MWE
            $values = [
                $seq,
                (string) $row['tin'],
                (string) $row['last_name'],
                (string) $row['first_name'],
                (string) $row['middle_name'],
                '',
                (float) $a['4a'],
                $basic > 0 ? $basic : (float) $a['4a'] - (float) $a['4b'] - (float) $a['4c'] - (float) $a['4d'],
                0.0,
                0.0,
                0.0,
                0.0,
                (float) $a['4b'],
                (float) $a['4c'],
                (float) $a['4d'],
                (float) $a['4e'],
                (float) $a['4f'],
                (string) $row['date_from'],
                (string) $row['date_to'],
                $smwDay,
                $smwMonth,
                $factor,
                (string) $a['5a'],
                (float) $a['9'],
                (float) $a['11'],
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $excelRow], $value);
            }
            $excelRow++;
        }

        if ($seq === 0) {
            $sheet->setCellValue('A6', 'No employees in this schedule.');
        } else {
            $sheet->getStyle('G'.($headerRow + 1).':Q'.($excelRow - 1))
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('T'.($headerRow + 1).':V'.($excelRow - 1))
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('X'.($headerRow + 1).':Y'.($excelRow - 1))
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $this->autosize($sheet, count($headers));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeSheetHeader(
        Worksheet $sheet,
        string $scheduleLabel,
        string $subtitle,
        array $meta,
        int $columnCount,
    ): void {
        $employer = $meta['employer'] ?? [];
        $sheet->setCellValue('A1', 'ALPHABETICAL LIST OF EMPLOYEES/PAYEES FROM WHOM TAXES WERE WITHHELD');
        $sheet->mergeCells('A1:'.$this->colLetter($columnCount).'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', $scheduleLabel.' — '.$subtitle);
        $sheet->mergeCells('A2:'.$this->colLetter($columnCount).'2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(9);

        $year = (string) ($meta['pay_year'] ?? '');
        $tin = (string) (($employer['tin_formatted'] ?? '') !== '' ? $employer['tin_formatted'] : ($employer['tin'] ?? ''));
        $sheet->setCellValue('A3', 'Applicable Year: '.$year.' | Employer: '.($employer['name'] ?? '').' | TIN: '.$tin);
        $sheet->mergeCells('A3:'.$this->colLetter($columnCount).'3');
        $sheet->getStyle('A3')->getFont()->setSize(9);
    }

    private function styleHeaderRow(Worksheet $sheet, int $row, int $columnCount): void
    {
        $range = 'A'.$row.':'.$this->colLetter($columnCount).$row;
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(8);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($range)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(36);
    }

    private function autosize(Worksheet $sheet, int $columnCount): void
    {
        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    private function colLetter(int $columnNumber): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $columnNumber));
    }
}
