<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTimeLog;
use App\Models\RawTimekeepingTransaction;
use App\Models\TimeCaptureFormat;
use App\Models\TimeCode;
use App\Models\User;
use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TimeLogsUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    private const DATE_PATTERN = '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/';

    private const DATE_PATTERN_US = '/^([0]?[1-9]|1[0-2])\/([0]?[1-9]|1\d|2\d|3[01])\/(19|20)\d{2}$/';

    private const TIME_PATTERN = '/^([0]?[0-9]|[0-1][0-9]|[2][0-3]):([0-5][0-9])$/';

    private const TIME_PATTERN_SECONDS = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/';

    /**
     * @return array<int, string>
     */
    public function orderedFieldNames(TimeCaptureFormat $format): array
    {
        return $format->fields
            ->sortBy('column')
            ->pluck('field_name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function humanHeaders(TimeCaptureFormat $format): array
    {
        return $format->fields
            ->sortBy('column')
            ->map(fn ($field) => filled($field->description)
                ? $field->description
                : str_replace('_', ' ', ucwords(str_replace('_', ' ', $field->field_name))))
            ->values()
            ->all();
    }

    public function buildTemplateContent(TimeCaptureFormat $format): string
    {
        $fieldNames = $this->orderedFieldNames($format);
        $headers = $this->humanHeaders($format);

        return implode("\t", $fieldNames)."\n"
            .implode("\t", $headers)."\n";
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
    public function parseUploadedFile(UploadedFile $file, TimeCaptureFormat $format): array
    {
        $this->assertTextFile($file);

        $format->loadMissing('fields');
        $expectedFields = $this->orderedFieldNames($format);

        if ($expectedFields === []) {
            throw new RuntimeException('The selected format has no field mappings.');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $valid = [];
        $errors = [];
        $lineNumber = 0;
        $fieldHeader = null;
        $titleHeader = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $cells = $this->splitLine($line);

                if ($lineNumber === 1) {
                    $fieldHeader = $cells;

                    if (count($fieldHeader) !== count($expectedFields)) {
                        throw new RuntimeException('Fields and columns are not equal.');
                    }

                    foreach ($fieldHeader as $index => $cell) {
                        if (trim($cell) !== $expectedFields[$index]) {
                            throw new RuntimeException('Fields from the uploaded file do not match the selected format type.');
                        }
                    }

                    continue;
                }

                if ($lineNumber === 2) {
                    $titleHeader = $cells;

                    if (count($titleHeader) !== count($expectedFields)) {
                        throw new RuntimeException('Header did not match the number of fields.');
                    }

                    continue;
                }

                if ($this->isBlankRow($cells)) {
                    continue;
                }

                if (count($cells) !== count($expectedFields)) {
                    throw new RuntimeException('Details did not match with the number of fields at line '.$lineNumber.'.');
                }

                $row = [];

                foreach ($expectedFields as $index => $fieldName) {
                    $row[$fieldName] = trim($cells[$index] ?? '');
                }

                $parsed = $this->validateRow($format, $row, $lineNumber, $errors);

                if ($parsed !== null) {
                    $valid[] = $parsed;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineNumber < 3) {
            throw new RuntimeException('The uploaded file must include field names, column headers, and at least one data row.');
        }

        if ($valid === [] && $errors === []) {
            throw new RuntimeException('No data rows found for uploading.');
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'filename' => $file->getClientOriginalName(),
            'valid_count' => count($valid),
            'error_count' => count($errors),
        ];
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, int $formatId, array $parseResult, string $tab, ?int $campusId = null): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
            'parser' => 'format',
            'tab' => $tab,
            'campus_id' => $campusId,
            'format_id' => $formatId,
            'filename' => $parseResult['filename'],
            'valid' => $parseResult['valid'],
            'errors' => $parseResult['errors'],
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

    /**
     * @return array{
     *     transaction: RawTimekeepingTransaction,
     *     inserted: int,
     *     skipped_duplicates: int
     * }
     */
    public function commit(User $user, string $token): array
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['parser'] ?? 'format') === 'dtr' || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $format = TimeCaptureFormat::query()->with('fields')->findOrFail((int) $staging['format_id']);
        $fieldNames = $this->orderedFieldNames($format);
        $rows = $staging['valid'];
        $tab = (string) ($staging['tab'] ?? 'time-in-out');
        $transactionTypeId = \App\Support\TimeLogs::transactionTypeId($tab);

        return DB::transaction(function () use ($user, $format, $fieldNames, $rows, $staging, $token, $transactionTypeId) {
            $dateRange = $this->resolveDateRange($format, $rows);

            $transaction = RawTimekeepingTransaction::query()->create([
                'timekeeping_transaction_type_id' => $transactionTypeId,
                'dt_from' => $dateRange['from'],
                'dt_to' => $dateRange['to'],
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
                'batch_no' => $this->nextBatchNumber(),
                'filename' => $staging['filename'],
                'timecapture_format_id' => $format->timecapture_format_id,
                'campus_id' => $staging['campus_id'] ?? null,
            ]);

            $persisted = $this->persistRows($format, $fieldNames, $rows, $transaction);

            if ($persisted['inserted'] === 0) {
                throw new RuntimeException('All records are duplicates of existing time logs and were not saved.');
            }

            $this->discardStaging($user, $token);

            return [
                'transaction' => $transaction->fresh(['uploadedBy', 'timeCaptureFormat', 'campus']),
                'inserted' => $persisted['inserted'],
                'skipped_duplicates' => $persisted['skipped_duplicates'],
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $fieldNames
     * @return array{inserted: int, skipped_duplicates: int}
     */
    private function persistRows(TimeCaptureFormat $format, array $fieldNames, array $rows, RawTimekeepingTransaction $transaction): array
    {
        $inOutRows = [];
        $batchFingerprints = [];
        $skippedDuplicates = 0;
        $roles = TimeCaptureFormatSupport::fieldRoles($format);

        foreach ($rows as $row) {
            if ($roles['reason'] !== null) {
                $dateField = TimeCaptureFormatSupport::requireFieldRole($format, 'date');
                $timeInField = $roles['time_in'];
                $timeOutField = $roles['time_out'];

                if ($this->timeLogAlreadyExists($row, $dateField, $timeInField, $timeOutField)) {
                    $skippedDuplicates++;

                    continue;
                }

                RawTimekeepingTimeLog::query()->create([
                    'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                    'employee_id' => $row['employee_id'],
                    'time_in' => $timeInField ? $this->normalizeTimeValue($row[$timeInField] ?? null) : null,
                    'time_out' => $timeOutField ? $this->normalizeTimeValue($row[$timeOutField] ?? null) : null,
                    'date_out' => $this->normalizeDateValue($row[$dateField] ?? null),
                    'time_code_id' => $row['time_code_id'] ?? null,
                ]);

                if ($timeInField !== null || $timeOutField !== null) {
                    [$timeIn, $timeOut] = $this->resolveSeparateDateTimes($format, $row);

                    if ($timeInField !== null) {
                        $skippedDuplicates += $this->queueInOutRow(
                            $inOutRows,
                            $batchFingerprints,
                            (int) $row['employee_id'],
                            $timeIn,
                            true,
                            $transaction->timekeeping_transaction_id,
                        );
                    }

                    if ($timeOutField !== null) {
                        $skippedDuplicates += $this->queueInOutRow(
                            $inOutRows,
                            $batchFingerprints,
                            (int) $row['employee_id'],
                            $timeOut,
                            false,
                            $transaction->timekeeping_transaction_id,
                        );
                    }
                }

                continue;
            }

            if ($roles['worktime'] !== null) {
                $worktimeField = TimeCaptureFormatSupport::requireFieldRole($format, 'worktime');
                $isIn = $this->resolveIndicator($format, (string) ($row['is_in'] ?? ''));

                $skippedDuplicates += $this->queueInOutRow(
                    $inOutRows,
                    $batchFingerprints,
                    (int) $row['employee_id'],
                    $this->combineDateTime(
                        $this->resolveRowDateString($format, $row),
                        (string) $row[$worktimeField],
                    ),
                    $isIn,
                    $transaction->timekeeping_transaction_id,
                );

                continue;
            }

            if ($roles['time_in'] !== null || $roles['time_out'] !== null) {
                [$timeIn, $timeOut] = $this->resolveSeparateDateTimes($format, $row);

                if ($roles['time_in'] !== null) {
                    $skippedDuplicates += $this->queueInOutRow(
                        $inOutRows,
                        $batchFingerprints,
                        (int) $row['employee_id'],
                        $timeIn,
                        true,
                        $transaction->timekeeping_transaction_id,
                    );
                }

                if ($roles['time_out'] !== null) {
                    $skippedDuplicates += $this->queueInOutRow(
                        $inOutRows,
                        $batchFingerprints,
                        (int) $row['employee_id'],
                        $timeOut,
                        false,
                        $transaction->timekeeping_transaction_id,
                    );
                }
            }
        }

        $filtered = $this->filterExistingInOutRows($inOutRows);
        $skippedDuplicates += $filtered['skipped'];

        foreach ($filtered['rows'] as $inOutRow) {
            RawTimekeepingInandout::query()->create($inOutRow);
        }

        return [
            'inserted' => count($filtered['rows']),
            'skipped_duplicates' => $skippedDuplicates,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $inOutRows
     * @param  array<string, true>  $batchFingerprints
     */
    private function queueInOutRow(
        array &$inOutRows,
        array &$batchFingerprints,
        int $employeeId,
        Carbon $dtDatetime,
        bool $isIn,
        int $transactionId,
    ): int {
        $fingerprint = $this->inOutFingerprint($employeeId, $dtDatetime, $isIn);

        if (isset($batchFingerprints[$fingerprint])) {
            return 1;
        }

        $batchFingerprints[$fingerprint] = true;
        $inOutRows[] = [
            'timekeeping_transaction_id' => $transactionId,
            'employee_id' => $employeeId,
            'dt_datetime' => $dtDatetime,
            'is_in' => $isIn,
        ];

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inOutRows
     * @return array{rows: array<int, array<string, mixed>>, skipped: int}
     */
    private function filterExistingInOutRows(array $inOutRows): array
    {
        if ($inOutRows === []) {
            return ['rows' => [], 'skipped' => 0];
        }

        $employeeIds = array_values(array_unique(array_map(
            fn (array $row) => (int) $row['employee_id'],
            $inOutRows,
        )));

        $dateTimes = collect($inOutRows)->map(fn (array $row) => Carbon::parse($row['dt_datetime']));
        $rangeStart = $dateTimes->min()->copy()->startOfDay();
        $rangeEnd = $dateTimes->max()->copy()->endOfDay();

        $existingFingerprints = RawTimekeepingInandout::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('dt_datetime', [$rangeStart, $rangeEnd])
            ->get()
            ->mapWithKeys(fn (RawTimekeepingInandout $record) => [
                $this->inOutFingerprint(
                    (int) $record->employee_id,
                    Carbon::parse($record->dt_datetime),
                    (bool) $record->is_in,
                ) => true,
            ])
            ->all();

        $filtered = [];
        $skipped = 0;

        foreach ($inOutRows as $row) {
            $fingerprint = $this->inOutFingerprint(
                (int) $row['employee_id'],
                Carbon::parse($row['dt_datetime']),
                (bool) $row['is_in'],
            );

            if (isset($existingFingerprints[$fingerprint])) {
                $skipped++;

                continue;
            }

            $existingFingerprints[$fingerprint] = true;
            $filtered[] = $row;
        }

        return ['rows' => $filtered, 'skipped' => $skipped];
    }

    private function inOutFingerprint(int $employeeId, Carbon $dtDatetime, bool $isIn): string
    {
        return $employeeId.'|'.$dtDatetime->format('Y-m-d H:i:s').'|'.($isIn ? '1' : '0');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function timeLogAlreadyExists(
        array $row,
        string $dateField,
        ?string $timeInField,
        ?string $timeOutField,
    ): bool {
        $query = RawTimekeepingTimeLog::query()
            ->where('employee_id', $row['employee_id'])
            ->where('date_out', $this->normalizeDateValue($row[$dateField] ?? null));

        if ($timeInField !== null) {
            $query->where('time_in', $this->normalizeTimeValue($row[$timeInField] ?? null));
        }

        if ($timeOutField !== null) {
            $query->where('time_out', $this->normalizeTimeValue($row[$timeOutField] ?? null));
        }

        if (filled($row['time_code_id'] ?? null)) {
            $query->where('time_code_id', $row['time_code_id']);
        }

        return $query->exists();
    }

    private function assertTextFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getClientMimeType());

        $allowedExtensions = ['txt', 'csv', ''];
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];

        if (! in_array($extension, $allowedExtensions, true) && ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('File should be in Text (Tab delimited) (*.txt) or CSV format.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitLine(string $line): array
    {
        $line = rtrim($line, "\r\n");

        if ($line === '') {
            return [''];
        }

        return str_getcsv($line, "\t");
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
     * @param  array<string, string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateRow(TimeCaptureFormat $format, array $row, int $lineNumber, array &$errors): ?array
    {
        $parsed = [];
        $hasError = false;

        foreach ($row as $fieldName => $value) {
            $result = match ($fieldName) {
                'employee_number' => $this->validateEmployeeNumber($value, $lineNumber, $errors, $hasError),
                'biometric_id' => $this->validateBiometricId($value, $lineNumber, $errors, $hasError),
                'tmp_employee_number' => $this->validateEmployeeNumber($value, $lineNumber, $errors, $hasError),
                'indicator' => $this->validateIndicator($format, $value, $lineNumber, $errors, $hasError),
                'actual_date', 'workdate', 'date_out' => $this->validateDateField($fieldName, $value, $lineNumber, $errors, $hasError),
                'worktime' => $this->validateWorktime($value, $lineNumber, $errors, $hasError),
                'time_in', 'time_out' => $this->validateTimeField($fieldName, $value, $lineNumber, $errors, $hasError),
                'reason' => $this->validateReason($value, $lineNumber, $errors, $hasError),
                default => ['value' => $value],
            };

            if ($result === null) {
                continue;
            }

            foreach ($result as $key => $item) {
                $parsed[$key] = $item;
            }
        }

        return $hasError ? null : $parsed;
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateEmployeeNumber(string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Employee Number is required.";

            return null;
        }

        $employee = Employee::query()
            ->where('employee_number', $value)
            ->first();

        if (! $employee) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Employee Number ({$value}).";

            return null;
        }

        return ['employee_id' => $employee->employee_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateBiometricId(string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Employee Biometric ID is required.";

            return null;
        }

        $assignments = EmployeeCampusAssignment::query()
            ->where('biometric_id', $value)
            ->get();
        $employeeIds = $assignments->pluck('employee_id')->unique();

        if ($employeeIds->count() !== 1) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Employee Biometric ID ({$value}).";

            return null;
        }

        $employee = Employee::query()->find((int) $employeeIds->first());

        if (! $employee) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Employee Biometric ID ({$value}).";

            return null;
        }

        return ['employee_id' => $employee->employee_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateIndicator(TimeCaptureFormat $format, string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Indicator is required.";

            return null;
        }

        $in = trim((string) $format->time_in_identifier);
        $out = trim((string) $format->time_out_identifier);

        if ($value !== $in && $value !== $out) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Indicator ({$value}).";

            return null;
        }

        return ['is_in' => $value];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateDateField(string $fieldName, string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        $label = str_replace('_', ' ', ucwords(str_replace('_', ' ', $fieldName)));

        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: {$label} is required.";

            return null;
        }

        if (! $this->isValidDate($value)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [$fieldName => $value];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateWorktime(string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Worktime is required.";

            return null;
        }

        if (! preg_match(self::TIME_PATTERN, $value)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Worktime ({$value}).";

            return null;
        }

        return ['worktime' => $value];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateTimeField(string $fieldName, string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        $label = str_replace('_', ' ', ucwords(str_replace('_', ' ', $fieldName)));

        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: {$label} is required.";

            return null;
        }

        if (! preg_match(self::TIME_PATTERN_SECONDS, $value)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [$fieldName => $value];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateReason(string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        if ($value === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Reason is required.";

            return null;
        }

        $timeCode = TimeCode::query()
            ->where('time_code', $value)
            ->first();

        if (! $timeCode) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid Reason / Time Code ({$value}).";

            return null;
        }

        return [
            'reason' => $value,
            'time_code_id' => $timeCode->time_code_id,
        ];
    }

    private function isValidDate(string $value): bool
    {
        return preg_match(self::DATE_PATTERN, $value) === 1
            || preg_match(self::DATE_PATTERN_US, $value) === 1;
    }

    private function normalizeDateValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeTimeValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('H:i:s');
    }

    private function combineDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time);
    }

    private function resolveIndicator(TimeCaptureFormat $format, string $indicator): bool
    {
        return trim($indicator) === trim((string) $format->time_in_identifier);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveSeparateDateTimes(TimeCaptureFormat $format, array $row): array
    {
        $roles = TimeCaptureFormatSupport::fieldRoles($format);
        $dateField = TimeCaptureFormatSupport::requireFieldRole($format, 'date');
        $timeInField = TimeCaptureFormatSupport::requireFieldRole($format, 'time_in');
        $timeOutField = TimeCaptureFormatSupport::requireFieldRole($format, 'time_out');

        $date = Carbon::parse($this->resolveRowDateString($format, $row));
        $timeIn = Carbon::parse($date->toDateString().' '.$row[$timeInField]);
        $timeOut = Carbon::parse($date->toDateString().' '.$row[$timeOutField]);

        if ($roles['reason'] !== null) {
            if ($timeIn->format('H:i:s') > $timeOut->format('H:i:s')) {
                $timeIn = $timeIn->copy()->subDay();
            }

            return [$timeIn, $timeOut];
        }

        if ($timeIn->format('H:i:s') > $timeOut->format('H:i:s')) {
            $timeOut = $timeOut->copy()->addDay();
        }

        return [$timeIn, $timeOut];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveRowDateString(TimeCaptureFormat $format, array $row): string
    {
        $dateField = TimeCaptureFormatSupport::requireFieldRole($format, 'date');
        $value = $row[$dateField] ?? null;

        if (! filled($value)) {
            throw new RuntimeException("Missing date value for mapped field {$dateField}.");
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveDateRange(TimeCaptureFormat $format, array $rows): array
    {
        $roles = TimeCaptureFormatSupport::fieldRoles($format);
        $dateField = $roles['date'];
        $dates = [];

        if ($dateField === null) {
            $today = now();

            return ['from' => $today->copy()->startOfDay(), 'to' => $today->copy()->endOfDay()];
        }

        $timeFields = array_values(array_filter([
            $roles['worktime'],
            $roles['time_in'],
            $roles['time_out'],
        ]));

        foreach ($rows as $row) {
            if (filled($row[$dateField] ?? null)) {
                $dates[] = Carbon::parse($row[$dateField])->startOfDay();
            }

            foreach ($timeFields as $timeField) {
                $timeValue = $row[$timeField] ?? null;

                if (filled($row[$dateField] ?? null) && filled($timeValue)) {
                    $dates[] = Carbon::parse($row[$dateField].' '.$timeValue);
                }
            }
        }

        if ($dates === []) {
            $today = now();

            return ['from' => $today->copy()->startOfDay(), 'to' => $today->copy()->endOfDay()];
        }

        return [
            'from' => collect($dates)->min(),
            'to' => collect($dates)->max(),
        ];
    }

    private function nextBatchNumber(): int
    {
        $last = RawTimekeepingTransaction::query()->max('batch_no');

        return ((int) $last) + 1;
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "time_logs_staging:{$userId}:{$token}";
    }
}
