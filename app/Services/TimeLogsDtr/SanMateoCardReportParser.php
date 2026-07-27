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

    /** Consecutive punches within this gap keep the same IN/OUT tag (e.g. double-scan). */
    private const PUNCH_SAME_TAG_WITHIN_MINUTES = 5;

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
        $fingerprints = [];
        $parsedCardSheets = 0;
        $parsedAttLogSheets = 0;

        foreach ($sheetNames as $sheetName) {
            if ($this->isAttLogReportSheet($sheetName)) {
                continue;
            }

            if ($this->shouldSkipSheetByTabName($sheetName, $format)) {
                continue;
            }

            $matrix = $this->loadSheetMatrix($reader, $path, $sheetName);

            if ($this->shouldSkipSheet($matrix, $sheetName, $format)) {
                continue;
            }

            if (! $this->isCardReportSheet($matrix, $format)) {
                continue;
            }

            $parsedCardSheets++;
            $sheetRows = $this->parseCardReportSheet($matrix);

            foreach ($sheetRows as $sheetRow) {
                $this->appendUniqueRow($rows, $fingerprints, $sheetRow);
            }
        }

        foreach ($sheetNames as $sheetName) {
            if (! $this->isAttLogReportSheet($sheetName)) {
                continue;
            }

            $matrix = $this->loadSheetMatrix($reader, $path, $sheetName);
            $parsedAttLogSheets++;
            $sheetRows = $this->parseAttLogReportSheet($matrix);

            foreach ($sheetRows as $sheetRow) {
                $this->appendUniqueRow($rows, $fingerprints, $sheetRow);
            }
        }

        if ($parsedCardSheets === 0 && $parsedAttLogSheets === 0) {
            throw new RuntimeException('No Card Report or Att.log report worksheets found. Summary sheets are ignored.');
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function loadSheetMatrix(\PhpOffice\PhpSpreadsheet\Reader\IReader $reader, string $path, string $sheetName): array
    {
        $reader->setLoadSheetsOnly([$sheetName]);
        $spreadsheet = $reader->load($path);
        $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $matrix;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, true>  $fingerprints
     * @param  array<string, mixed>  $sheetRow
     */
    private function appendUniqueRow(array &$rows, array &$fingerprints, array $sheetRow): void
    {
        if ($this->isPunchRow($sheetRow)) {
            $fingerprint = $this->punchFingerprint($sheetRow);

            if (isset($fingerprints[$fingerprint])) {
                return;
            }

            $fingerprints[$fingerprint] = true;
            $rows[] = $sheetRow;

            return;
        }

        $fingerprint = $this->pairFingerprint($sheetRow);

        if (isset($fingerprints[$fingerprint])) {
            return;
        }

        $fingerprints[$fingerprint] = true;
        $this->registerPairPunchFingerprints($fingerprints, $sheetRow);
        $rows[] = $sheetRow;
    }

    /**
     * @param  array<string, true>  $fingerprints
     * @param  array{biometric_id: string, actual_date: string, time_in: string, time_out: string}  $sheetRow
     */
    private function registerPairPunchFingerprints(array &$fingerprints, array $sheetRow): void
    {
        $fingerprints[$this->punchFingerprint([
            'biometric_id' => $sheetRow['biometric_id'],
            'actual_date' => $sheetRow['actual_date'],
            'punch_time' => $sheetRow['time_in'],
            'is_in' => true,
        ])] = true;

        $fingerprints[$this->punchFingerprint([
            'biometric_id' => $sheetRow['biometric_id'],
            'actual_date' => $sheetRow['actual_date'],
            'punch_time' => $sheetRow['time_out'],
            'is_in' => false,
        ])] = true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPunchRow(array $row): bool
    {
        return array_key_exists('punch_time', $row);
    }

    /**
     * @param  array{biometric_id: string, actual_date: string, time_in: string, time_out: string}  $row
     */
    private function pairFingerprint(array $row): string
    {
        return trim((string) ($row['biometric_id'] ?? ''))
            .'|'.($row['actual_date'] ?? '')
            .'|'.($row['time_in'] ?? '')
            .'|'.($row['time_out'] ?? '');
    }

    /**
     * @param  array{biometric_id: string, actual_date: string, punch_time: string, is_in: bool}  $row
     */
    private function punchFingerprint(array $row): string
    {
        return trim((string) ($row['biometric_id'] ?? ''))
            .'|'.($row['actual_date'] ?? '')
            .'|'.($row['punch_time'] ?? '')
            .'|'.(($row['is_in'] ?? false) ? '1' : '0');
    }

    private function rowFingerprint(array $row): string
    {
        return $this->isPunchRow($row)
            ? $this->punchFingerprint($row)
            : $this->pairFingerprint($row);
    }

    private function isAttLogReportSheet(string $sheetName): bool
    {
        return strtolower(trim($sheetName)) === 'att.log report';
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
     * @return array<int, array{biometric_id: string, actual_date: string, punch_time: string, is_in: bool}>
     */
    private function parseAttLogReportSheet(array $matrix): array
    {
        $period = $this->extractPeriod($matrix);

        if ($period === null) {
            return [];
        }

        $headerRow = $this->findDayNumberHeaderRow($matrix);

        if ($headerRow === null) {
            return [];
        }

        $rows = [];
        $maxRow = $matrix === [] ? 0 : max(array_keys($matrix));
        $rowIndex = $headerRow + 1;

        while ($rowIndex <= $maxRow) {
            $idCells = array_map(fn ($cell) => $this->stringifyCell($cell), $matrix[$rowIndex] ?? []);

            if (! $this->isAttLogIdRow($idCells)) {
                $rowIndex++;

                continue;
            }

            $biometricId = $this->extractAttLogBiometricId($idCells);

            if ($biometricId === '') {
                $rowIndex++;

                continue;
            }

            $timeRowIndex = $rowIndex + 1;

            if ($timeRowIndex > $maxRow) {
                break;
            }

            for ($day = 1; $day <= 30; $day++) {
                $colIndex = $day - 1;
                $times = $this->extractTimesFromCell($matrix[$timeRowIndex][$colIndex] ?? null);

                if ($times === []) {
                    continue;
                }

                $actualDate = $this->resolveDate($day, $period);

                if ($actualDate === null) {
                    continue;
                }

                foreach ($this->assignInOutTags($times) as $taggedPunch) {
                    $rows[] = [
                        'biometric_id' => $biometricId,
                        'actual_date' => $actualDate,
                        'punch_time' => $taggedPunch['punch_time'],
                        'is_in' => $taggedPunch['is_in'],
                    ];
                }
            }

            $rowIndex += 2;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findDayNumberHeaderRow(array $matrix): ?int
    {
        foreach ($matrix as $rowIndex => $cells) {
            if ($this->rowLooksLikeDayNumberHeader($cells)) {
                return $rowIndex;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function rowLooksLikeDayNumberHeader(array $cells): bool
    {
        for ($day = 1; $day <= 5; $day++) {
            $value = trim($this->stringifyCell($cells[$day - 1] ?? ''));

            if ($value !== (string) $day) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isAttLogIdRow(array $cells): bool
    {
        $label = strtolower(trim((string) ($cells[0] ?? '')));

        return $label === 'id:' || $label === 'id';
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function extractAttLogBiometricId(array $cells): string
    {
        foreach ($cells as $colIndex => $raw) {
            $value = trim((string) $raw);

            if (preg_match('/^id\s*:\s*(.+)$/i', $value, $inlineMatch)) {
                $id = trim($inlineMatch[1]);

                if ($id !== '' && ! str_contains(strtolower($id), 'name')) {
                    return $id;
                }
            }

            $label = strtolower($value);

            if (! in_array($label, ['id:', 'id'], true)) {
                continue;
            }

            for ($valueCol = $colIndex + 1; $valueCol <= $colIndex + 3; $valueCol++) {
                $id = trim((string) ($cells[$valueCol] ?? ''));

                if ($id !== '' && ! str_contains(strtolower($id), 'name')) {
                    return $id;
                }
            }
        }

        $fallback = trim((string) ($cells[2] ?? ''));

        if ($fallback !== '' && preg_match('/^\d+$/', $fallback)) {
            return $fallback;
        }

        return '';
    }

    /**
     * First punch is always IN; later punches flip IN/OUT unless within 5 minutes of the previous punch.
     *
     * @param  array<int, string>  $times
     * @return array<int, array{punch_time: string, is_in: bool}>
     */
    private function assignInOutTags(array $times): array
    {
        if ($times === []) {
            return [];
        }

        $tagged = [];
        $previousIsIn = true;
        $previousMinutes = null;

        foreach ($times as $index => $punchTime) {
            $currentMinutes = $this->timeToMinutes($punchTime);

            if ($index === 0) {
                $previousIsIn = true;
            } elseif ($previousMinutes !== null
                && ($currentMinutes - $previousMinutes) <= self::PUNCH_SAME_TAG_WITHIN_MINUTES) {
                // Keep the same tag for rapid duplicate scans.
            } else {
                $previousIsIn = ! $previousIsIn;
            }

            $tagged[] = [
                'punch_time' => $punchTime,
                'is_in' => $previousIsIn,
            ];

            $previousMinutes = $currentMinutes;
        }

        return $tagged;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    /**
     * @return array<int, string>
     */
    private function extractTimesFromCell(mixed $cell): array
    {
        if ($cell === null || $cell === '') {
            return [];
        }

        if (is_numeric($cell)) {
            $numeric = (float) $cell;

            if ($numeric > 0 && $numeric < 1) {
                $normalized = $this->normalizeTime((string) $cell);

                return $normalized !== '' ? [$normalized] : [];
            }

            return [];
        }

        $text = $this->stringifyCell($cell);

        if ($text === '') {
            return [];
        }

        preg_match_all('/\d{1,2}:\d{2}(?::\d{2})?/', $text, $matches);
        $times = [];

        foreach ($matches[0] as $match) {
            $normalized = $this->normalizeTime($match);

            if ($normalized === '') {
                continue;
            }

            if (! in_array($normalized, $times, true)) {
                $times[] = $normalized;
            }
        }

        return $times;
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
