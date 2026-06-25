<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTimeLog;
use App\Models\RawTimekeepingTransaction;
use App\Models\TimeCaptureFormat;
use App\Models\TimeCode;
use App\Models\User;
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
    public function createStagingToken(User $user, int $formatId, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
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

    public function commit(User $user, string $token): RawTimekeepingTransaction
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $format = TimeCaptureFormat::query()->with('fields')->findOrFail((int) $staging['format_id']);
        $fieldNames = $this->orderedFieldNames($format);
        $rows = $staging['valid'];

        return DB::transaction(function () use ($user, $format, $fieldNames, $rows, $staging, $token) {
            $dateRange = $this->resolveDateRange($rows, $fieldNames);

            $transaction = RawTimekeepingTransaction::query()->create([
                'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
                'dt_from' => $dateRange['from'],
                'dt_to' => $dateRange['to'],
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
                'batch_no' => $this->nextBatchNumber(),
                'filename' => $staging['filename'],
                'timecapture_format_id' => $format->timecapture_format_id,
            ]);

            $this->persistRows($format, $fieldNames, $rows, $transaction);

            $this->discardStaging($user, $token);

            return $transaction->fresh(['uploadedBy', 'timeCaptureFormat']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $fieldNames
     */
    private function persistRows(TimeCaptureFormat $format, array $fieldNames, array $rows, RawTimekeepingTransaction $transaction): void
    {
        $inOutRows = [];

        foreach ($rows as $row) {
            if (in_array('reason', $fieldNames, true)) {
                $timeLog = RawTimekeepingTimeLog::query()->create([
                    'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                    'employee_id' => $row['employee_id'],
                    'time_in' => $this->normalizeTimeValue($row['time_in'] ?? null),
                    'time_out' => $this->normalizeTimeValue($row['time_out'] ?? null),
                    'date_out' => $this->normalizeDateValue($row['date_out'] ?? null),
                    'time_code_id' => $row['time_code_id'] ?? null,
                ]);

                if (in_array('time_in', $fieldNames, true) || in_array('time_out', $fieldNames, true)) {
                    [$timeIn, $timeOut] = $this->resolveReasonDateTimes($row);

                    if (in_array('time_in', $fieldNames, true)) {
                        $inOutRows[] = [
                            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                            'employee_id' => $row['employee_id'],
                            'dt_datetime' => $timeIn,
                            'is_in' => true,
                        ];
                    }

                    if (in_array('time_out', $fieldNames, true)) {
                        $inOutRows[] = [
                            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                            'employee_id' => $row['employee_id'],
                            'dt_datetime' => $timeOut,
                            'is_in' => false,
                        ];
                    }
                }

                continue;
            }

            if (in_array('worktime', $fieldNames, true)) {
                $isIn = $this->resolveIndicator($format, (string) ($row['is_in'] ?? ''));

                $inOutRows[] = [
                    'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                    'employee_id' => $row['employee_id'],
                    'dt_datetime' => $this->combineDateTime($row['actual_date'], $row['worktime']),
                    'is_in' => $isIn,
                ];

                continue;
            }

            if (in_array('time_in', $fieldNames, true) || in_array('time_out', $fieldNames, true)) {
                [$timeIn, $timeOut] = $this->resolveWorkDateTimes($row);

                if (in_array('time_in', $fieldNames, true)) {
                    $inOutRows[] = [
                        'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                        'employee_id' => $row['employee_id'],
                        'dt_datetime' => $timeIn,
                        'is_in' => true,
                    ];
                }

                if (in_array('time_out', $fieldNames, true)) {
                    $inOutRows[] = [
                        'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                        'employee_id' => $row['employee_id'],
                        'dt_datetime' => $timeOut,
                        'is_in' => false,
                    ];
                }
            }
        }

        foreach ($inOutRows as $inOutRow) {
            RawTimekeepingInandout::query()->create($inOutRow);
        }
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

        $employee = Employee::query()
            ->where('employee_number', $value)
            ->first();

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
    private function resolveReasonDateTimes(array $row): array
    {
        $dateOut = Carbon::parse($row['date_out']);
        $timeIn = Carbon::parse($dateOut->toDateString().' '.$row['time_in']);
        $timeOut = Carbon::parse($dateOut->toDateString().' '.$row['time_out']);

        if ($timeIn->format('H:i:s') > $timeOut->format('H:i:s')) {
            $timeIn = $timeIn->copy()->subDay();
        }

        return [$timeIn, $timeOut];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWorkDateTimes(array $row): array
    {
        $date = Carbon::parse($row['workdate']);
        $timeIn = Carbon::parse($date->toDateString().' '.$row['time_in']);
        $timeOut = Carbon::parse($date->toDateString().' '.$row['time_out']);

        if ($timeIn->format('H:i:s') > $timeOut->format('H:i:s')) {
            $timeOut = $timeOut->copy()->addDay();
        }

        return [$timeIn, $timeOut];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $fieldNames
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveDateRange(array $rows, array $fieldNames): array
    {
        $dates = [];

        foreach ($rows as $row) {
            foreach (['actual_date', 'workdate', 'date_out'] as $dateField) {
                if (in_array($dateField, $fieldNames, true) && filled($row[$dateField] ?? null)) {
                    $dates[] = Carbon::parse($row[$dateField])->startOfDay();
                }
            }

            foreach (['worktime', 'time_in', 'time_out'] as $timeField) {
                if (! in_array($timeField, $fieldNames, true)) {
                    continue;
                }

                $dateValue = $row['actual_date'] ?? $row['workdate'] ?? $row['date_out'] ?? null;
                $timeValue = $row[$timeField] ?? null;

                if ($dateValue && $timeValue) {
                    $dates[] = Carbon::parse($dateValue.' '.$timeValue);
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
