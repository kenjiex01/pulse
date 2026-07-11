<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\User;
use App\Services\TimeLogsDtr\SanMateoCardReportParser;
use App\Support\TimeLogs;
use App\Support\TimeLogsDtr;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class TimeLogsDtrUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    private const DATE_PATTERN = '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/';

    private const DATE_PATTERN_US = '/^([0]?[1-9]|1[0-2])\/([0]?[1-9]|1\d|2\d|3[01])\/(19|20)\d{2}$/';

    private const TIME_PATTERN_SECONDS = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/';

    public function __construct(
        private readonly EmployeeBiometricResolver $biometricResolver,
        private readonly SanMateoCardReportParser $cardReportParser,
    ) {}

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    public function parseUploadedFile(UploadedFile $file, Campus $campus): array
    {
        $format = TimeLogsDtr::campusFormat($campus);
        $this->assertCampusFileType($file, $format);

        if (($format['parser'] ?? 'flat') === 'san_mateo_card_report') {
            return $this->parseCardReportFile($file, $campus, $format);
        }

        $matrix = $this->readSpreadsheetRows($file);

        return $this->parseFlatRowMatrix($matrix, $campus, $format, $file->getClientOriginalName());
    }

    /**
     * @param  array<string, mixed>  $format
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    private function parseCardReportFile(UploadedFile $file, Campus $campus, array $format): array
    {
        $parsed = $this->cardReportParser->parse($file, $format);
        $valid = [];
        $errors = $parsed['errors'];
        $lineNumber = 0;

        foreach ($parsed['rows'] as $row) {
            $lineNumber++;
            $validated = $this->validateBiometricRow($row, $campus, $lineNumber, $errors);

            if ($validated !== null) {
                $valid[] = $validated;
            }
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
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    private function parseFlatRowMatrix(array $matrix, Campus $campus, array $format, string $filename): array
    {
        $expectedColumns = $format['columns'];
        $headerRows = (int) ($format['header_rows'] ?? 2);
        $valid = [];
        $errors = [];
        $lineNumber = 0;

        foreach ($matrix as $cells) {
            $lineNumber++;
            $cells = array_map(fn ($cell) => $this->stringifyCell($cell), $cells);

            if ($lineNumber <= $headerRows) {
                if ($lineNumber === 1) {
                    $nonEmpty = array_values(array_filter($cells, fn (string $cell) => $cell !== ''));

                    if (count($nonEmpty) !== count($expectedColumns)) {
                        throw new RuntimeException('Fields and columns are not equal.');
                    }

                    foreach ($expectedColumns as $index => $column) {
                        if (($cells[$index] ?? '') !== $column) {
                            throw new RuntimeException('Fields from the uploaded file do not match the '.$campus->campus_name.' DTR layout.');
                        }
                    }
                }

                if ($lineNumber === 2) {
                    $nonEmpty = array_values(array_filter($cells, fn (string $cell) => $cell !== ''));

                    if (count($nonEmpty) !== count($expectedColumns)) {
                        throw new RuntimeException('Header did not match the number of fields.');
                    }
                }

                continue;
            }

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $nonEmpty = array_values(array_filter($cells, fn (string $cell) => $cell !== ''));

            if (count($nonEmpty) !== count($expectedColumns)) {
                throw new RuntimeException('Details did not match with the number of fields at line '.$lineNumber.'.');
            }

            $row = [];

            foreach ($expectedColumns as $index => $column) {
                $row[$column] = $cells[$index] ?? '';
            }

            $parsed = $this->validateEmployeeNumberRow($row, $lineNumber, $errors);

            if ($parsed !== null) {
                $valid[] = $parsed;
            }
        }

        if ($lineNumber < ($headerRows + 1)) {
            throw new RuntimeException('The uploaded file must include field names, column headers, and at least one data row.');
        }

        if ($valid === [] && $errors === []) {
            throw new RuntimeException('No data rows found for uploading.');
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
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheetRows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to read the uploaded Excel file.');
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, int $campusId, array $parseResult, string $tab): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
            'parser' => 'dtr',
            'tab' => $tab,
            'campus_id' => $campusId,
            'format_id' => null,
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

        if (! $staging || ($staging['parser'] ?? null) !== 'dtr' || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged DTR records to load.');
        }

        $campus = Campus::query()->findOrFail((int) $staging['campus_id']);
        $rows = $staging['valid'];
        $tab = (string) ($staging['tab'] ?? 'timelogs-dtr');

        return DB::transaction(function () use ($user, $campus, $rows, $staging, $token, $tab) {
            $dateRange = $this->resolveDateRange($rows);

            $transaction = RawTimekeepingTransaction::query()->create([
                'timekeeping_transaction_type_id' => TimeLogs::transactionTypeId($tab),
                'dt_from' => $dateRange['from'],
                'dt_to' => $dateRange['to'],
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
                'batch_no' => $this->nextBatchNumber(),
                'filename' => $staging['filename'],
                'timecapture_format_id' => null,
                'campus_id' => $campus->campus_id,
            ]);

            $persisted = $this->persistRows($rows, $transaction);

            if ($persisted['inserted'] === 0) {
                throw new RuntimeException('All records are duplicates of existing time logs and were not saved.');
            }

            $this->discardStaging($user, $token);

            return [
                'transaction' => $transaction->fresh(['uploadedBy', 'campus']),
                'inserted' => $persisted['inserted'],
                'skipped_duplicates' => $persisted['skipped_duplicates'],
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{inserted: int, skipped_duplicates: int}
     */
    private function persistRows(array $rows, RawTimekeepingTransaction $transaction): array
    {
        $inOutRows = [];
        $batchFingerprints = [];
        $skippedDuplicates = 0;

        foreach ($rows as $row) {
            $timeIn = Carbon::parse($row['actual_date'].' '.$row['time_in']);
            $timeOut = Carbon::parse($row['actual_date'].' '.$row['time_out']);

            if ($timeIn->format('H:i:s') > $timeOut->format('H:i:s')) {
                $timeOut = $timeOut->copy()->addDay();
            }

            $skippedDuplicates += $this->queueInOutRow(
                $inOutRows,
                $batchFingerprints,
                (int) $row['employee_id'],
                $timeIn,
                true,
                $transaction->timekeeping_transaction_id,
            );

            $skippedDuplicates += $this->queueInOutRow(
                $inOutRows,
                $batchFingerprints,
                (int) $row['employee_id'],
                $timeOut,
                false,
                $transaction->timekeeping_transaction_id,
            );
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
     * @param  array<string, string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateBiometricRow(array $row, Campus $campus, int $lineNumber, array &$errors): ?array
    {
        $hasError = false;
        $parsed = [];
        $biometricId = trim((string) ($row['biometric_id'] ?? ''));

        if ($biometricId === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Biometric ID is required.";
        } else {
            $employee = $this->biometricResolver->resolve((int) $campus->campus_id, $biometricId);

            if (! $employee) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid Biometric ID ({$biometricId}) for {$campus->campus_name}.";
            } else {
                $parsed['employee_id'] = $employee->employee_id;
                $parsed['employee_number'] = $employee->employee_number;
            }
        }

        foreach (['actual_date' => 'Actual Date', 'time_in' => 'Time In', 'time_out' => 'Time Out'] as $field => $label) {
            $rawValue = $row[$field] ?? '';
            $value = $this->normalizeFieldValue($rawValue, $field);

            if ($value === '') {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: {$label} is required.";

                continue;
            }

            if ($field === 'actual_date' && ! $this->isValidDate($value)) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid {$label} ({$rawValue}).";

                continue;
            }

            if (in_array($field, ['time_in', 'time_out'], true) && ! preg_match(self::TIME_PATTERN_SECONDS, $value)) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid {$label} ({$rawValue}).";

                continue;
            }

            $parsed[$field] = $field === 'actual_date'
                ? Carbon::parse($value)->toDateString()
                : Carbon::parse($value)->format('H:i:s');
        }

        return $hasError ? null : $parsed;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateEmployeeNumberRow(array $row, int $lineNumber, array &$errors): ?array
    {
        $hasError = false;
        $parsed = [];

        $employeeNumber = $row['employee_number'] ?? '';

        if ($employeeNumber === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Employee Number is required.";
        } else {
            $employee = Employee::query()->where('employee_number', $employeeNumber)->first();

            if (! $employee) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid Employee Number ({$employeeNumber}).";
            } else {
                $parsed['employee_id'] = $employee->employee_id;
                $parsed['employee_number'] = $employeeNumber;
            }
        }

        foreach (['actual_date' => 'Actual Date', 'time_in' => 'Time In', 'time_out' => 'Time Out'] as $field => $label) {
            $rawValue = $row[$field] ?? '';
            $value = $this->normalizeFieldValue($rawValue, $field);

            if ($value === '') {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: {$label} is required.";

                continue;
            }

            if ($field === 'actual_date' && ! $this->isValidDate($value)) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid {$label} ({$rawValue}).";

                continue;
            }

            if (in_array($field, ['time_in', 'time_out'], true) && ! preg_match(self::TIME_PATTERN_SECONDS, $value)) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid {$label} ({$rawValue}).";

                continue;
            }

            $parsed[$field] = $field === 'actual_date'
                ? Carbon::parse($value)->toDateString()
                : Carbon::parse($value)->format('H:i:s');
        }

        return $hasError ? null : $parsed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveDateRange(array $rows): array
    {
        $dates = collect($rows)
            ->flatMap(function (array $row) {
                $date = Carbon::parse($row['actual_date'])->startOfDay();
                $times = [];

                if (filled($row['time_in'] ?? null)) {
                    $times[] = Carbon::parse($row['actual_date'].' '.$row['time_in']);
                }

                if (filled($row['time_out'] ?? null)) {
                    $times[] = Carbon::parse($row['actual_date'].' '.$row['time_out']);
                }

                return collect([$date])->merge($times);
            });

        if ($dates->isEmpty()) {
            $today = now();

            return ['from' => $today->copy()->startOfDay(), 'to' => $today->copy()->endOfDay()];
        }

        return [
            'from' => $dates->min(),
            'to' => $dates->max(),
        ];
    }

    private function nextBatchNumber(): int
    {
        return ((int) RawTimekeepingTransaction::query()->max('batch_no')) + 1;
    }

    private function assertCampusFileType(UploadedFile $file, array $format): void
    {
        $expectedExtension = strtolower((string) ($format['file_extension'] ?? 'xls'));
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== $expectedExtension) {
            throw new RuntimeException('This campus requires an .'.strtoupper($expectedExtension).' file.');
        }
    }

    private function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
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

    private function normalizeFieldValue(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            if (in_array($field, ['time_in', 'time_out'], true) && $numeric >= 0 && $numeric < 1) {
                return gmdate('H:i:s', (int) round($numeric * 86400));
            }

            if ($field === 'actual_date' && $numeric >= 1) {
                return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
            }
        }

        if (preg_match('/^\d{5,}(\.\d+)?$/', $value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format(
                in_array($field, ['time_in', 'time_out'], true) ? 'H:i:s' : 'Y-m-d',
            );
        }

        return $value;
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

    private function isValidDate(string $value): bool
    {
        return preg_match(self::DATE_PATTERN, $value) === 1
            || preg_match(self::DATE_PATTERN_US, $value) === 1;
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "time_logs_staging:{$userId}:{$token}";
    }
}
