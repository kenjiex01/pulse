<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\TeachingLoadPullBatch;
use App\Models\TeachingLoadSession;
use App\Models\TeachingLoadSyncStatus;
use App\Models\User;
use App\Support\TimeLogs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TeachingLoadPullService
{
    private const CACHE_PREFIX = 'teaching_load_pull:';

    private const CACHE_TTL_MINUTES = 30;

    public const EMPLOYEE_LOAD_FILENAME_PREFIX = 'Skolaris Pull';

    public function __construct(
        private readonly SkolarisApiService $skolaris,
        private readonly EmployeeLoadAttendanceMatcher $attendanceMatcher,
    ) {}

    /**
     * @param  array<int, int>  $employeeIds
     * @return array{token: string, total: int}
     */
    public function startJob(User $user, string $dateFrom, string $dateTo, array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            throw new RuntimeException('Select at least one employee to pull.');
        }

        $eligibleIds = TimeLogs::eligibleEmployeeIds($employeeIds);

        if ($eligibleIds === []) {
            throw new RuntimeException('None of the selected employees are eligible for teaching load pull.');
        }

        if (count($eligibleIds) !== count($employeeIds)) {
            throw new RuntimeException('One or more selected employees are not eligible for teaching load pull.');
        }

        $token = Str::uuid()->toString();

        $pullBatch = TeachingLoadPullBatch::query()->create([
            'batch_no' => ((int) TeachingLoadPullBatch::query()->max('batch_no')) + 1,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'employee_count' => 0,
            'records_count' => 0,
            'pulled_by_id' => $user->id,
            'pulled_at' => now(),
        ]);

        $employeeLoadTransaction = RawEmployeeLoadTransaction::query()->create([
            'batch_no' => ((int) RawEmployeeLoadTransaction::query()->withTrashed()->max('batch_no')) + 1,
            'filename' => self::EMPLOYEE_LOAD_FILENAME_PREFIX.' Batch #'.$pullBatch->formattedBatchNo(),
            'enrollment_period_id' => null,
            'enrollment_period_label' => null,
            'dt_from' => $dateFrom,
            'dt_to' => $dateTo,
            'uploaded_by_id' => $user->id,
            'dt_uploaded' => now(),
        ]);

        Cache::put(self::CACHE_PREFIX.$token, [
            'pull_batch_id' => $pullBatch->teaching_load_pull_batch_id,
            'employee_load_transaction_id' => $employeeLoadTransaction->employee_load_transaction_id,
            'user_id' => $user->id,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'employee_ids' => $eligibleIds,
            'current' => 0,
            'total' => count($eligibleIds),
            'status' => 'running',
            'errors' => [],
            'completed' => [],
            'records_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        SysLogService::record(
            action: 'create',
            table: 'teaching_load_pull_batches',
            recordId: $pullBatch->teaching_load_pull_batch_id,
            description: 'Started teaching load pull from Skolaris (Batch #'.$pullBatch->formattedBatchNo().', '.$dateFrom.' to '.$dateTo.', '.count($eligibleIds).' employee(s))',
        );

        return ['token' => $token, 'total' => count($eligibleIds)];
    }

    /**
     * @return array<string, mixed>
     */
    public function processNext(string $token): array
    {
        $job = $this->getJob($token);

        if (($job['status'] ?? '') === 'done') {
            return $this->progressPayload($job, true);
        }

        $remaining = array_values(array_diff(
            $job['employee_ids'],
            $job['completed'] ?? [],
        ));

        if ($remaining === []) {
            $job['status'] = 'done';
            Cache::put(self::CACHE_PREFIX.$token, $job, now()->addMinutes(self::CACHE_TTL_MINUTES));

            SysLogService::record(
                action: 'update',
                table: 'teaching_load_pull_batches',
                recordId: $job['pull_batch_id'] ?? null,
                description: 'Completed teaching load pull from Skolaris ('.$job['current'].'/'.$job['total'].' employee(s), '.(($job['records_count'] ?? 0)).' rows, '.(($job['unchanged_count'] ?? 0)).' unchanged)',
            );

            return $this->progressPayload($job, true);
        }

        $employeeId = (int) $remaining[0];
        $employee = Employee::query()->find($employeeId);

        if ($employee === null || ! in_array($employeeId, TimeLogs::eligibleEmployeeIds([$employeeId]), true)) {
            $job['errors'][] = [
                'employee_id' => $employeeId,
                'message' => 'Employee is not eligible for teaching load pull.',
            ];
            $job['completed'][] = $employeeId;
            $job['current'] = count($job['completed']);
            Cache::put(self::CACHE_PREFIX.$token, $job, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $this->progressPayload($job, false, [
                'employee_number' => $employee?->employee_number,
                'records_count' => 0,
                'sync_status' => 'error',
                'error' => 'Employee is not eligible for teaching load pull.',
            ]);
        }

        try {
            $result = $this->pullEmployee(
                $employee,
                $job['date_from'],
                $job['date_to'],
                (int) $job['user_id'],
                (int) ($job['pull_batch_id'] ?? 0),
                (int) ($job['employee_load_transaction_id'] ?? 0),
            );
            $error = null;
        } catch (RuntimeException $exception) {
            $result = [
                'records_count' => 0,
                'sync_status' => 'error',
            ];
            $error = $exception->getMessage();
            $job['errors'][] = [
                'employee_id' => $employeeId,
                'employee_number' => $employee->employee_number,
                'message' => $error,
            ];
        }

        $job['completed'][] = $employeeId;
        $job['current'] = count($job['completed']);

        if (($result['sync_status'] ?? '') === 'unchanged') {
            $job['unchanged_count'] = (int) ($job['unchanged_count'] ?? 0) + 1;
        } elseif (($result['sync_status'] ?? '') === 'updated') {
            $job['updated_count'] = (int) ($job['updated_count'] ?? 0) + 1;
            $job['records_count'] = (int) ($job['records_count'] ?? 0) + (int) ($result['records_count'] ?? 0);
        }

        if ($job['current'] >= $job['total']) {
            $job['status'] = 'done';

            SysLogService::record(
                action: 'update',
                table: 'teaching_load_pull_batches',
                recordId: $job['pull_batch_id'] ?? null,
                description: 'Completed teaching load pull from Skolaris ('.$job['current'].'/'.$job['total'].' employee(s), '.(($job['records_count'] ?? 0)).' rows, '.(($job['unchanged_count'] ?? 0)).' unchanged)',
            );
        }

        Cache::put(self::CACHE_PREFIX.$token, $job, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $this->progressPayload($job, ($job['status'] ?? '') === 'done', [
            'employee_number' => $employee->employee_number,
            'records_count' => (int) ($result['records_count'] ?? 0),
            'sync_status' => $result['sync_status'] ?? null,
            'error' => $error,
        ]);
    }

    /**
     * Sync existing teaching_load_sessions into Employee Load entries (profile tab / payroll source).
     */
    public function backfillEmployeeLoadFromSessions(?int $employeeId = null): int
    {
        $sessionsQuery = TeachingLoadSession::query()
            ->with('employee')
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderBy('employee_id')
            ->orderBy('session_date')
            ->orderBy('time_in');

        $grouped = $sessionsQuery->get()->groupBy('employee_id');
        $synced = 0;

        foreach ($grouped as $empId => $sessions) {
            /** @var \Illuminate\Support\Collection<int, TeachingLoadSession> $sessions */
            $employee = $sessions->first()?->employee;

            if ($employee === null) {
                continue;
            }

            $dateFrom = $sessions->min('session_date')?->format('Y-m-d');
            $dateTo = $sessions->max('session_date')?->format('Y-m-d');

            if (! $dateFrom || ! $dateTo) {
                continue;
            }

            $loads = $sessions->map(fn (TeachingLoadSession $session) => [
                'session_date' => $session->session_date?->format('Y-m-d'),
                'employee_number' => $session->employee_number,
                'skolaris_offering_id' => $session->skolaris_offering_id,
                'subject_code' => $session->subject_code,
                'subject_name' => $session->subject_name,
                'section' => $session->section,
                'campus_name' => $session->campus_name,
                'room' => $session->room,
                'schedule_day' => $session->schedule_day,
                'class_schedule' => $session->class_schedule,
                'time_in' => $session->time_in,
                'time_out' => $session->time_out,
                'total_hours' => $session->total_hours,
                'total_render_hours' => $session->total_render_hours,
                'status_code' => $session->status_code,
            ])->values()->all();

            $transaction = RawEmployeeLoadTransaction::query()->create([
                'batch_no' => ((int) RawEmployeeLoadTransaction::query()->withTrashed()->max('batch_no')) + 1,
                'filename' => self::EMPLOYEE_LOAD_FILENAME_PREFIX.' Backfill',
                'enrollment_period_id' => null,
                'enrollment_period_label' => null,
                'dt_from' => $dateFrom,
                'dt_to' => $dateTo,
                'uploaded_by_id' => $sessions->first()?->pulled_by_id,
                'dt_uploaded' => now(),
            ]);

            $synced += $this->syncEmployeeLoadEntries(
                $employee,
                $dateFrom,
                $dateTo,
                (int) $transaction->employee_load_transaction_id,
                $loads,
            );
        }

        return $synced;
    }

    /**
     * @return array{records_count: int, sync_status: string}
     */
    private function pullEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        int $userId,
        int $pullBatchId,
        int $employeeLoadTransactionId,
    ): array {
        $employeeNumber = trim((string) $employee->employee_number);

        if ($employeeNumber === '') {
            throw new RuntimeException('Employee has no employee number.');
        }

        $rows = $this->skolaris->dailyLoads($dateFrom, $dateTo, [$employeeNumber]);
        $employeePayload = null;

        foreach ($rows as $row) {
            if (trim((string) ($row['employee_number'] ?? '')) === $employeeNumber) {
                $employeePayload = $row;
                break;
            }
        }

        $incoming = $this->normalizeIncomingLoads(
            is_array($employeePayload['loads'] ?? null) ? $employeePayload['loads'] : [],
            $employeeNumber,
        );

        $existing = TeachingLoadSession::query()
            ->where('employee_id', $employee->employee_id)
            ->whereDate('session_date', '>=', $dateFrom)
            ->whereDate('session_date', '<=', $dateTo)
            ->orderBy('session_date')
            ->orderBy('skolaris_offering_id')
            ->orderBy('time_in')
            ->get();

        $sessionsUnchanged = $this->fingerprintRows($incoming) === $this->fingerprintExisting($existing);
        $now = now();

        return DB::transaction(function () use (
            $employee,
            $employeeNumber,
            $incoming,
            $dateFrom,
            $dateTo,
            $userId,
            $pullBatchId,
            $employeeLoadTransactionId,
            $sessionsUnchanged,
            $now,
        ) {
            $insertedSessions = count($incoming);

            if (! $sessionsUnchanged) {
                TeachingLoadSession::query()
                    ->where('employee_id', $employee->employee_id)
                    ->whereDate('session_date', '>=', $dateFrom)
                    ->whereDate('session_date', '<=', $dateTo)
                    ->forceDelete();

                foreach ($incoming as $load) {
                    TeachingLoadSession::query()->create([
                        'teaching_load_pull_batch_id' => $pullBatchId,
                        'employee_id' => $employee->employee_id,
                        'session_date' => $load['session_date'],
                        'employee_number' => $employeeNumber,
                        'skolaris_offering_id' => $load['skolaris_offering_id'],
                        'subject_code' => $load['subject_code'],
                        'subject_name' => $load['subject_name'],
                        'section' => $load['section'],
                        'campus_name' => $load['campus_name'],
                        'room' => $load['room'],
                        'schedule_day' => $load['schedule_day'],
                        'class_schedule' => $load['class_schedule'],
                        'time_in' => $load['time_in'],
                        'time_out' => $load['time_out'],
                        'total_hours' => $load['total_hours'],
                        'total_render_hours' => $load['total_render_hours'],
                        'status_code' => $load['status_code'],
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'pulled_at' => $now,
                        'pulled_by_id' => $userId,
                    ]);
                }

                TeachingLoadSyncStatus::query()->updateOrCreate(
                    ['employee_id' => $employee->employee_id],
                    [
                        'last_pulled_at' => $now,
                        'last_date_from' => $dateFrom,
                        'last_date_to' => $dateTo,
                        'last_records_count' => $insertedSessions,
                        'last_pulled_by_id' => $userId,
                    ],
                );

                TeachingLoadPullBatch::query()
                    ->whereKey($pullBatchId)
                    ->update([
                        'records_count' => DB::raw('records_count + '.$insertedSessions),
                        'employee_count' => DB::raw('employee_count + 1'),
                    ]);
            }

            $syncedEmployeeLoad = $this->syncEmployeeLoadEntries(
                $employee,
                $dateFrom,
                $dateTo,
                $employeeLoadTransactionId,
                $incoming,
            );

            if ($sessionsUnchanged && $syncedEmployeeLoad === 0) {
                SysLogService::record(
                    action: 'read',
                    table: 'teaching_load_sessions',
                    recordId: $employee->employee_id,
                    description: 'Skipped teaching load pull for '.$employeeNumber.' ('.$dateFrom.' to '.$dateTo.') — unchanged',
                );

                return [
                    'records_count' => count($incoming),
                    'sync_status' => 'unchanged',
                ];
            }

            SysLogService::record(
                action: 'update',
                table: 'teaching_load_sessions',
                recordId: $employee->employee_id,
                description: ($sessionsUnchanged ? 'Synced employee load from unchanged teaching loads for ' : 'Overwrote teaching loads for ')
                    .$employeeNumber.' ('.$dateFrom.' to '.$dateTo.', '.$insertedSessions.' session(s))',
            );

            return [
                'records_count' => $insertedSessions,
                'sync_status' => 'updated',
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $loads
     */
    private function syncEmployeeLoadEntries(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        int $employeeLoadTransactionId,
        array $loads,
    ): int {
        if ($employeeLoadTransactionId <= 0) {
            return 0;
        }

        $existing = RawEmployeeLoadEntry::query()
            ->where('employee_id', $employee->employee_id)
            ->whereDate('session_date', '>=', $dateFrom)
            ->whereDate('session_date', '<=', $dateTo)
            ->whereHas('transaction', function ($query) {
                $query->where('filename', 'like', self::EMPLOYEE_LOAD_FILENAME_PREFIX.'%');
            })
            ->orderBy('session_date')
            ->orderBy('skolaris_offering_id')
            ->orderBy('class_schedule')
            ->get();

        $incomingFingerprint = $this->fingerprintEmployeeLoadRows($loads);
        $existingFingerprint = $this->fingerprintEmployeeLoadEntries($existing);
        $scheduleChanged = $incomingFingerprint !== $existingFingerprint;

        if ($scheduleChanged) {
            RawEmployeeLoadEntry::query()
                ->where('employee_id', $employee->employee_id)
                ->whereDate('session_date', '>=', $dateFrom)
                ->whereDate('session_date', '<=', $dateTo)
                ->whereHas('transaction', function ($query) {
                    $query->where('filename', 'like', self::EMPLOYEE_LOAD_FILENAME_PREFIX.'%');
                })
                ->forceDelete();

            foreach ($loads as $load) {
                $subject = trim(implode(' — ', array_filter([
                    $this->normalizeText($load['subject_code'] ?? null),
                    $this->normalizeText($load['subject_name'] ?? null),
                ])));

                RawEmployeeLoadEntry::query()->create([
                    'employee_load_transaction_id' => $employeeLoadTransactionId,
                    'employee_id' => $employee->employee_id,
                    'skolaris_offering_id' => $load['skolaris_offering_id'] ?? null,
                    'employee_number' => $load['employee_number'] ?? $employee->employee_number,
                    'faculty_name' => $employee->full_name,
                    'college' => $employee->college,
                    'modality' => null,
                    'subject' => $subject !== '' ? $subject : null,
                    'section' => $load['section'] ?? null,
                    'load_date' => $load['session_date'] ?? null,
                    'session_date' => $load['session_date'] ?? null,
                    'class_schedule' => $load['class_schedule'] ?? null,
                    'total_hours' => isset($load['total_hours']) ? $load['total_hours'] : null,
                    // Actual punches come from Time Logs, not Skolaris schedule times.
                    'time_in' => null,
                    'time_out' => null,
                    'remarks' => $load['status_code'] ?? null,
                    'comments' => $this->normalizeText($load['room'] ?? null),
                    'verification_remarks' => 'Pulled from Skolaris',
                ]);
            }
        }

        $entries = RawEmployeeLoadEntry::query()
            ->where('employee_id', $employee->employee_id)
            ->whereDate('session_date', '>=', $dateFrom)
            ->whereDate('session_date', '<=', $dateTo)
            ->whereHas('transaction', function ($query) {
                $query->where('filename', 'like', self::EMPLOYEE_LOAD_FILENAME_PREFIX.'%');
            })
            ->get();

        $matched = $this->attendanceMatcher->applyToEntries($employee, $entries);

        if (! $scheduleChanged && $matched === 0) {
            return 0;
        }

        return max($entries->count(), $matched);
    }

    /**
     * @param  array<int, mixed>  $loads
     * @return array<int, array<string, mixed>>
     */
    private function normalizeIncomingLoads(array $loads, string $employeeNumber): array
    {
        $normalized = [];

        foreach ($loads as $load) {
            if (! is_array($load)) {
                continue;
            }

            $sessionDate = trim((string) ($load['attendance_date'] ?? ''));

            if ($sessionDate === '') {
                continue;
            }

            $normalized[] = [
                'session_date' => $sessionDate,
                'employee_number' => $employeeNumber,
                'skolaris_offering_id' => isset($load['offering_id']) ? (int) $load['offering_id'] : null,
                'subject_code' => $this->normalizeText($load['subject_code'] ?? null),
                'subject_name' => $this->normalizeText($load['subject_name'] ?? null),
                'section' => $this->normalizeText($load['section'] ?? null),
                'campus_name' => $this->normalizeText($load['campus_name'] ?? null),
                'room' => $this->normalizeText($load['room'] ?? null),
                'schedule_day' => $this->normalizeText($load['schedule_day'] ?? null),
                'class_schedule' => $this->normalizeText($load['schedule'] ?? null),
                'time_in' => $this->normalizeText($load['time_in'] ?? null),
                'time_out' => $this->normalizeText($load['time_out'] ?? null),
                'total_hours' => $this->normalizeDecimal($load['total_hours'] ?? null),
                'total_render_hours' => $this->normalizeDecimal($load['total_render_hours'] ?? null),
                'status_code' => $this->normalizeText($load['status_code'] ?? null),
            ];
        }

        usort($normalized, function (array $left, array $right): int {
            return [$left['session_date'], $left['skolaris_offering_id'] ?? 0, $left['time_in'] ?? '', $left['subject_code'] ?? '']
                <=> [$right['session_date'], $right['skolaris_offering_id'] ?? 0, $right['time_in'] ?? '', $right['subject_code'] ?? ''];
        });

        return $normalized;
    }

    /**
     * @param  iterable<int, TeachingLoadSession>  $sessions
     */
    private function fingerprintExisting(iterable $sessions): string
    {
        $rows = [];

        foreach ($sessions as $session) {
            $rows[] = [
                'session_date' => optional($session->session_date)->format('Y-m-d') ?? '',
                'employee_number' => $this->normalizeText($session->employee_number),
                'skolaris_offering_id' => $session->skolaris_offering_id !== null ? (int) $session->skolaris_offering_id : null,
                'subject_code' => $this->normalizeText($session->subject_code),
                'subject_name' => $this->normalizeText($session->subject_name),
                'section' => $this->normalizeText($session->section),
                'campus_name' => $this->normalizeText($session->campus_name),
                'room' => $this->normalizeText($session->room),
                'schedule_day' => $this->normalizeText($session->schedule_day),
                'class_schedule' => $this->normalizeText($session->class_schedule),
                'time_in' => $this->normalizeText($session->time_in),
                'time_out' => $this->normalizeText($session->time_out),
                'total_hours' => $this->normalizeDecimal($session->total_hours),
                'total_render_hours' => $this->normalizeDecimal($session->total_render_hours),
                'status_code' => $this->normalizeText($session->status_code),
            ];
        }

        usort($rows, function (array $left, array $right): int {
            return [$left['session_date'], $left['skolaris_offering_id'] ?? 0, $left['time_in'] ?? '', $left['subject_code'] ?? '']
                <=> [$right['session_date'], $right['skolaris_offering_id'] ?? 0, $right['time_in'] ?? '', $right['subject_code'] ?? ''];
        });

        return $this->fingerprintRows($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $loads
     */
    private function fingerprintEmployeeLoadRows(array $loads): string
    {
        $rows = [];

        foreach ($loads as $load) {
            $subject = trim(implode(' — ', array_filter([
                $this->normalizeText($load['subject_code'] ?? null),
                $this->normalizeText($load['subject_name'] ?? null),
            ])));

            $rows[] = [
                'session_date' => (string) ($load['session_date'] ?? ''),
                'skolaris_offering_id' => isset($load['skolaris_offering_id']) ? (int) $load['skolaris_offering_id'] : null,
                'subject' => $subject !== '' ? $subject : null,
                'section' => $this->normalizeText($load['section'] ?? null),
                'class_schedule' => $this->normalizeText($load['class_schedule'] ?? null),
                'total_hours' => $this->normalizeDecimal($load['total_hours'] ?? null),
                'remarks' => $this->normalizeText($load['status_code'] ?? null),
            ];
        }

        usort($rows, function (array $left, array $right): int {
            return [$left['session_date'], $left['skolaris_offering_id'] ?? 0, $left['class_schedule'] ?? '', $left['subject'] ?? '']
                <=> [$right['session_date'], $right['skolaris_offering_id'] ?? 0, $right['class_schedule'] ?? '', $right['subject'] ?? ''];
        });

        return $this->fingerprintRows($rows);
    }

    /**
     * @param  iterable<int, RawEmployeeLoadEntry>  $entries
     */
    private function fingerprintEmployeeLoadEntries(iterable $entries): string
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'session_date' => optional($entry->session_date)->format('Y-m-d') ?? '',
                'skolaris_offering_id' => $entry->skolaris_offering_id !== null ? (int) $entry->skolaris_offering_id : null,
                'subject' => $this->normalizeText($entry->subject),
                'section' => $this->normalizeText($entry->section),
                'class_schedule' => $this->normalizeText($entry->class_schedule),
                'total_hours' => $this->normalizeDecimal($entry->total_hours),
                'remarks' => $this->normalizeText($entry->remarks),
            ];
        }

        usort($rows, function (array $left, array $right): int {
            return [$left['session_date'], $left['skolaris_offering_id'] ?? 0, $left['class_schedule'] ?? '', $left['subject'] ?? '']
                <=> [$right['session_date'], $right['skolaris_offering_id'] ?? 0, $right['class_schedule'] ?? '', $right['subject'] ?? ''];
        });

        return $this->fingerprintRows($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fingerprintRows(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return $this->normalizeText($value);
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function progressPayload(array $job, bool $done, array $extra = []): array
    {
        $current = (int) ($job['current'] ?? 0);
        $total = max(1, (int) ($job['total'] ?? 1));

        return array_merge([
            'current' => $current,
            'total' => (int) ($job['total'] ?? 0),
            'percent' => (int) round(($current / $total) * 100),
            'done' => $done,
            'errors' => $job['errors'] ?? [],
            'updated_count' => (int) ($job['updated_count'] ?? 0),
            'unchanged_count' => (int) ($job['unchanged_count'] ?? 0),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJob(string $token): array
    {
        $job = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($job)) {
            throw new RuntimeException('Pull job expired or not found. Please start again.');
        }

        return $job;
    }
}
