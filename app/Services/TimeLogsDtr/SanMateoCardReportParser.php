<?php

namespace App\Services\TimeLogsDtr;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class SanMateoCardReportParser
{
    private const TIME_PATTERN = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/';

    /**
     * @param  array<string, mixed>  $format
     * @return array{
     *     rows: array<int, array{biometric_id: string, actual_date: string, time_in: string, time_out: string}>,
     *     errors: array<int, string>
     * }
     */
    public function parse(UploadedFile $file, array $format): array
    {
        $path = $file->getRealPath();

        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (\Throwable) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        $reader->setReadDataOnly(true);

        $sheetNames = $reader->listWorksheetNames($path);
        $rows = [];
        $errors = [];
        $parsedSheets = 0;
        $lineNumber = 0;

        foreach ($sheetNames as $sheetName) {
            if ($this->shouldSkipSheetByTabName($sheetName, $format)) {
                continue;
            }

            $reader->setLoadSheetsOnly([$sheetName]);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $matrix = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($this->shouldSkipSheet($matrix, $sheetName, $format)) {
                continue;
            }

            if (! $this->isCardReportSheet($matrix, $format)) {
                continue;
            }

            $parsedSheets++;
            $sheetRows = $this->parseCardReportSheet($matrix);

            foreach ($sheetRows as $sheetRow) {
                $lineNumber++;
                $rows[] = $sheetRow;
            }
        }

        if ($parsedSheets === 0) {
            throw new RuntimeException('No Card Report worksheets found. Summary sheets are ignored.');
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $format
     */
    private function shouldSkipSheetByTabName(string $sheetTitle, array $format): bool
    {
        $skipTabNames = array_map('strtolower', (array) ($format['skip_sheet_names'] ?? []));

        foreach ($skipTabNames as $tabName) {
            if ($tabName !== '' && strtolower(trim($sheetTitle)) === $tabName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @param  array<string, mixed>  $format
     */
    private function shouldSkipSheet(array $matrix, string $sheetTitle, array $format): bool
    {
        $skipTitles = array_map('strtolower', (array) ($format['skip_sheet_titles'] ?? []));
        $skipTabNames = array_map('strtolower', (array) ($format['skip_sheet_names'] ?? []));
        $haystack = strtolower($sheetTitle.' '.$this->flattenHeaderText($matrix, 4));

        foreach ($skipTitles as $title) {
            if ($title !== '' && str_contains($haystack, $title)) {
                return true;
            }
        }

        foreach ($skipTabNames as $tabName) {
            if ($tabName !== '' && strtolower(trim($sheetTitle)) === $tabName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @param  array<string, mixed>  $format
     */
    private function isCardReportSheet(array $matrix, array $format): bool
    {
        $marker = strtolower((string) ($format['parse_sheet_marker'] ?? 'Card Report'));
        $haystack = strtolower($this->flattenHeaderText($matrix, 12));

        if ($marker !== '' && str_contains($haystack, $marker)) {
            return true;
        }

        return str_contains($haystack, 'att. report')
            && (str_contains($haystack, 'week date') || str_contains($haystack, 'weekdate'))
            && str_contains($haystack, 'on-duty');
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array<int, array{biometric_id: string, actual_date: string, time_in: string, time_out: string}>
     */
    private function parseCardReportSheet(array $matrix): array
    {
        $period = $this->extractPeriod($matrix);

        if ($period === null) {
            return [];
        }

        $attReportRow = $this->findAttReportRow($matrix);

        if ($attReportRow === null) {
            return [];
        }

        $headerRow = $this->findWeekDateHeaderRow($matrix, $attReportRow);

        if ($headerRow === null) {
            return [];
        }

        $blocks = $this->findEmployeeBlocks($matrix, $headerRow);
        $rows = [];
        $maxRow = $matrix === [] ? 0 : max(array_keys($matrix));

        foreach ($blocks as $block) {
            $biometricId = trim((string) ($block['biometric_id'] ?? ''));

            if ($biometricId === '') {
                continue;
            }

            for ($rowIndex = $headerRow + 2; $rowIndex <= $maxRow; $rowIndex++) {
                $cells = array_map(fn ($cell) => $this->stringifyCell($cell), $matrix[$rowIndex] ?? []);
                $weekDateCell = trim((string) ($cells[$block['week_date_col']] ?? ''));

                if ($weekDateCell === '' || ! preg_match('/^(\d{1,2})\b/', $weekDateCell, $dayMatch)) {
                    continue;
                }

                $actualDate = $this->resolveDate((int) $dayMatch[1], $period);

                if ($actualDate === null) {
                    continue;
                }

                $timeIn = $this->normalizeTime((string) ($cells[$block['first_in_col']] ?? ''));
                $timeOut = $this->normalizeTime((string) ($cells[$block['first_out_col']] ?? ''));

                if ($timeIn === '' || $timeOut === '') {
                    $timeIn = $timeIn !== '' ? $timeIn : $this->normalizeTime((string) ($cells[$block['ot_in_col']] ?? ''));
                    $timeOut = $timeOut !== '' ? $timeOut : $this->normalizeTime((string) ($cells[$block['ot_out_col']] ?? ''));
                }

                if ($timeIn === '' || $timeOut === '') {
                    continue;
                }

                $rows[] = [
                    'biometric_id' => $biometricId,
                    'actual_date' => $actualDate,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array{from: Carbon, to: Carbon}|null
     */
    private function extractPeriod(array $matrix): ?array
    {
        $haystack = $this->flattenHeaderText($matrix, 12, true);

        if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $haystack, $matches)) {
            return [
                'from' => Carbon::parse($matches[1])->startOfDay(),
                'to' => Carbon::parse($matches[2])->startOfDay(),
            ];
        }

        foreach (array_slice($matrix, 0, 15, true) as $cells) {
            $rowDates = [];

            foreach ($cells as $cell) {
                $parsed = $this->parseDateValue($cell);

                if ($parsed !== null) {
                    $rowDates[] = $parsed;
                }
            }

            if (count($rowDates) >= 2) {
                $from = $rowDates[0];
                $to = $rowDates[1];

                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }

                return [
                    'from' => $from,
                    'to' => $to,
                ];
            }

            foreach ($cells as $cell) {
                $text = $this->stringifyCell($cell);

                if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
                    return [
                        'from' => Carbon::parse($matches[1])->startOfDay(),
                        'to' => Carbon::parse($matches[2])->startOfDay(),
                    ];
                }
            }
        }

        return null;
    }

    private function parseDateValue(mixed $cell): ?Carbon
    {
        if ($cell === null || $cell === '') {
            return null;
        }

        if ($cell instanceof \DateTimeInterface) {
            return Carbon::instance($cell)->startOfDay();
        }

        if (is_numeric($cell)) {
            $numeric = (float) $cell;

            if ($numeric >= 30000 && $numeric < 70000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($numeric))->startOfDay();
            }

            return null;
        }

        $text = trim((string) $cell);

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
            return Carbon::parse($matches[1])->startOfDay();
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findAttReportRow(array $matrix): ?int
    {
        foreach ($matrix as $rowIndex => $cells) {
            foreach ($cells as $cell) {
                if (str_contains(strtolower($this->stringifyCell($cell)), 'att. report')) {
                    return $rowIndex;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findWeekDateHeaderRow(array $matrix, int $attReportRow): ?int
    {
        $maxRow = $matrix === [] ? 0 : max(array_keys($matrix));

        for ($rowIndex = $attReportRow; $rowIndex <= min($attReportRow + 6, $maxRow); $rowIndex++) {
            foreach ($matrix[$rowIndex] ?? [] as $cell) {
                if ($this->isWeekDateHeader($this->stringifyCell($cell))) {
                    return $rowIndex;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array<int, array<string, int|string>>
     */
    private function findEmployeeBlocks(array $matrix, int $headerRow): array
    {
        $blocks = [];
        $headerCells = array_map(fn ($cell) => $this->stringifyCell($cell), $matrix[$headerRow] ?? []);
        $subHeaderCells = array_map(fn ($cell) => $this->stringifyCell($cell), $matrix[$headerRow + 1] ?? []);

        foreach ($headerCells as $colIndex => $headerValue) {
            if (! $this->isWeekDateHeader($headerValue)) {
                continue;
            }

            $biometricId = $this->findBiometricIdNearColumn($matrix, $colIndex, $headerRow);
            $offsets = $this->resolveTimeColumnOffsets($subHeaderCells, $colIndex);

            if ($biometricId === '') {
                continue;
            }

            $blocks[] = array_merge([
                'biometric_id' => $biometricId,
                'week_date_col' => $colIndex,
            ], $offsets);
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findBiometricIdNearColumn(array $matrix, int $colIndex, int $headerRow): string
    {
        for ($rowIndex = max(0, $headerRow - 10); $rowIndex < $headerRow; $rowIndex++) {
            $cells = array_map(fn ($cell) => $this->stringifyCell($cell), $matrix[$rowIndex] ?? []);

            for ($scanCol = max(0, $colIndex - 2); $scanCol <= $colIndex + 12; $scanCol++) {
                $raw = trim((string) ($cells[$scanCol] ?? ''));

                if (preg_match('/^id\s*:\s*(.+)$/i', $raw, $inlineMatch)) {
                    $value = trim($inlineMatch[1]);

                    if ($value !== '' && ! str_contains(strtolower($value), 'name')) {
                        return $value;
                    }
                }

                $label = strtolower($raw);

                if (! in_array($label, ['id:', 'id'], true)) {
                    continue;
                }

                for ($valueCol = $scanCol + 1; $valueCol <= $scanCol + 3; $valueCol++) {
                    $value = trim((string) ($cells[$valueCol] ?? ''));

                    if ($value !== '' && ! str_contains(strtolower($value), 'name')) {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    private function isWeekDateHeader(string $value): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', trim($value)) ?? '');

        return $normalized === 'weekdate' || str_contains($normalized, 'weekdate');
    }

    private function normalizeHeaderToken(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($value)) ?? '');
    }

    /**
     * @param  array<int, string>  $subHeaderCells
     * @return array{first_in_col: int, first_out_col: int, ot_in_col: int, ot_out_col: int}
     */
    private function resolveTimeColumnOffsets(array $subHeaderCells, int $weekDateCol): array
    {
        $onDutyCols = [];
        $offDutyCols = [];
        $checkInCols = [];
        $checkOutCols = [];

        foreach ($subHeaderCells as $colIndex => $label) {
            if ($colIndex <= $weekDateCol) {
                continue;
            }

            match ($this->normalizeHeaderToken($label)) {
                'on-duty' => $onDutyCols[] = $colIndex,
                'off-duty' => $offDutyCols[] = $colIndex,
                'check-in' => $checkInCols[] = $colIndex,
                'check-out' => $checkOutCols[] = $colIndex,
                default => null,
            };
        }

        return [
            'first_in_col' => $onDutyCols[0] ?? ($weekDateCol + 1),
            'first_out_col' => $offDutyCols[0] ?? ($weekDateCol + 2),
            'ot_in_col' => $checkInCols[0] ?? ($weekDateCol + 5),
            'ot_out_col' => $checkOutCols[0] ?? ($weekDateCol + 6),
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $period
     */
    private function resolveDate(int $day, array $period): ?string
    {
        $cursor = $period['from']->copy();

        while ($cursor->lte($period['to'])) {
            if ((int) $cursor->day === $day) {
                return $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function flattenHeaderText(array $matrix, int $maxRows, bool $normalizeDates = false): string
    {
        $parts = [];

        foreach (array_slice($matrix, 0, $maxRows, true) as $cells) {
            foreach ($cells as $cell) {
                if ($normalizeDates) {
                    $parsed = $this->parseDateValue($cell);

                    if ($parsed !== null) {
                        $parts[] = $parsed->toDateString();

                        continue;
                    }
                }

                $value = trim($this->stringifyCell($cell));

                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        return implode(' ', $parts);
    }

    private function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric >= 0 && $numeric < 1) {
                return gmdate('H:i:s', (int) round($numeric * 86400));
            }
        }

        if (preg_match(self::TIME_PATTERN, $value, $matches)) {
            $seconds = $matches[3] ?? '00';

            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $seconds);
        }

        if (preg_match('/^\d{5,}(\.\d+)?$/', $value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s');
        }

        return '';
    }
}
