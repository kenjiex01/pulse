<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class EmployeeUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    public function __construct(private readonly EmployeeUploadRowMapper $rowMapper) {}

    /**
     * @return array<int, array{alias: string, label: string}>
     */
    public function columns(): array
    {
        return (array) config('employee_upload.columns', []);
    }

    /**
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return array_column($this->columns(), 'alias');
    }

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return array_column($this->columns(), 'label');
    }

    public function templateFilePath(): string
    {
        $bundled = resource_path('templates/employee_upload_template.xlsx');

        if (is_readable($bundled)) {
            return $bundled;
        }

        $cached = storage_path('app/templates/employee_upload_template.xlsx');

        if (is_readable($cached)) {
            return $cached;
        }

        File::ensureDirectoryExists(dirname($cached));
        File::put($cached, $this->buildTemplateBinary());

        return $cached;
    }

    /**
     * @return array<string, string>
     */
    public function sampleRowValues(): array
    {
        $sample = (array) config('employee_upload.sample_row', []);
        $aliases = $this->aliases();

        return array_merge(
            array_fill_keys($aliases, ''),
            array_intersect_key($sample, array_flip($aliases)),
        );
    }

    public function buildTemplateContent(): string
    {
        $aliases = $this->aliases();
        $labels = $this->labels();
        $sample = $this->sampleRowValues();
        $sampleRow = array_map(fn (string $alias) => $sample[$alias] ?? '', $aliases);

        return $this->formatCsvRow($aliases)."\n"
            .$this->formatCsvRow($labels)."\n"
            .$this->formatCsvRow($sampleRow)."\n";
    }

    public function buildTemplateBinary(): string
    {
        $aliases = $this->aliases();
        $labels = $this->labels();
        $sample = $this->sampleRowValues();
        $sampleRow = array_map(fn (string $alias) => $sample[$alias] ?? '', $aliases);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employee Upload');

        foreach ([$aliases, $labels, $sampleRow] as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
                $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
            }
        }

        $dataRowStart = 4;
        $dataRowEnd = 150;
        $lastColumn = Coordinate::stringFromColumnIndex(count($aliases));

        $sheet->getStyle("A{$dataRowStart}:{$lastColumn}{$dataRowEnd}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        $sheet->freezePane('A4');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return (string) ob_get_clean();
    }

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $this->assertAllowedFile($file);

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseSpreadsheetFile($file);
        }

        return $this->parseDelimitedFile($file);
    }

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    private function parseDelimitedFile(UploadedFile $file): array
    {
        $aliases = $this->aliases();
        $labels = $this->labels();

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $matrix = [];

        while (($rawLine = fgets($handle)) !== false) {
            $delimiter = $this->detectDelimiter($rawLine);
            $matrix[] = $this->splitLine($rawLine, $delimiter);
        }

        fclose($handle);

        return $this->parseRowMatrix($matrix, $aliases, $labels, $file->getClientOriginalName());
    }

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    private function parseSpreadsheetFile(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        return $this->parseRowMatrix(
            $matrix,
            $this->aliases(),
            $this->labels(),
            $file->getClientOriginalName(),
        );
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @param  array<int, string>  $aliases
     * @param  array<int, string>  $labels
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    private function parseRowMatrix(array $matrix, array $aliases, array $labels, string $filename): array
    {
        $valid = [];
        $errors = [];
        $seenNumbers = [];
        $seenEmails = [];
        $lineNumber = 0;
        $headerMatched = false;

        foreach ($matrix as $cells) {
            $lineNumber++;

            if (! is_array($cells)) {
                continue;
            }

            $cells = array_map(fn ($cell) => $this->stringifyCell($cell), $cells);

            if ($this->isBlankRow($cells)) {
                continue;
            }

            if (! $headerMatched) {
                if ($this->matchesRow($cells, $aliases) || $this->matchesRow($cells, $labels)) {
                    $headerMatched = true;
                }

                continue;
            }

            if ($this->matchesRow($cells, $aliases) || $this->matchesRow($cells, $labels)) {
                continue;
            }

            $row = [];

            foreach ($aliases as $index => $alias) {
                $row[$alias] = trim((string) ($cells[$index] ?? ''));
            }

            $rowErrors = $this->validateRow($row, $lineNumber, $seenNumbers, $seenEmails);

            if ($rowErrors['errors'] !== []) {
                foreach ($rowErrors['errors'] as $message) {
                    $errors[] = $message;
                }

                continue;
            }

            $valid[] = $rowErrors['payload'];
        }

        if (! $headerMatched) {
            throw ValidationException::withMessages([
                'upload_file' => 'Invalid template header. Download the latest template and do not change the first two header rows.',
            ]);
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'filename' => $filename,
            'valid_count' => count($valid),
            'error_count' => count($errors),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenNumbers
     * @param  array<string, bool>  $seenEmails
     * @return array{errors: array<int, string>, payload: array<string, mixed>|null}
     */
    private function validateRow(
        array $row,
        int $lineNumber,
        array &$seenNumbers,
        array &$seenEmails,
    ): array {
        return $this->rowMapper->mapRow($row, $lineNumber, $seenNumbers, $seenEmails);
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), $parseResult, now()->addMinutes(self::STAGING_TTL_MINUTES));

        return $token;
    }

    public function discardStaging(User $user, string $token): void
    {
        Cache::forget($this->stagingCacheKey($user->id, $token));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStaging(User $user, string $token): ?array
    {
        $payload = Cache::get($this->stagingCacheKey($user->id, $token));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array{created: int, employee_ids: array<int, int>}
     */
    public function commit(User $user, string $token): array
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to import.');
        }

        $createdIds = [];

        DB::transaction(function () use ($staging, &$createdIds) {
            foreach ($staging['valid'] as $payload) {
                $employee = Employee::query()->create($payload['employee']);

                EmployeeEmploymentSync::sync($employee, $payload['employment_informations']);
                EmployeeCampusAssignmentSync::sync($employee, $payload['campus_assignments']);
                EmployeeSalarySync::sync(
                    $employee,
                    $payload['employee_salaries'],
                    (bool) ($payload['is_hybrid'] ?? false),
                );

                SysLogService::record(
                    action: 'create',
                    table: 'tbl_employees',
                    recordId: $employee->employee_id,
                    newValues: $employee->fresh()->logSnapshot(),
                    description: 'Imported employee via upload: '.$employee->employee_number,
                );

                $createdIds[] = $employee->employee_id;
            }
        });

        $this->discardStaging($user, $token);

        return [
            'created' => count($createdIds),
            'employee_ids' => $createdIds,
        ];
    }

    private function assertAllowedFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getClientMimeType());
        $allowedExtensions = ['txt', 'csv', 'xlsx', 'xls', ''];
        $allowedMimes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (! in_array($extension, $allowedExtensions, true) && ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('File should be Excel (*.xlsx), CSV (*.csv), or tab-delimited text (*.txt).');
        }
    }

    private function stringifyCell(mixed $cell): string
    {
        if ($cell === null || $cell === '') {
            return '';
        }

        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }

        if (is_int($cell) || is_float($cell)) {
            $numeric = (float) $cell;

            if ($numeric >= 1 && $numeric <= 2958465 && floor($numeric) == $numeric) {
                try {
                    return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
                } catch (\Throwable) {
                    // Fall through to string cast.
                }
            }

            if (is_float($cell)) {
                $formatted = rtrim(rtrim(sprintf('%.10F', $cell), '0'), '.');

                return $formatted === '' ? '0' : $formatted;
            }

            return (string) $cell;
        }

        return trim((string) $cell);
    }

    /**
     * @return array<int, string>
     */
    private function splitLine(string $line, string $delimiter = ','): array
    {
        $line = rtrim($line, "\r\n");

        return $line === '' ? [''] : str_getcsv($line, $delimiter);
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, "\t") > substr_count($line, ',') ? "\t" : ',';
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $expected
     */
    private function matchesRow(array $cells, array $expected): bool
    {
        if ($expected === [] || count($cells) < count($expected)) {
            return false;
        }

        foreach ($expected as $index => $expectedCell) {
            if (trim((string) ($cells[$index] ?? '')) !== trim($expectedCell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function formatCsvRow(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return implode(',', $cells);
        }

        fputcsv($handle, $cells);
        rewind($handle);
        $line = stream_get_contents($handle) ?: '';
        fclose($handle);

        return rtrim($line, "\n");
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "employee_upload_staging:{$userId}:{$token}";
    }
}
