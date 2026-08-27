<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
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
     * @return array<string, array<string, mixed>>
     */
    public function uploadTypes(): array
    {
        return (array) config('employee_upload.upload_types', []);
    }

    public function normalizeUploadType(string $uploadType): string
    {
        return array_key_exists($uploadType, $this->uploadTypes()) ? $uploadType : 'master-file';
    }

    private function configNameFor(string $uploadType): string
    {
        $meta = $this->uploadTypes()[$this->normalizeUploadType($uploadType)] ?? [];

        return (string) ($meta['config'] ?? 'employee_upload');
    }

    /**
     * @return array<int, array{alias: string, label: string}>
     */
    public function columns(string $uploadType = 'master-file'): array
    {
        return (array) config($this->configNameFor($uploadType).'.columns', []);
    }

    /**
     * @return array<int, string>
     */
    public function aliases(string $uploadType = 'master-file'): array
    {
        return array_column($this->columns($uploadType), 'alias');
    }

    /**
     * @return array<int, string>
     */
    public function labels(string $uploadType = 'master-file'): array
    {
        return array_column($this->columns($uploadType), 'label');
    }

    public function templateFilePath(string $uploadType = 'master-file'): string
    {
        $uploadType = $this->normalizeUploadType($uploadType);
        $meta = $this->uploadTypes()[$uploadType] ?? [];
        $filename = (string) ($meta['template_filename'] ?? 'employee_upload_template.xlsx');
        $cached = storage_path('app/templates/'.$filename);

        if ($uploadType === 'employee-assignment') {
            File::ensureDirectoryExists(dirname($cached));
            File::put($cached, $this->buildTemplateBinary($uploadType));

            return $cached;
        }

        $bundled = resource_path('templates/'.$filename);

        if (is_readable($bundled)) {
            return $bundled;
        }

        if (is_readable($cached)) {
            return $cached;
        }

        File::ensureDirectoryExists(dirname($cached));
        File::put($cached, $this->buildTemplateBinary($uploadType));

        return $cached;
    }

    /**
     * @return array<string, string>
     */
    public function sampleRowValues(string $uploadType = 'master-file'): array
    {
        $configName = $this->configNameFor($uploadType);
        $sample = (array) config("{$configName}.sample_row", []);
        $aliases = $this->aliases($uploadType);

        return array_merge(
            array_fill_keys($aliases, ''),
            array_intersect_key($sample, array_flip($aliases)),
        );
    }

    public function buildTemplateContent(string $uploadType = 'master-file'): string
    {
        $aliases = $this->aliases($uploadType);
        $labels = $this->labels($uploadType);
        $sample = $this->sampleRowValues($uploadType);
        $sampleRow = array_map(fn (string $alias) => $sample[$alias] ?? '', $aliases);

        return $this->formatCsvRow($aliases)."\n"
            .$this->formatCsvRow($labels)."\n"
            .$this->formatCsvRow($sampleRow)."\n";
    }

    public function buildTemplateBinary(string $uploadType = 'master-file'): string
    {
        $uploadType = $this->normalizeUploadType($uploadType);
        $aliases = $this->aliases($uploadType);
        $labels = $this->labels($uploadType);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(match ($uploadType) {
            'employee-salary' => 'Employee Salary Upload',
            'employee-assignment' => 'Employee Assignment Upload',
            default => 'Employee Upload',
        });

        $headerRows = [$aliases, $labels];

        if ($uploadType === 'employee-assignment') {
            $dataRows = $this->assignmentTemplateRows($aliases);
            $freezePane = 'A3';
            $dataRowStart = 3;
        } else {
            $sample = $this->sampleRowValues($uploadType);
            $dataRows = [array_map(fn (string $alias) => $sample[$alias] ?? '', $aliases)];
            $freezePane = 'A4';
            $dataRowStart = 4;
        }

        foreach (array_merge($headerRows, $dataRows) as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
                $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
            }
        }

        $dataRowEnd = max($dataRowStart + 150, $dataRowStart + count($dataRows) + 20);
        $lastColumn = Coordinate::stringFromColumnIndex(count($aliases));

        $sheet->getStyle("A{$dataRowStart}:{$lastColumn}{$dataRowEnd}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        $sheet->freezePane($freezePane);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<int, string>  $aliases
     * @return array<int, array<int, string>>
     */
    private function assignmentTemplateRows(array $aliases): array
    {
        $employees = Employee::query()
            ->with(['campusAssignments.campus'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->orderBy('employee_id')
            ->get();

        return $employees->map(function (Employee $employee) use ($aliases) {
            $main = $employee->campusAssignments->first(
                fn ($assignment) => (bool) $assignment->is_primary
            ) ?? $employee->campusAssignments->first();

            $values = [
                'employee_number' => (string) $employee->employee_number,
                'employee_name' => $this->assignmentEmployeeName($employee),
                'campus_code' => (string) ($main?->campus?->campus_code ?? ''),
                'campus_name' => (string) ($main?->campus?->campus_name ?? ''),
            ];

            return array_map(fn (string $alias) => $values[$alias] ?? '', $aliases);
        })->values()->all();
    }

    private function assignmentEmployeeName(Employee $employee): string
    {
        $last = trim((string) $employee->last_name);
        $given = trim(implode(' ', array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->suffix,
        ], fn ($part) => filled($part))));

        if ($last === '') {
            return $given !== '' ? $given : (string) $employee->full_name;
        }

        return $given !== '' ? "{$last}, {$given}" : $last;
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
    public function parseUploadedFile(
        UploadedFile $file,
        string $uploadType = 'master-file',
        bool $disableRequiredFields = false,
    ): array {
        $this->assertAllowedFile($file);
        $uploadType = $this->normalizeUploadType($uploadType);

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseSpreadsheetFile($file, $uploadType, $disableRequiredFields);
        }

        return $this->parseDelimitedFile($file, $uploadType, $disableRequiredFields);
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
    private function parseDelimitedFile(UploadedFile $file, string $uploadType, bool $disableRequiredFields = false): array
    {
        $aliases = $this->aliases($uploadType);
        $labels = $this->labels($uploadType);

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

        return $this->parseRowMatrix($matrix, $aliases, $labels, $file->getClientOriginalName(), $uploadType, $disableRequiredFields);
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
    private function parseSpreadsheetFile(UploadedFile $file, string $uploadType, bool $disableRequiredFields = false): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        return $this->parseRowMatrix(
            $matrix,
            $this->aliases($uploadType),
            $this->labels($uploadType),
            $file->getClientOriginalName(),
            $uploadType,
            $disableRequiredFields,
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
    private function parseRowMatrix(
        array $matrix,
        array $aliases,
        array $labels,
        string $filename,
        string $uploadType = 'master-file',
        bool $disableRequiredFields = false,
    ): array {
        $valid = [];
        $errors = [];
        $seenNumbers = [];
        $seenEmails = [];
        $seenSalaryKeys = [];
        $lineNumber = 0;
        $columnMap = null;

        foreach ($matrix as $cells) {
            $lineNumber++;

            if (! is_array($cells)) {
                continue;
            }

            $cells = array_map(fn ($cell) => $this->stringifyCell($cell), $cells);

            if ($this->isBlankRow($cells)) {
                continue;
            }

            if ($columnMap === null) {
                $columnMap = $this->resolveHeaderColumnMap($cells, $aliases, $labels, $uploadType, $disableRequiredFields);

                continue;
            }

            if ($this->resolveHeaderColumnMap($cells, $aliases, $labels, $uploadType, $disableRequiredFields) !== null) {
                continue;
            }

            $row = [];

            foreach ($aliases as $alias) {
                $index = $columnMap[$alias] ?? null;
                $row[$alias] = $index === null ? '' : trim((string) ($cells[$index] ?? ''));
            }

            $rowErrors = $this->validateRow(
                $row,
                $lineNumber,
                $seenNumbers,
                $seenEmails,
                $seenSalaryKeys,
                $uploadType,
                $disableRequiredFields,
            );

            if ($rowErrors['errors'] !== []) {
                foreach ($rowErrors['errors'] as $message) {
                    $errors[] = $message;
                }

                continue;
            }

            $valid[] = $rowErrors['payload'];
        }

        if ($columnMap === null) {
            $message = match ($uploadType) {
                'employee-salary' => 'Invalid salary upload template header. Download the latest Employee Salary template from Employees → Upload.',
                'employee-assignment' => 'Invalid assignment upload template header. Download the latest Employee Assignment template from Employees → Upload.',
                default => 'Invalid template header. Download the latest template from Employees → Upload (or keep employee_number and email columns).',
            };

            throw ValidationException::withMessages([
                'upload_file' => $message,
            ]);
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'filename' => $filename,
            'valid_count' => count($valid),
            'error_count' => count($errors),
            'upload_type' => $uploadType,
            'disable_required_fields' => $disableRequiredFields,
        ];
    }

    /**
     * Map header cells to current template aliases.
     * Supports legacy aliases such as salary_date_effective → salary_date_effective_from.
     *
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $aliases
     * @param  array<int, string>  $labels
     * @return array<string, int>|null
     */
    private function resolveHeaderColumnMap(
        array $cells,
        array $aliases,
        array $labels,
        string $uploadType = 'master-file',
        bool $disableRequiredFields = false,
    ): ?array {
        $aliasByNormalized = [];

        foreach ($aliases as $alias) {
            $aliasByNormalized[$this->normalizeHeaderKey($alias)] = $alias;
        }

        foreach ($labels as $index => $label) {
            $alias = $aliases[$index] ?? null;
            if ($alias === null) {
                continue;
            }

            $aliasByNormalized[$this->normalizeHeaderKey($label)] = $alias;
        }

        foreach ($this->legacyHeaderAliases($uploadType) as $legacy => $current) {
            $aliasByNormalized[$this->normalizeHeaderKey($legacy)] = $current;
        }

        $map = [];

        foreach ($cells as $index => $cell) {
            $key = $this->normalizeHeaderKey((string) $cell);
            if ($key === '' || ! isset($aliasByNormalized[$key])) {
                continue;
            }

            $alias = $aliasByNormalized[$key];
            if (! array_key_exists($alias, $map)) {
                $map[$alias] = (int) $index;
            }
        }

        foreach ($this->requiredHeaderAliases($uploadType, $disableRequiredFields) as $alias) {
            if (! array_key_exists($alias, $map)) {
                return null;
            }
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function requiredHeaderAliases(string $uploadType, bool $disableRequiredFields = false): array
    {
        if ($uploadType === 'employee-salary') {
            return ['employee_number', 'date_effective_from', 'pay_type'];
        }

        if ($uploadType === 'employee-assignment') {
            return ['employee_number', 'campus_code'];
        }

        if ($disableRequiredFields) {
            return ['employee_number', 'email'];
        }

        return ['employee_number', 'first_name', 'last_name', 'email'];
    }

    /**
     * @return array<string, string>
     */
    private function legacyHeaderAliases(string $uploadType = 'master-file'): array
    {
        $legacy = [
            'salary_date_effective' => 'salary_date_effective_from',
            'salary2_date_effective' => 'salary2_date_effective_from',
            'Salary Effectivity (YYYY-MM-DD or M/D/YYYY)' => 'salary_date_effective_from',
            'Salary 2 Effectivity (YYYY-MM-DD or M/D/YYYY)' => 'salary2_date_effective_from',
        ];

        if ($uploadType === 'employee-salary') {
            $legacy['date_effective'] = 'date_effective_from';
            $legacy['Salary Effectivity From (YYYY-MM-DD or M/D/YYYY)'] = 'date_effective_from';
        }

        return $legacy;
    }

    private function normalizeHeaderKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(["\u{00A0}", "\t"], ' ', $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
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
        array &$seenSalaryKeys,
        string $uploadType = 'master-file',
        bool $disableRequiredFields = false,
    ): array {
        if ($uploadType === 'employee-salary') {
            return $this->rowMapper->mapSalaryUploadRow($row, $lineNumber, $seenSalaryKeys);
        }

        if ($uploadType === 'employee-assignment') {
            return $this->rowMapper->mapAssignmentUploadRow($row, $lineNumber, $seenNumbers);
        }

        return $this->rowMapper->mapRow($row, $lineNumber, $seenNumbers, $seenEmails, $disableRequiredFields);
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
     * @return array{created: int, updated: int, employee_ids: array<int, int>}
     */
    public function commit(User $user, string $token): array
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to import.');
        }

        if (($staging['upload_type'] ?? 'master-file') === 'employee-salary') {
            return $this->commitSalaryUpload($user, $token, $staging);
        }

        if (($staging['upload_type'] ?? 'master-file') === 'employee-assignment') {
            return $this->commitAssignmentUpload($user, $token, $staging);
        }

        $createdIds = [];
        $updatedIds = [];

        DB::transaction(function () use ($staging, &$createdIds, &$updatedIds) {
            foreach ($staging['valid'] as $payload) {
                $existingId = (int) ($payload['existing_employee_id'] ?? 0);
                $isUpdate = $existingId > 0;
                $employeeAttributes = (array) ($payload['employee'] ?? []);

                if ($isUpdate) {
                    $employee = Employee::query()->findOrFail($existingId);
                    $oldSnapshot = $employee->logSnapshot();

                    if ($employeeAttributes !== []) {
                        $employee->fill($employeeAttributes);
                        $employee->save();
                    }

                    if (! empty($payload['sync_employment'])) {
                        EmployeeEmploymentSync::sync($employee, $payload['employment_informations'] ?? []);
                    }

                    if (! empty($payload['sync_campus'])) {
                        EmployeeCampusAssignmentSync::sync($employee, $payload['campus_assignments'] ?? []);
                    }

                    if (! empty($payload['sync_salary'])) {
                        EmployeeSalarySync::sync(
                            $employee,
                            $payload['employee_salaries'] ?? [],
                            (bool) ($payload['is_hybrid'] ?? $employee->is_hybrid),
                        );
                    }

                    SysLogService::record(
                        action: 'edit',
                        table: 'tbl_employees',
                        recordId: $employee->employee_id,
                        oldValues: $oldSnapshot,
                        newValues: $employee->fresh()->logSnapshot(),
                        description: 'Updated employee via upload: '.$employee->employee_number,
                    );

                    $updatedIds[] = $employee->employee_id;

                    continue;
                }

                $employee = Employee::query()->create($employeeAttributes);

                if (! empty($payload['sync_employment']) || empty($payload['disable_required_fields'])) {
                    EmployeeEmploymentSync::sync($employee, $payload['employment_informations'] ?? []);
                }

                if (! empty($payload['sync_campus']) || empty($payload['disable_required_fields'])) {
                    EmployeeCampusAssignmentSync::sync($employee, $payload['campus_assignments'] ?? []);
                }

                if (! empty($payload['sync_salary']) || empty($payload['disable_required_fields'])) {
                    EmployeeSalarySync::sync(
                        $employee,
                        $payload['employee_salaries'] ?? [],
                        (bool) ($payload['is_hybrid'] ?? false),
                    );
                }

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
            'updated' => count($updatedIds),
            'employee_ids' => array_values(array_unique([...$createdIds, ...$updatedIds])),
        ];
    }

    /**
     * @param  array<string, mixed>  $staging
     * @return array{updated: int, employee_ids: array<int, int>}
     */
    private function commitSalaryUpload(User $user, string $token, array $staging): array
    {
        $employeeIds = [];

        DB::transaction(function () use ($staging, &$employeeIds) {
            foreach ($staging['valid'] as $payload) {
                $employment = EmployeeEmploymentInformation::query()
                    ->where('employment_info_id', $payload['employment_info_id'])
                    ->firstOrFail();

                EmployeeSalarySync::syncForEmployment($employment, $payload['salary']);

                SysLogService::record(
                    action: 'update',
                    table: 'tbl_employee_salaries',
                    recordId: $employment->employment_info_id,
                    description: 'Updated salary via upload for employee '.$payload['employee_number'].' (slot '.((int) $payload['employment_index'] + 1).')',
                );

                $employeeIds[] = (int) $payload['employee_id'];
            }
        });

        $this->discardStaging($user, $token);

        return [
            'updated' => count($staging['valid']),
            'employee_ids' => array_values(array_unique($employeeIds)),
        ];
    }

    /**
     * @param  array<string, mixed>  $staging
     * @return array{created: int, updated: int, employee_ids: array<int, int>}
     */
    private function commitAssignmentUpload(User $user, string $token, array $staging): array
    {
        $employeeIds = [];

        DB::transaction(function () use ($staging, &$employeeIds) {
            foreach ($staging['valid'] as $payload) {
                $employee = Employee::query()->findOrFail($payload['employee_id']);
                EmployeeCampusAssignmentSync::setMainCampus($employee, (int) $payload['campus_id']);

                SysLogService::record(
                    action: 'update',
                    table: 'tbl_employee_campus_assignments',
                    recordId: $employee->employee_id,
                    description: 'Updated main campus assignment via upload for employee '.$payload['employee_number'],
                );

                $employeeIds[] = (int) $payload['employee_id'];
            }
        });

        $this->discardStaging($user, $token);

        return [
            'created' => 0,
            'updated' => count($staging['valid']),
            'employee_ids' => array_values(array_unique($employeeIds)),
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
