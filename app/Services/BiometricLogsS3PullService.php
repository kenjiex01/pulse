<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BiometricLogsS3PullService
{
    public function __construct(
        private readonly EmployeeBiometricResolver $biometricResolver,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('biometric_logs.s3.bucket'))
            && filled(config('biometric_logs.s3.key'))
            && filled(config('biometric_logs.s3.secret'));
    }

    /**
     * @return array{
     *     files_scanned: int,
     *     files_imported: int,
     *     files_skipped: int,
     *     punches_inserted: int,
     *     punches_skipped_duplicates: int,
     *     punches_unmatched: int,
     *     transactions: array<int, array{batch_no: int, filename: string, inserted: int}>,
     *     errors: array<int, string>,
     * }
     */
    public function pull(
        User $user,
        int $year,
        int $month,
        ?int $campusId = null,
        ?string $collectorFolder = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Biometric S3 credentials are not configured. Set DB_BACKUP_S3_* (or BIOMETRIC_LOGS_S3_*) in .env.');
        }

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new RuntimeException('Invalid year or month for S3 pull.');
        }

        $prefix = $this->monthPrefix($year, $month);
        $collectorFolder = $this->sanitizeFolder($collectorFolder);
        $campusFilter = $campusId !== null && $campusId > 0
            ? Campus::query()->find($campusId)
            : null;

        if ($campusId !== null && $campusId > 0 && $campusFilter === null) {
            throw new RuntimeException('Selected campus was not found.');
        }

        $keys = $this->listAttendanceObjectKeys($prefix, $collectorFolder);

        $summary = [
            'files_scanned' => count($keys),
            'files_imported' => 0,
            'files_skipped' => 0,
            'punches_inserted' => 0,
            'punches_skipped_duplicates' => 0,
            'punches_unmatched' => 0,
            'transactions' => [],
            'errors' => [],
        ];

        foreach ($keys as $key) {
            try {
                $payload = $this->downloadAndDecode($key);

                if (($payload['kind'] ?? null) !== 'attendance') {
                    $summary['files_skipped']++;

                    continue;
                }

                $result = $this->importAttendancePayload($user, $key, $payload, $campusFilter);

                if ($result === null) {
                    $summary['files_skipped']++;

                    continue;
                }

                $summary['punches_unmatched'] += $result['unmatched'];
                $summary['punches_skipped_duplicates'] += $result['skipped_duplicates'];

                if (($result['inserted'] ?? 0) === 0) {
                    $summary['files_skipped']++;

                    continue;
                }

                $summary['files_imported']++;
                $summary['punches_inserted'] += $result['inserted'];
                $summary['transactions'][] = [
                    'batch_no' => $result['batch_no'],
                    'filename' => $key,
                    'inserted' => $result['inserted'],
                ];
            } catch (Throwable $exception) {
                $summary['errors'][] = basename($key).': '.$exception->getMessage();
                Log::warning('Biometric S3 pull failed for object.', [
                    's3_key' => $key,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Collector folders under biometric_logs/{Y}/{m}/.
     *
     * @return array<int, string>
     */
    public function listCollectorFolders(int $year, int $month): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $prefix = $this->monthPrefix($year, $month);
        $disk = Storage::disk($this->disk());

        try {
            $directories = $disk->directories($prefix);
        } catch (Throwable) {
            return [];
        }

        $folders = [];

        foreach ($directories as $directory) {
            $name = basename(rtrim($directory, '/'));
            if ($name !== '' && $name !== '.' && $name !== '..') {
                $folders[] = $name;
            }
        }

        sort($folders);

        return array_values(array_unique($folders));
    }

    public function monthPrefix(int $year, int $month): string
    {
        $root = trim((string) config('biometric_logs.s3.prefix', 'biometric_logs'), '/');
        $ym = sprintf('%04d/%02d', $year, $month);

        return $root === '' ? $ym : $root.'/'.$ym;
    }

    /**
     * @return array<int, string>
     */
    private function listAttendanceObjectKeys(string $prefix, ?string $collectorFolder): array
    {
        $disk = Storage::disk($this->disk());
        $searchPrefix = $collectorFolder !== null && $collectorFolder !== ''
            ? rtrim($prefix, '/').'/'.$collectorFolder
            : $prefix;

        try {
            $files = $disk->allFiles($searchPrefix);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to list S3 objects under '.$searchPrefix.': '.$exception->getMessage(), 0, $exception);
        }

        $keys = [];

        foreach ($files as $path) {
            $lower = strtolower($path);
            if (str_ends_with($lower, '.json.gzip') || str_ends_with($lower, '.json.gz')) {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadAndDecode(string $s3Key): array
    {
        $disk = Storage::disk($this->disk());
        $binary = $disk->get($s3Key);

        if ($binary === null || $binary === '') {
            throw new RuntimeException('Empty S3 object.');
        }

        $json = @gzdecode($binary);
        if ($json === false) {
            // Some tools store uncompressed JSON by mistake — try raw.
            $json = $binary;
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid JSON after decompress.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{batch_no: int, inserted: int, skipped_duplicates: int, unmatched: int}|null
     */
    private function importAttendancePayload(
        User $user,
        string $s3Key,
        array $payload,
        ?Campus $campusFilter,
    ): ?array {
        $logs = $payload['logs'] ?? null;

        if (! is_array($logs) || $logs === []) {
            return null;
        }

        $campus = $this->resolveCampus($payload, $campusFilter);

        if ($campusFilter !== null && ($campus === null || (int) $campus->campus_id !== (int) $campusFilter->campus_id)) {
            return null;
        }

        if ($campus === null) {
            throw new RuntimeException('Could not resolve campus from campus_code or collector folder.');
        }

        $candidateRows = [];
        $unmatched = 0;

        foreach ($logs as $log) {
            if (! is_array($log)) {
                $unmatched++;

                continue;
            }

            $userId = trim((string) ($log['user_id'] ?? ''));
            $punchedAt = trim((string) ($log['punched_at'] ?? ''));
            $isIn = $this->mapPunchStateToIsIn($log['punch_state'] ?? null);

            if ($userId === '' || $punchedAt === '' || $isIn === null) {
                $unmatched++;

                continue;
            }

            $employee = $this->biometricResolver->resolve((int) $campus->campus_id, $userId);

            if ($employee === null) {
                $unmatched++;

                continue;
            }

            try {
                $dt = Carbon::parse($punchedAt);
            } catch (Throwable) {
                $unmatched++;

                continue;
            }

            $candidateRows[] = [
                'employee_id' => (int) $employee->employee_id,
                'dt_datetime' => $dt,
                'is_in' => $isIn,
            ];
        }

        if ($candidateRows === []) {
            return [
                'batch_no' => 0,
                'inserted' => 0,
                'skipped_duplicates' => 0,
                'unmatched' => $unmatched,
            ];
        }

        return DB::transaction(function () use ($user, $s3Key, $campus, $candidateRows, $unmatched) {
            $inOutRows = [];
            $batchFingerprints = [];
            $skippedDuplicates = 0;

            foreach ($candidateRows as $row) {
                $fingerprint = $this->fingerprint(
                    (int) $row['employee_id'],
                    $row['dt_datetime'],
                    (bool) $row['is_in'],
                );

                if (isset($batchFingerprints[$fingerprint])) {
                    $skippedDuplicates++;

                    continue;
                }

                $batchFingerprints[$fingerprint] = true;
                $inOutRows[] = [
                    'employee_id' => $row['employee_id'],
                    'dt_datetime' => $row['dt_datetime'],
                    'is_in' => $row['is_in'],
                ];
            }

            $filtered = $this->filterExistingInOutRows($inOutRows);
            $skippedDuplicates += $filtered['skipped'];
            $inserted = count($filtered['rows']);

            if ($inserted === 0) {
                return [
                    'batch_no' => 0,
                    'inserted' => 0,
                    'skipped_duplicates' => $skippedDuplicates,
                    'unmatched' => $unmatched,
                ];
            }

            $dateTimes = collect($filtered['rows'])->map(fn (array $row) => Carbon::parse($row['dt_datetime']));
            $batchNo = ((int) RawTimekeepingTransaction::query()->max('batch_no')) + 1;

            $transaction = RawTimekeepingTransaction::query()->create([
                'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
                'dt_from' => $dateTimes->min()->copy(),
                'dt_to' => $dateTimes->max()->copy(),
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
                'batch_no' => $batchNo,
                'filename' => $s3Key,
                'timecapture_format_id' => null,
                'campus_id' => $campus->campus_id,
            ]);

            foreach ($filtered['rows'] as $inOutRow) {
                RawTimekeepingInandout::query()->create([
                    'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                    'employee_id' => $inOutRow['employee_id'],
                    'dt_datetime' => $inOutRow['dt_datetime'],
                    'is_in' => $inOutRow['is_in'],
                ]);
            }

            SysLogService::record(
                action: 'add',
                table: 'raw_timekeeping_transactions',
                description: sprintf(
                    'Pulled biometric S3 logs (%s): %d punches inserted, %d duplicates skipped, %d unmatched user_id',
                    basename($s3Key),
                    $inserted,
                    $skippedDuplicates,
                    $unmatched,
                ),
            );

            return [
                'batch_no' => $batchNo,
                'inserted' => $inserted,
                'skipped_duplicates' => $skippedDuplicates,
                'unmatched' => $unmatched,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCampus(array $payload, ?Campus $campusFilter): ?Campus
    {
        $code = strtoupper(trim((string) ($payload['campus_code'] ?? '')));

        if ($code !== '') {
            $byCode = Campus::query()
                ->whereRaw('UPPER(campus_code) = ?', [$code])
                ->first();

            if ($byCode !== null) {
                return $byCode;
            }
        }

        if ($campusFilter !== null) {
            return $campusFilter;
        }

        $hint = (string) ($payload['biometric_name'] ?? $payload['collector_name'] ?? '');

        return $this->guessCampusFromCollectorName($hint);
    }

    private function guessCampusFromCollectorName(string $name): ?Campus
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? '');
        $slug = trim(preg_replace('/\s+/', ' ', $slug) ?? '');

        if ($slug === '') {
            return null;
        }

        $campuses = Campus::query()->orderBy('campus_name')->get();

        foreach ($campuses as $campus) {
            $campusSlug = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $campus->campus_name) ?? '');
            $campusSlug = trim(preg_replace('/\s+/', ' ', $campusSlug) ?? '');

            if ($campusSlug === '') {
                continue;
            }

            // "Cainta-Main-Campus" / "Cainta Campus Front Desk" ↔ "ICCT Colleges Cainta Main Campus"
            $tokens = array_values(array_filter(explode(' ', $slug), fn (string $t) => strlen($t) >= 4 && ! in_array($t, ['icct', 'college', 'colleges', 'campus', 'front', 'desk'], true)));

            if ($tokens === []) {
                continue;
            }

            $matched = 0;
            foreach ($tokens as $token) {
                if (str_contains($campusSlug, $token)) {
                    $matched++;
                }
            }

            if ($matched >= 1 && (count($tokens) === 1 || $matched >= (int) ceil(count($tokens) / 2))) {
                return $campus;
            }
        }

        return null;
    }

    private function mapPunchStateToIsIn(mixed $punchState): ?bool
    {
        $state = strtolower(trim((string) ($punchState ?? '')));

        return match ($state) {
            'checkin', 'check_in', 'in', 'breakin', 'break_in', 'overtimein', 'overtime_in' => true,
            'checkout', 'check_out', 'out', 'breakout', 'break_out', 'overtimeout', 'overtime_out' => false,
            default => null,
        };
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
                $this->fingerprint(
                    (int) $record->employee_id,
                    Carbon::parse($record->dt_datetime),
                    (bool) $record->is_in,
                ) => true,
            ])
            ->all();

        $rows = [];
        $skipped = 0;

        foreach ($inOutRows as $row) {
            $fingerprint = $this->fingerprint(
                (int) $row['employee_id'],
                Carbon::parse($row['dt_datetime']),
                (bool) $row['is_in'],
            );

            if (isset($existingFingerprints[$fingerprint])) {
                $skipped++;

                continue;
            }

            $existingFingerprints[$fingerprint] = true;
            $rows[] = $row;
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function fingerprint(int $employeeId, Carbon $dtDatetime, bool $isIn): string
    {
        return $employeeId.'|'.$dtDatetime->format('Y-m-d H:i:s').'|'.($isIn ? '1' : '0');
    }

    private function sanitizeFolder(?string $folder): ?string
    {
        if ($folder === null) {
            return null;
        }

        $folder = trim($folder);
        $folder = trim($folder, '/');

        if ($folder === '') {
            return null;
        }

        // Prevent path traversal when building S3 prefixes.
        if (str_contains($folder, '..') || str_contains($folder, '\\')) {
            throw new RuntimeException('Invalid collector folder name.');
        }

        return $folder;
    }

    private function disk(): string
    {
        return (string) config('biometric_logs.s3.disk', 'backup-s3');
    }
}
