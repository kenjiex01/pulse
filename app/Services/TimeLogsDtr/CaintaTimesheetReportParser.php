<?php

namespace App\Services\TimeLogsDtr;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class CaintaTimesheetReportParser
{
    private const EMPLOYEE_PATTERN = '/Employee:\s*(.+?)\s*\(\s*(\d+)\s*\)/i';

    private const DATE_COLUMN = 0;

    /** @var array<int, array{in: int, out: int|null}> */
    private const PUNCH_COLUMNS = [
        ['in' => 2, 'out' => 3],
        ['in' => 4, 'out' => 5],
        ['in' => 6, 'out' => 7],
        ['in' => 8, 'out' => 9],
        ['in' => 10, 'out' => null],
    ];

    /**
     * @return array{
     *     rows: array<int, array{biometric_id: string, employee_name: string, actual_date: string, punch_time: string, is_in: bool}>,
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
            $biometricId = trim((string) ($matches[2] ?? ''));
            $headerIndex = $this->findTimesheetHeaderIndex($matrix, $index + 1);

            if ($headerIndex === null) {
                $errors[] = 'Employee block for '.$employeeName.' ('.$biometricId.') is missing Date/In/Out headers.';

                continue;
            }

            for ($rowIndex = $headerIndex + 1; $rowIndex < count($matrix); $rowIndex++) {
                $punchCells = $this->normalizeRow($matrix[$rowIndex]);

                if ($this->isEmployeeRow($punchCells)) {
                    $index = $rowIndex - 1;

                    break;
                }

                if ($this->isBlankRow($punchCells) || $this->isSummaryRow($punchCells)) {
                    continue;
                }

                $rawDate = trim((string) ($punchCells[self::DATE_COLUMN] ?? ''));

                if ($rawDate === '') {
                    continue;
                }

                $parsedDate = $this->normalizeDate($rawDate);

                if ($parsedDate === null) {
                    $lineNumber++;
                    $errors[] = "Line {$lineNumber}: Invalid date ({$rawDate}) for {$employeeName} ({$biometricId}).";

                    continue;
                }

                foreach (self::PUNCH_COLUMNS as $pair) {
                    $rawIn = trim((string) ($punchCells[$pair['in']] ?? ''));
                    $rawOut = $pair['out'] === null ? '' : trim((string) ($punchCells[$pair['out']] ?? ''));

                    if ($rawIn !== '') {
                        $parsedIn = $this->normalizeTime($rawIn);

                        if ($parsedIn === null) {
                            $lineNumber++;
                            $errors[] = "Line {$lineNumber}: Invalid time ({$rawIn}) for {$employeeName} ({$biometricId}) on {$parsedDate}.";

                            continue;
                        }

                        $lineNumber++;
                        $rows[] = [
                            'biometric_id' => $biometricId,
                            'employee_name' => $employeeName,
                            'actual_date' => $parsedDate,
                            'punch_time' => $parsedIn,
                            'is_in' => true,
                        ];
                    }

                    if ($rawOut !== '') {
                        $parsedOut = $this->normalizeTime($rawOut);

                        if ($parsedOut === null) {
                            $lineNumber++;
                            $errors[] = "Line {$lineNumber}: Invalid time ({$rawOut}) for {$employeeName} ({$biometricId}) on {$parsedDate}.";

                            continue;
                        }

                        $lineNumber++;
                        $rows[] = [
                            'biometric_id' => $biometricId,
                            'employee_name' => $employeeName,
                            'actual_date' => $parsedDate,
                            'punch_time' => $parsedOut,
                            'is_in' => false,
                        ];
                    }
                }
            }
        }

        if ($rows === [] && $errors === []) {
            throw new RuntimeException('No Cainta timesheet punch data found in the uploaded file.');
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     */
    private function findTimesheetHeaderIndex(array $matrix, int $startIndex): ?int
    {
        for ($index = $startIndex; $index < count($matrix); $index++) {
            $cells = $this->normalizeRow($matrix[$index]);
            $date = strtolower(trim((string) ($cells[self::DATE_COLUMN] ?? '')));
            $inOne = strtolower(trim((string) ($cells[2] ?? '')));

            if ($date === 'date' && str_starts_with($inOne, 'in')) {
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
    private function isSummaryRow(array $cells): bool
    {
        $first = strtolower(trim((string) ($cells[self::DATE_COLUMN] ?? '')));

        return str_starts_with($first, 'total:')
            || str_starts_with($first, 'days present:')
            || str_starts_with($first, 'days absent:');
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

        foreach (['m/d/Y', 'd/m/Y', 'Y-m-d', 'n/j/Y', 'd-m-Y'] as $format) {
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
