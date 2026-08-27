<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EmployeeLoadUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    private const TIME_PATTERN = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/';

    private const TIME_PATTERN_MERIDIEM = '/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*([AaPp][Mm])$/';

    public function __construct(private readonly EmployeeLoadTemplateService $templateService) {}

    /**
     * @return array<int, array{alias: string, label: string, prefill?: bool, editable?: bool, max?: int}>
     */
    public function columns(): array
    {
        return (array) config('employee_load.columns', []);
    }

    /**
     * @return array<int, array{alias: string, label: string}>
     */
    public function hiddenColumns(): array
    {
        return (array) config('employee_load.hidden_columns', []);
    }

    /**
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return array_merge(
            array_column($this->columns(), 'alias'),
            array_column($this->hiddenColumns(), 'alias'),
        );
    }

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return array_merge(
            array_column($this->columns(), 'label'),
            array_column($this->hiddenColumns(), 'label'),
        );
    }

    /**
     * Build the pre-filled CSV template for a date range. The enrollment period
     * (loading) is resolved automatically from the selected dates.
     */
    public function buildTemplateContent(string $dateFrom, string $dateTo): string
    {
        $aliases = $this->aliases();
        $labels = $this->labels();

        $content = $this->formatCsvRow($aliases)."\n"
            .$this->formatCsvRow($labels)."\n";

        $rows = $this->templateService->buildRows($dateFrom, $dateTo);

        foreach ($rows as $row) {
            $line = [];

            foreach ($aliases as $alias) {
                $line[] = (string) ($row[$alias] ?? '');
            }

            $content .= $this->formatCsvRow($line)."\n";
        }

        return $content;
    }

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     warnings: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    public function parseUploadedFile(UploadedFile $file, string $dateFrom, string $dateTo): array
    {
        $this->assertTextFile($file);

        $aliases = $this->aliases();
        $labels = $this->labels();

        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $valid = [];
        $errors = [];
        $warnings = [];
        $seenKeys = [];
        $lineNumber = 0;
        $delimiter = ',';

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $delimiter = $this->detectDelimiter($line);
                }

                $cells = $this->splitLine($line, $delimiter);

                if ($lineNumber === 1) {
                    if (count($cells) !== count($aliases)) {
                        throw new RuntimeException('Fields and columns are not equal. Use the downloaded template as-is.');
                    }

                    foreach ($cells as $index => $cell) {
                        if (trim($cell) !== $aliases[$index]) {
                            throw new RuntimeException('Fields from the uploaded file do not match the template.');
                        }
                    }

                    continue;
                }

                if ($this->matchesRow($cells, $labels)) {
                    continue;
                }

                if ($this->isBlankRow($cells)) {
                    continue;
                }

                if (count($cells) !== count($aliases)) {
                    throw new RuntimeException('Details did not match with the number of fields at line '.$lineNumber.'.');
                }

                $row = [];

                foreach ($aliases as $index => $alias) {
                    $row[$alias] = trim($cells[$index] ?? '');
                }

                $parsed = $this->validateRow($row, $lineNumber, $from, $to, $seenKeys, $errors, $warnings);

                if ($parsed !== null) {
                    $valid[] = $parsed;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineNumber < 2) {
            throw new RuntimeException('The uploaded file must include the template header row and at least one data row.');
        }

        if ($valid === [] && $errors === []) {
            throw new RuntimeException('No data rows found for uploading.');
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'filename' => $file->getClientOriginalName(),
            'valid_count' => count($valid),
            'error_count' => count($errors),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenKeys
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>|null
     */
    private function validateRow(array $row, int $lineNumber, CarbonImmutable $from, CarbonImmutable $to, array &$seenKeys, array &$errors, array &$warnings): ?array
    {
        $hasError = false;

        $offeringId = trim($row['skolaris_offering_id'] ?? '');
        $sessionIso = trim($row['session_date_iso'] ?? '');

        if ($offeringId === '' || ! ctype_digit($offeringId)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Missing or invalid Offering ID (do not edit hidden columns).";
        }

        $sessionDate = null;

        if ($sessionIso === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Missing Session Date (do not edit hidden columns).";
        } else {
            try {
                $sessionDate = CarbonImmutable::parse($sessionIso)->startOfDay();
            } catch (\Throwable) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid Session Date ({$sessionIso}).";
            }

            if ($sessionDate !== null && ($sessionDate->lessThan($from) || $sessionDate->greaterThan($to))) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Session Date {$sessionIso} is outside the selected date range.";
            }
        }

        foreach (['faculty_name', 'subject', 'section', 'load_date', 'class_schedule'] as $requiredPrefill) {
            if (trim($row[$requiredPrefill] ?? '') === '') {
                $hasError = true;
                $label = ucwords(str_replace('_', ' ', $requiredPrefill));
                $errors[] = "Line {$lineNumber}: {$label} is required (do not clear pre-filled columns).";
            }
        }

        $timeIn = $this->normalizeTime($row['time_in'] ?? '', 'Time In', $lineNumber, $errors, $hasError);
        $timeOut = $this->normalizeTime($row['time_out'] ?? '', 'Time Out', $lineNumber, $errors, $hasError);

        foreach (['remarks' => 255, 'comments' => 255, 'verification_remarks' => 255] as $field => $max) {
            if (strlen((string) ($row[$field] ?? '')) > $max) {
                $hasError = true;
                $label = ucwords(str_replace('_', ' ', $field));
                $errors[] = "Line {$lineNumber}: {$label} exceeds maximum length of {$max}.";
            }
        }

        if (! $hasError && $sessionDate !== null) {
            $dupKey = $offeringId.'|'.$sessionDate->toDateString();

            if (isset($seenKeys[$dupKey])) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Duplicate row for the same offering and date.";
            } else {
                $seenKeys[$dupKey] = true;
            }
        }

        if ($hasError) {
            return null;
        }

        $employeeNumber = trim($row['employee_number'] ?? '');
        $employeeId = null;

        if ($employeeNumber !== '') {
            $employeeId = Employee::query()
                ->where('employee_number', $employeeNumber)
                ->value('employee_id');

            if ($employeeId === null) {
                $warnings[] = "Line {$lineNumber}: Employee No. {$employeeNumber} not found in People360 — stored without an employee link.";
            }
        }

        return [
            'employee_id' => $employeeId,
            'employee_number' => $employeeNumber !== '' ? $employeeNumber : null,
            'skolaris_offering_id' => (int) $offeringId,
            'faculty_name' => $row['faculty_name'] ?? null,
            'college' => $row['college'] ?? null,
            'modality' => $row['modality'] ?? null,
            'subject' => $row['subject'] ?? null,
            'section' => $row['section'] ?? null,
            'load_date' => $row['load_date'] ?? null,
            'session_date' => $sessionDate?->toDateString(),
            'class_schedule' => $row['class_schedule'] ?? null,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'remarks' => $row['remarks'] !== '' ? $row['remarks'] : null,
            'comments' => $row['comments'] !== '' ? $row['comments'] : null,
            'verification_remarks' => $row['verification_remarks'] !== '' ? $row['verification_remarks'] : null,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function normalizeTime(string $value, string $label, int $lineNumber, array &$errors, bool &$hasError): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match(self::TIME_PATTERN, $value) === 1 || preg_match(self::TIME_PATTERN_MERIDIEM, $value) === 1) {
            return CarbonImmutable::parse($value)->format('H:i:s');
        }

        $hasError = true;
        $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}). Use HH:MM or h:MM AM/PM.";

        return null;
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, array $context, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
            'enrollment_period_id' => $context['enrollment_period_id'] ?? null,
            'enrollment_period_label' => $context['enrollment_period_label'] ?? null,
            'date_from' => $context['date_from'] ?? null,
            'date_to' => $context['date_to'] ?? null,
            'filename' => $parseResult['filename'],
            'valid' => $parseResult['valid'],
            'errors' => $parseResult['errors'],
            'warnings' => $parseResult['warnings'] ?? [],
            'valid_count' => $parseResult['valid_count'],
            'error_count' => $parseResult['error_count'],
        ], now()->addMinutes(self::STAGING_TTL_MINUTES));

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

    public function commit(User $user, string $token): RawEmployeeLoadTransaction
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $rows = $staging['valid'];

        return DB::transaction(function () use ($user, $staging, $rows, $token) {
            $sessionDates = array_filter(array_column($rows, 'session_date'));

            $transaction = RawEmployeeLoadTransaction::query()->create([
                'batch_no' => $this->nextBatchNumber(),
                'filename' => $staging['filename'],
                'enrollment_period_id' => $staging['enrollment_period_id'] ?? null,
                'enrollment_period_label' => $staging['enrollment_period_label'] ?? null,
                'dt_from' => $staging['date_from'] ?? (empty($sessionDates) ? null : min($sessionDates)),
                'dt_to' => $staging['date_to'] ?? (empty($sessionDates) ? null : max($sessionDates)),
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
            ]);

            foreach ($rows as $row) {
                RawEmployeeLoadEntry::query()->create(array_merge($row, [
                    'employee_load_transaction_id' => $transaction->employee_load_transaction_id,
                ]));
            }

            $this->discardStaging($user, $token);

            return $transaction->fresh(['uploadedBy']);
        });
    }

    private function assertTextFile(UploadedFile $file): void
    {
        if ($file->getSize() > (int) config('uploads.max_file_kb', 15360) * 1024) {
            throw new RuntimeException('File size exceeds the maximum limit of 15 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getClientMimeType());
        $allowedExtensions = ['txt', 'csv', ''];
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];

        if (! in_array($extension, $allowedExtensions, true) && ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('File should be in CSV (*.csv) or tab-delimited text (*.txt) format.');
        }
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
        if ($expected === [] || count($cells) !== count($expected)) {
            return false;
        }

        foreach ($cells as $index => $cell) {
            if (trim($cell) !== trim($expected[$index] ?? '')) {
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

    private function nextBatchNumber(): int
    {
        $last = RawEmployeeLoadTransaction::query()->withTrashed()->max('batch_no');

        return ((int) $last) + 1;
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "employee_load_staging:{$userId}:{$token}";
    }
}
