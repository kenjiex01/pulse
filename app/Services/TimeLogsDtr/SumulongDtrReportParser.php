<?php

namespace App\Services\TimeLogsDtr;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class SumulongDtrReportParser
{
    private const EMPLOYEE_PATTERN = '/Employee:\s*(.+?)\s*\(\s*(\d+)\s*\)/i';

    private const DATE_COLUMN = 2;

    private const TIME_COLUMN = 4;

    private const STATUS_COLUMN = 6;

    /**
     * @return array{
     *     rows: array<int, array{employee_number: string, employee_name: string, actual_date: string, punch_time: string, is_in: bool}>,
     *     errors: array<int, string>
     * }
     */
    public function parse(UploadedFile $file): array
    {
        $matrix = $this->readSpreadsheetRows($file);
        $rows = [];
        $errors = [];
        $lineNumber = 0;

        for ($index = 0; $index < count($matrix); $index++) {
            $cells = $this->normalizeRow($matrix[$index]);
            $employeeCell = $this->firstNonEmptyCell($cells);

            if ($employeeCell === null || ! preg_match(self::EMPLOYEE_PATTERN, $employeeCell, $matches)) {
                continue;
            }

            $employeeName = trim((string) ($matches[1] ?? ''));
            $employeeNumber = trim((string) ($matches[2]));
            $headerIndex = $this->findPunchHeaderIndex($matrix, $index + 1);

            if ($headerIndex === null) {
                $errors[] = 'Employee block for '.$employeeName.' ('.$employeeNumber.') is missing Date/Time/Status headers.';

                continue;
            }

            $punches = [];
            $currentDate = null;

            for ($rowIndex = $headerIndex + 1; $rowIndex < count($matrix); $rowIndex++) {
                $punchCells = $this->normalizeRow($matrix[$rowIndex]);

                if ($this->isEmployeeRow($punchCells)) {
                    $index = $rowIndex - 1;

                    break;
                }

                if ($this->isBlankRow($punchCells)) {
                    continue;
                }

                $rawDate = trim((string) ($punchCells[self::DATE_COLUMN] ?? ''));

                if ($rawDate !== '') {
                    $parsedDate = $this->normalizeDate($rawDate);

                    if ($parsedDate === null) {
                        $lineNumber++;
                        $errors[] = "Line {$lineNumber}: Invalid date ({$rawDate}) for {$employeeName} ({$employeeNumber}).";

                        continue;
                    }

                    $currentDate = $parsedDate;
                }

                $status = strtolower(trim((string) ($punchCells[self::STATUS_COLUMN] ?? '')));
                $rawTime = trim((string) ($punchCells[self::TIME_COLUMN] ?? ''));

                if ($currentDate === null || $rawTime === '' || ! in_array($status, ['in', 'out'], true)) {
                    continue;
                }

                $parsedTime = $this->normalizeTime($rawTime);

                if ($parsedTime === null) {
                    $lineNumber++;
                    $errors[] = "Line {$lineNumber}: Invalid time ({$rawTime}) for {$employeeName} ({$employeeNumber}) on {$currentDate}.";

                    continue;
                }

                $punches[] = [
                    'date' => $currentDate,
                    'time' => $parsedTime,
                    'status' => $status,
                ];
            }

            foreach ($punches as $punch) {
                $lineNumber++;
                $rows[] = [
                    'employee_number' => $employeeNumber,
                    'employee_name' => $employeeName,
                    'actual_date' => $punch['date'],
                    'punch_time' => $punch['time'],
                    'is_in' => $punch['status'] === 'in',
                ];
            }
        }

        if ($rows === [] && $errors === []) {
            throw new RuntimeException('No Sumulong DTR employee punch data found in the uploaded file.');
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array<int, string>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $index => $cell) {
            $normalized[(int) $index] = $this->stringifyCell($cell);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findPunchHeaderIndex(array $matrix, int $startIndex): ?int
    {
        for ($index = $startIndex; $index < count($matrix); $index++) {
            $cells = $this->normalizeRow($matrix[$index]);
            $date = strtolower(trim((string) ($cells[self::DATE_COLUMN] ?? '')));
            $time = strtolower(trim((string) ($cells[self::TIME_COLUMN] ?? '')));
            $status = strtolower(trim((string) ($cells[self::STATUS_COLUMN] ?? '')));

            if ($date === 'date' && $time === 'time' && $status === 'status') {
                return $index;
            }

            if ($this->isEmployeeRow($cells)) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isEmployeeRow(array $cells): bool
    {
        $first = $this->firstNonEmptyCell($cells);

        return $first !== null && preg_match(self::EMPLOYEE_PATTERN, $first) === 1;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function firstNonEmptyCell(array $cells): ?string
    {
        foreach ($cells as $value) {
            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheetRows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['d/m/Y', 'm/d/Y', 'Y-m-d', 'n/j/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if ($numeric >= 0 && $numeric < 1) {
                return gmdate('H:i:s', (int) round($numeric * 86400));
            }

            if ($numeric >= 1) {
                try {
                    return ExcelDate::excelToDateTimeObject($numeric)->format('H:i:s');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
