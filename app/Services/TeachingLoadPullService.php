<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\TeachingLoadPullBatch;
use App\Models\TeachingLoadPullBatchEmployee;
use App\Models\TeachingLoadSession;
use App\Models\TeachingLoadSyncStatus;
use App\Models\User;
use App\Support\EmployeeNumberMatch;
use App\Support\PhpExecutionTime;
use App\Support\SkolarisCheckerLoadStatus;
use App\Support\SkolarisLoadSessionTimes;
use App\Support\SkolarisScheduleWeekdays;
use App\Support\TimeLogs;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TeachingLoadPullService
{
    private const CACHE_PREFIX = 'teaching_load_pull:';

    private const CANCEL_KEY = 'teaching_load_pull:cancel';

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

        Cache::forget(self::CANCEL_KEY);

        $token = Str::uuid()->toString();

        $pullBatch = TeachingLoadPullBatch::query()->create([
            'batch_no' => ((int) TeachingLoadPullBatch::query()->max('batch_no')) + 1,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'employee_count' => count($eligibleIds),
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

        foreach ($eligibleIds as $selectedEmployeeId) {
            TeachingLoadPullBatchEmployee::query()->create([
                'teaching_load_pull_batch_id' => $pullBatch->teaching_load_pull_batch_id,
                'employee_id' => $selectedEmployeeId,
                'rows_count' => 0,
                'status' => 'pending',
            ]);
        }

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
        PhpExecutionTime::ensureAtLeast(max(30, (int) config('employee_load.pull_step_time_limit_seconds', 900)));

        $job = $this->getJob($token);

        if (Cache::get(self::CANCEL_KEY)) {
            $job['status'] = 'cancelled';
            Cache::put(self::CACHE_PREFIX.$token, $job, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $this->progressPayload($job, true, [
                'sync_status' => 'error',
                'error' => 'Pull cancelled.',
            ]);
        }

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

        $batchSize = max(1, (int) config('employee_load.pull_employees_per_step', 10));
        $slice = array_slice($remaining, 0, $batchSize);
        $employees = Employee::query()->whereIn('employee_id', $slice)->get()->keyBy('employee_id');
        $eligible = array_fill_keys(TimeLogs::eligibleEmployeeIds($slice), true);
        $skolarisIds = [];

        foreach ($slice as $employeeId) {
            $employee = $employees->get($employeeId);

            if ($employee === null || ! isset($eligible[$employeeId])) {
                continue;
            }

            $skolarisIds[$employeeId] = (int) ($this->skolaris->resolveSkolarisEmployeeRecord($employee)['employee_id'] ?? 0);
        }

        $logsBySkolarisId = $this->skolaris->timekeepingEmployeeAttendanceMany(
            array_values($skolarisIds),
            $job['date_from'],
            $job['date_to'],
        );

        $stepRecords = 0;
        $lastNumber = null;
        $lastStatus = null;
        $lastError = null;

        foreach ($slice as $employeeId) {
            $employee = $employees->get($employeeId);

            if ($employee === null || ! isset($eligible[$employeeId])) {
                $job['errors'][] = [
                    'employee_id' => $employeeId,
                    'message' => 'Employee is not eligible for teaching load pull.',
                ];
                $job['completed'][] = $employeeId;
                $lastNumber = $employee?->employee_number;
                $lastStatus = 'error';
                $lastError = 'Employee is not eligible for teaching load pull.';

                continue;
            }

            $skolarisId = (int) ($skolarisIds[$employeeId] ?? 0);
            $prefetched = $skolarisId > 0 ? ($logsBySkolarisId[$skolarisId] ?? []) : null;

            try {
                $result = $this->pullEmployee(
                    $employee,
                    $job['date_from'],
                    $job['date_to'],
                    (int) $job['user_id'],
                    (int) ($job['pull_batch_id'] ?? 0),
                    (int) ($job['employee_load_transaction_id'] ?? 0),
                    $prefetched,
                );
                $error = null;
            } catch (Throwable $exception) {
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
            $this->recordBatchEmployee(
                (int) ($job['pull_batch_id'] ?? 0),
                $employeeId,
                (int) ($result['records_count'] ?? 0),
                (string) ($result['sync_status'] ?? 'empty'),
            );
            $stepRecords += (int) ($result['records_count'] ?? 0);
            $lastNumber = $employee->employee_number;
            $lastStatus = $result['sync_status'] ?? null;
            $lastError = $error;

            if (($result['sync_status'] ?? '') === 'unchanged') {
                $job['unchanged_count'] = (int) ($job['unchanged_count'] ?? 0) + 1;
            } elseif (($result['sync_status'] ?? '') === 'updated') {
                $job['updated_count'] = (int) ($job['updated_count'] ?? 0) + 1;
                $job['records_count'] = (int) ($job['records_count'] ?? 0) + (int) ($result['records_count'] ?? 0);
            }
        }

        $job['current'] = count($job['completed']);

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
            'employee_number' => count($slice) > 1 ? count($slice).' employees (last '.$lastNumber.')' : $lastNumber,
            'records_count' => $stepRecords,
            'sync_status' => $lastStatus,
            'error' => $lastError,
        ]);
    }

    private function recordBatchEmployee(int $pullBatchId, int $employeeId, int $rowsCount, string $status): void
    {
        if ($pullBatchId <= 0 || $employeeId <= 0) {
            return;
        }

        TeachingLoadPullBatchEmployee::query()->updateOrCreate(
            [
                'teaching_load_pull_batch_id' => $pullBatchId,
                'employee_id' => $employeeId,
            ],
            [
                'rows_count' => max(0, $rowsCount),
                'status' => $status,
            ],
        );
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
        ?array $prefetchedLogs = null,
    ): array {
        $employeeNumber = trim((string) $employee->employee_number);

        if ($employeeNumber === '') {
            throw new RuntimeException('Employee has no employee number.');
        }

        PhpExecutionTime::ensureAtLeast(max(30, (int) config('employee_load.pull_step_time_limit_seconds', 900)));

        $skolarisEmployee = $prefetchedLogs === null
            ? $this->skolaris->resolveSkolarisEmployeeRecord($employee)
            : null;
        $skolarisEmployeeId = (int) ($skolarisEmployee['employee_id'] ?? 0);
        $skolarisNumber = trim((string) ($skolarisEmployee['employee_number'] ?? ''));

        $incoming = [];

        if ($prefetchedLogs !== null) {
            $incoming = $this->normalizeIncomingLoads($prefetchedLogs, $employeeNumber);
        } elseif ($skolarisEmployeeId > 0) {
            $attendance = $this->skolaris->timekeepingEmployeeAttendance(
                $skolarisEmployeeId,
                $dateFrom,
                $dateTo,
            );
            $incoming = $this->normalizeIncomingLoads($attendance['logs'], $employeeNumber);
        }

        if ($incoming === [] && $prefetchedLogs === null) {
            $dailyLoadNumbers = array_values(array_unique(array_filter([
                $employeeNumber,
                $skolarisNumber,
            ])));

            $rows = $this->skolaris->dailyLoads($dateFrom, $dateTo, $dailyLoadNumbers);
            $employeePayload = null;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowNumber = trim((string) ($row['employee_number'] ?? ''));

                if ($rowNumber === $employeeNumber
                    || ($skolarisNumber !== '' && EmployeeNumberMatch::same($rowNumber, $skolarisNumber))) {
                    $employeePayload = $row;
                    break;
                }
            }

            $incoming = $this->normalizeIncomingLoads(
                is_array($employeePayload['loads'] ?? null) ? $employeePayload['loads'] : [],
                $employeeNumber,
            );
        }

        $useSlowFallbacks = (bool) config('employee_load.pull_slow_fallbacks', false);

        if ($incoming === [] && $useSlowFallbacks) {
            $incoming = $this->fetchUploadedFacultyLoadingSchedules(
                $employee,
                $dateFrom,
                $dateTo,
            );
        }

        if ($incoming !== [] && $prefetchedLogs === null) {
            $incoming = $this->mergeAttendanceCheckerLoads(
                $employeeNumber,
                $dateFrom,
                $dateTo,
                $incoming,
            );
        } elseif ($useSlowFallbacks && $prefetchedLogs === null) {
            $incoming = $this->mergeAttendanceCheckerLoads(
                $employeeNumber,
                $dateFrom,
                $dateTo,
                $incoming,
            );
        }

        if ($incoming === []) {
            $suffix = $useSlowFallbacks
                ? ' Also tried Attendance Checker and Uploaded Faculty Loading.'
                : ' Run Process Attendance on Skolaris for that faculty and date range, or set EMPLOYEE_LOAD_PULL_SLOW_FALLBACKS=true for PDF/checker fallbacks.';

            throw new RuntimeException(
                'No teaching load in Skolaris Employee Attendance for this date range (timekeeping/employees/{employee_id}/attendance).'
                .$suffix
            );
        }

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

                $sessionTimes = SkolarisLoadSessionTimes::forEmployeeLoadEntry(
                    is_array($load) ? $load : [],
                );

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
                    'time_in' => $sessionTimes['time_in'],
                    'time_out' => $sessionTimes['time_out'],
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

            $sessionTimes = SkolarisLoadSessionTimes::forEmployeeLoadEntry($load);

            $normalized[] = [
                'session_date' => $sessionDate,
                'employee_number' => $employeeNumber,
                'skolaris_offering_id' => isset($load['offering_id']) ? (int) $load['offering_id'] : null,
                'subject_code' => $this->normalizeText($load['subject_code'] ?? null),
                'subject_name' => $this->normalizeText($load['subject_name'] ?? null),
                'section' => $this->normalizeText($load['section'] ?? null),
                'campus_id' => isset($load['campus_id']) ? (int) $load['campus_id'] : null,
                'campus_name' => $this->normalizeText($load['campus_name'] ?? null),
                'room' => $this->normalizeText($load['room'] ?? null),
                'schedule_day' => $this->normalizeText($load['schedule_day'] ?? null),
                'class_schedule' => $this->normalizeText($load['schedule'] ?? null),
                'time_in' => $sessionTimes['time_in'],
                'time_out' => $sessionTimes['time_out'],
                'total_hours' => $this->normalizeDecimal($load['total_hours'] ?? null),
                'total_render_hours' => $this->normalizeDecimal($load['total_render_hours'] ?? null),
                'status_code' => SkolarisCheckerLoadStatus::statusCodeFromChecker(
                    $load['status_code'] ?? $load['status'] ?? '',
                    $load['present_mode'] ?? '',
                ),
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

    /**
     * Merge Skolaris Attendance Checker schedules and P/A/L/U/E/M marks into pulled loads.
     *
     * @param  array<int, array<string, mixed>>  $loads
     * @return array<int, array<string, mixed>>
     */
    private function mergeAttendanceCheckerLoads(
        string $employeeNumber,
        string $dateFrom,
        string $dateTo,
        array $loads,
    ): array {
        if ($loads === []) {
            return $this->fetchCheckerSchedulesForPeriod($employeeNumber, $dateFrom, $dateTo);
        }

        try {
            $campusIds = $this->resolveAttendanceCheckerCampusIds($loads, $dateFrom);
        } catch (Throwable $exception) {
            Log::warning('Attendance checker campus lookup skipped during teaching load pull', [
                'employee_number' => $employeeNumber,
                'message' => $exception->getMessage(),
            ]);

            return $loads;
        }

        if ($campusIds === []) {
            return $loads;
        }

        $indexed = [];
        foreach ($loads as $load) {
            $indexed[$this->attendanceCheckerLoadKey($load, $employeeNumber)] = $load;
        }

        $dates = array_values(array_unique(array_filter(array_map(
            fn (array $load) => trim((string) ($load['session_date'] ?? '')),
            $loads,
        ))));
        sort($dates);

        if ($dates === []) {
            return $loads;
        }

        $this->mergeCheckerSchedulesIntoIndex(
            $indexed,
            $employeeNumber,
            $campusIds,
            $dates,
        );

        return $this->sortNormalizedLoads(array_values($indexed));
    }

    /**
     * When daily-loads returns nothing, build rows from Attendance Checker (same source as Skolaris checker UI).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCheckerSchedulesForPeriod(
        string $employeeNumber,
        string $dateFrom,
        string $dateTo,
    ): array {
        $maxDays = max(1, (int) config('employee_load.max_template_days', 45));

        try {
            $period = CarbonPeriod::create($dateFrom, $dateTo);
        } catch (Throwable $exception) {
            Log::warning('Attendance checker period skipped during teaching load pull', [
                'employee_number' => $employeeNumber,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $indexed = [];
        $dayCount = 0;

        foreach ($period as $date) {
            $dayCount++;

            if ($dayCount > $maxDays) {
                break;
            }

            $dateString = $date->toDateString();
            $campusIds = $this->checkerCampusIdsWithSchedulesForEmployee($dateString, $employeeNumber);

            if ($campusIds === []) {
                continue;
            }

            $this->mergeCheckerSchedulesIntoIndex(
                $indexed,
                $employeeNumber,
                $campusIds,
                [$dateString],
            );
        }

        return $this->sortNormalizedLoads(array_values($indexed));
    }

    /**
     * Build session rows from Skolaris People360 Uploaded Faculty Loading (parsed PDF grid).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchUploadedFacultyLoadingSchedules(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
    ): array {
        if (! config('employee_load.pull_uploaded_faculty_loading', true)) {
            return [];
        }

        $employeeNumber = trim((string) $employee->employee_number);

        try {
            $subjectRows = $this->skolaris->uploadedFacultyLoadSubjectItemsForEmployee(
                $employee,
                $dateFrom,
                $dateTo,
            );
        } catch (Throwable $exception) {
            Log::warning('Uploaded faculty loading list skipped during teaching load pull', [
                'employee_number' => $employeeNumber,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if ($subjectRows === []) {
            return [];
        }

        $indexed = [];
        $sessionCap = max(500, (int) config('employee_load.max_template_days', 45) * 24);

        foreach ($subjectRows as $row) {
            $detail = is_array($row['upload'] ?? null) ? $row['upload'] : [];
            $item = is_array($row['item'] ?? null) ? $row['item'] : [];
            $defaultCampus = $this->normalizeText($detail['campus_name'] ?? null);

            foreach ($this->expandUploadedFacultyLoadItem(
                $item,
                $employeeNumber,
                $dateFrom,
                $dateTo,
                $detail,
                $defaultCampus,
            ) as $normalized) {
                $key = $this->attendanceCheckerLoadKey($normalized, $employeeNumber);
                $indexed[$key] = $normalized;

                if (count($indexed) >= $sessionCap) {
                    break 2;
                }
            }
        }

        return $this->sortNormalizedLoads(array_values($indexed));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $upload
     * @return array<int, array<string, mixed>>
     */
    private function expandUploadedFacultyLoadItem(
        array $item,
        string $employeeNumber,
        string $dateFrom,
        string $dateTo,
        array $upload,
        ?string $defaultCampus,
    ): array {
        $weekdays = SkolarisScheduleWeekdays::fromDayField($item['day'] ?? null);

        if ($weekdays === []) {
            return [];
        }

        $expandFrom = $dateFrom;
        $expandTo = $dateTo;

        foreach ([
            $item['period_start'] ?? null,
            $upload['period_start'] ?? null,
        ] as $start) {
            $start = trim((string) $start);

            if ($start !== '' && $start > $expandFrom) {
                $expandFrom = $start;
            }
        }

        foreach ([
            $item['period_end'] ?? null,
            $upload['period_end'] ?? null,
        ] as $end) {
            $end = trim((string) $end);

            if ($end !== '' && $end < $expandTo) {
                $expandTo = $end;
            }
        }

        if ($expandFrom > $expandTo) {
            return [];
        }

        $classSchedule = $this->normalizeText($item['class_schedule'] ?? null);
        $sessionTimes = SkolarisLoadSessionTimes::forEmployeeLoadEntry([
            'class_schedule' => $classSchedule,
        ]);

        $statusCode = SkolarisCheckerLoadStatus::statusCodeFromChecker('', '');

        $rows = [];

        foreach (SkolarisScheduleWeekdays::datesInRange($expandFrom, $expandTo, $weekdays) as $sessionDate) {
            $normalized = [
                'session_date' => $sessionDate,
                'employee_number' => $employeeNumber,
                'skolaris_offering_id' => isset($item['item_id']) ? (int) $item['item_id'] : null,
                'subject_code' => $this->normalizeText($item['subject_code'] ?? null),
                'subject_name' => $this->normalizeText($item['title'] ?? null),
                'section' => $this->normalizeText($item['section'] ?? null),
                'campus_id' => null,
                'campus_name' => $this->normalizeText($item['campus_name'] ?? $defaultCampus),
                'room' => $this->normalizeText($item['room'] ?? null),
                'schedule_day' => $this->normalizeText($item['day'] ?? null),
                'class_schedule' => $classSchedule,
                'time_in' => $sessionTimes['time_in'],
                'time_out' => $sessionTimes['time_out'],
                'total_hours' => null,
                'total_render_hours' => null,
                'status_code' => $statusCode,
            ];

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $indexed
     * @param  array<int, int>  $campusIds
     * @param  array<int, string>  $dates
     */
    private function mergeCheckerSchedulesIntoIndex(
        array &$indexed,
        string $employeeNumber,
        array $campusIds,
        array $dates,
    ): void {
        foreach ($campusIds as $campusId) {
            foreach ($dates as $dateString) {
                try {
                    $payload = $this->skolaris->attendanceCheckerDaily(
                        (int) $campusId,
                        $dateString,
                        [$employeeNumber],
                    );
                } catch (Throwable $exception) {
                    Log::warning('Attendance checker daily fetch skipped during teaching load pull', [
                        'employee_number' => $employeeNumber,
                        'campus_id' => $campusId,
                        'date' => $dateString,
                        'message' => $exception->getMessage(),
                    ]);

                    continue;
                }

                foreach ($payload['schedules'] as $schedule) {
                    if (! is_array($schedule)) {
                        continue;
                    }

                    if (trim((string) ($schedule['employee_number'] ?? '')) !== $employeeNumber) {
                        continue;
                    }

                    $normalized = $this->normalizeAttendanceCheckerSchedule(
                        $schedule,
                        $employeeNumber,
                        $payload['campus'] ?? null,
                    );
                    $key = $this->attendanceCheckerLoadKey($normalized, $employeeNumber);

                    if (isset($indexed[$key])) {
                        $indexed[$key]['status_code'] = $normalized['status_code'];

                        continue;
                    }

                    $indexed[$key] = $normalized;
                }
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function checkerCampusIdsWithSchedulesForEmployee(string $date, string $employeeNumber): array
    {
        try {
            $campuses = $this->skolaris->attendanceCheckerCampuses($date, [$employeeNumber]);
        } catch (Throwable $exception) {
            Log::warning('Attendance checker campus lookup skipped during teaching load pull', [
                'employee_number' => $employeeNumber,
                'date' => $date,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $ids = [];

        foreach ($campuses as $campus) {
            if (! is_array($campus)) {
                continue;
            }

            if ((int) ($campus['schedule_count'] ?? 0) <= 0) {
                continue;
            }

            $campusId = (int) ($campus['campus_id'] ?? 0);

            if ($campusId > 0) {
                $ids[] = $campusId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, array<string, mixed>>  $loads
     * @return array<int, array<string, mixed>>
     */
    private function sortNormalizedLoads(array $loads): array
    {
        usort($loads, function (array $left, array $right): int {
            return [$left['session_date'], $left['skolaris_offering_id'] ?? 0, $left['time_in'] ?? '', $left['subject_code'] ?? '']
                <=> [$right['session_date'], $right['skolaris_offering_id'] ?? 0, $right['time_in'] ?? '', $right['subject_code'] ?? ''];
        });

        return $loads;
    }

    /**
     * @param  array<int, array<string, mixed>>  $loads
     * @return array<int, int>
     */
    private function resolveAttendanceCheckerCampusIds(array $loads, string $dateFrom): array
    {
        $campusIds = array_values(array_unique(array_filter(array_map(
            fn (array $load) => isset($load['campus_id']) ? (int) $load['campus_id'] : null,
            $loads,
        ))));

        if ($campusIds !== []) {
            return $campusIds;
        }

        $campusNames = array_values(array_unique(array_filter(array_map(
            fn (array $load) => trim((string) ($load['campus_name'] ?? '')),
            $loads,
        ))));

        $skolarisCampuses = $this->skolaris->attendanceCheckerCampuses($dateFrom);
        $resolved = [];

        foreach ($skolarisCampuses as $campus) {
            if (! is_array($campus)) {
                continue;
            }

            $campusId = (int) ($campus['campus_id'] ?? 0);
            $campusName = trim((string) ($campus['campus_name'] ?? ''));

            if ($campusId <= 0) {
                continue;
            }

            if ($campusNames !== [] && in_array($campusName, $campusNames, true)) {
                $resolved[] = $campusId;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>|null  $campus
     * @return array<string, mixed>
     */
    private function normalizeAttendanceCheckerSchedule(array $schedule, string $employeeNumber, ?array $campus): array
    {
        $statusCode = SkolarisCheckerLoadStatus::statusCodeFromChecker(
            $schedule['status'] ?? '',
            $schedule['present_mode'] ?? '',
        );

        return [
            'session_date' => (string) ($schedule['attendance_date'] ?? ''),
            'employee_number' => $employeeNumber,
            'skolaris_offering_id' => isset($schedule['offering_id']) ? (int) $schedule['offering_id'] : null,
            'subject_code' => $this->normalizeText($schedule['subject_code'] ?? null),
            'subject_name' => $this->normalizeText($schedule['subject_name'] ?? null),
            'section' => $this->normalizeText($schedule['section'] ?? null),
            'campus_id' => isset($campus['campus_id']) ? (int) $campus['campus_id'] : null,
            'campus_name' => $this->normalizeText($campus['campus_name'] ?? null),
            'room' => $this->normalizeText($schedule['room'] ?? null),
            'schedule_day' => $this->normalizeText($schedule['schedule_day'] ?? null),
            'class_schedule' => $this->normalizeText($schedule['schedule'] ?? null),
            'time_in' => $this->normalizeText($schedule['scheduled_time_in'] ?? null),
            'time_out' => $this->normalizeText($schedule['scheduled_time_out'] ?? null),
            'total_hours' => null,
            'total_render_hours' => null,
            'status_code' => $statusCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $load
     */
    private function attendanceCheckerLoadKey(array $load, string $employeeNumber): string
    {
        return implode('|', [
            (string) ($load['session_date'] ?? ''),
            $employeeNumber,
            strtolower((string) ($load['subject_code'] ?? '')),
            strtolower((string) ($load['section'] ?? '')),
            (string) ($load['time_in'] ?? ''),
            (string) ($load['time_out'] ?? ''),
        ]);
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
