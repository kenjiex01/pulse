<?php

namespace App\Services\Reports;

use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRegisterExcelExporter
{
    /**
     * @param  array<int, array<string, mixed>>  $registerRows
     * @param  array<string, mixed>  $meta
     */
    public function stream(array $registerRows, array $meta): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheetGroup = (string) ($meta['sheet_group'] ?? 'campus');
        $groups = $sheetGroup === 'period'
            ? $this->groupRowsByPeriodSheet($registerRows)
            : $this->groupRowsByCampusSheet($registerRows);
        $first = true;

        foreach ($groups as $sheetName => $rows) {
            if ($rows === []) {
                continue;
            }

            if ($first) {
                $sheet = $spreadsheet->getActiveSheet();
                $first = false;
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle($this->safeSheetTitle($sheetName));
            $this->writeLayout($sheet, $rows, array_merge($meta, [
                'sheet_title' => $sheetName,
                'campus_sheet' => $sheetName,
                'period_sheet' => $sheetName,
            ]));
        }

        // No employee rows at all — keep a single empty placeholder sheet.
        if ($first) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Payroll Register');
            $this->writeLayout($sheet, [], $meta);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Payroll_Register_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $registerRows
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupRowsByCampusSheet(array $registerRows): array
    {
        $order = config('payroll_register_layout.excel_campus_sheet_order', [
            'Antipolo',
            'Binangonan',
            'Cogeo',
            'San Mateo',
            'Sumulong',
            'Taytay',
            'Cainta',
        ]);
        $default = (string) config('payroll_register_layout.excel_campus_sheet_default', 'Cainta');

        $groups = [];

        foreach ($order as $sheetName) {
            $groups[(string) $sheetName] = [];
        }

        if (! array_key_exists($default, $groups)) {
            $groups[$default] = [];
        }

        foreach ($registerRows as $index => $row) {
            $sheetName = trim((string) ($row['campus_sheet'] ?? ''));

            if ($sheetName === '' || ! array_key_exists($sheetName, $groups)) {
                $sheetName = $default;
            }

            $row['index'] = count($groups[$sheetName]) + 1;
            $groups[$sheetName][] = $row;
            unset($registerRows[$index]);
        }

        return $groups;
    }

    /**
     * One worksheet per payroll period key (e.g. 27-10, 11-26), matching ICCT Staff register tabs.
     *
     * @param  array<int, array<string, mixed>>  $registerRows
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupRowsByPeriodSheet(array $registerRows): array
    {
        $groups = [];

        foreach ($registerRows as $row) {
            $sheetName = trim((string) ($row['period_sheet'] ?? ''));

            if ($sheetName === '') {
                $sheetName = 'Register';
            }

            if (! array_key_exists($sheetName, $groups)) {
                $groups[$sheetName] = [];
            }

            $row['index'] = count($groups[$sheetName]) + 1;
            $groups[$sheetName][] = $row;
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $registerRows
     * @param  array<string, mixed>  $meta
     */
    private function writeLayout(Worksheet $sheet, array $registerRows, array $meta): void
    {
        $layoutConfig = (string) ($meta['layout_config'] ?? 'payroll_register_layout');
        $layout = config($layoutConfig, config('payroll_register_layout'));
        $lastColumn = (string) ($layout['last_column'] ?? 'CE');
        $headerRow = (int) ($layout['header_row'] ?? 5);
        $subHeaderRow = (int) ($layout['subheader_row'] ?? 6);
        $dataStartRow = (int) ($layout['data_start_row'] ?? 8);

        $companyName = (string) ($meta['company_name'] ?? $layout['company_name'] ?? config('app.name'));
        $subtitle = (string) ($meta['subtitle'] ?? $layout['subtitle'] ?? 'PAYROLL REGISTER');
        $periodLabel = (string) ($meta['period_label'] ?? '');
        $sheetLabel = (string) ($meta['period_sheet'] ?? $meta['campus_sheet'] ?? $meta['sheet_title'] ?? '');

        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', $subtitle);
        $periodLine = $periodLabel !== '' ? 'Period Covered: '.$periodLabel : '';
        if ($sheetLabel !== '' && ($meta['sheet_group'] ?? '') === 'period') {
            $periodLine = $periodLabel !== ''
                ? 'Period Covered: '.$periodLabel
                : 'Payroll Period : '.$sheetLabel;
        }
        $sheet->setCellValue('A3', $periodLine);

        foreach ([1, 2, 3] as $row) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(12);

        $highlightColumns = [];

        foreach ($layout['columns'] as $column) {
            $letter = (string) $column['letter'];
            $sheet->setCellValue("{$letter}{$headerRow}", (string) ($column['row5'] ?? ''));
            $sheet->setCellValue("{$letter}{$subHeaderRow}", (string) ($column['row6'] ?? ''));

            if (! empty($column['highlight'])) {
                $highlightColumns[] = $letter;
            }
        }

        $headerRange = "A{$headerRow}:{$lastColumn}{$subHeaderRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        foreach ($highlightColumns as $letter) {
            $sheet->getStyle("{$letter}{$headerRow}:{$letter}{$subHeaderRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFFFF00');
        }

        $rowNumber = $dataStartRow;

        foreach ($registerRows as $registerRow) {
            foreach ($layout['columns'] as $column) {
                $field = $column['field'] ?? null;

                if ($field === null || $field === '') {
                    continue;
                }

                $value = $registerRow[$field] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $sheet->setCellValue(
                    (string) $column['letter'].$rowNumber,
                    is_numeric($value) ? (float) $value : (string) $value,
                );
            }

            $rowNumber++;
        }

        if ($rowNumber > $dataStartRow) {
            $dataRange = "A{$dataStartRow}:{$lastColumn}".($rowNumber - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            foreach ($highlightColumns as $letter) {
                $sheet->getStyle("{$letter}{$dataStartRow}:{$letter}".($rowNumber - 1))
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFFFFF00');
            }
        }

        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);

        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))
                ->setAutoSize(true);
        }

        $sheet->freezePane("A{$dataStartRow}");
    }

    private function safeSheetTitle(string $title): string
    {
        $clean = preg_replace('/[\[\]\:\*\?\/\\\\]/', '-', $title) ?: 'Campus';

        return mb_substr($clean, 0, 31);
    }
}
