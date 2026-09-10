<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\RawTimekeepingTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BiometricCollectorDashboardService
{
    public function __construct(
        private readonly BiometricLogsS3PullService $biometricLogsS3PullService,
    ) {}

    /**
     * @return array{
     *     configured: bool,
     *     bucket: string|null,
     *     region: string|null,
     *     reference_date: string,
     *     reference_date_label: string,
     *     collected_today: array<int, array<string, mixed>>,
     *     missing_today: array<int, array<string, mixed>>,
     *     unmapped_collectors: array<int, array<string, mixed>>,
     *     error: string|null,
     * }
     */
    public function statusForDate(?Carbon $onDate = null): array
    {
        $onDate = ($onDate ?? now())->copy()->startOfDay();
        $cacheKey = 'biometric_collector_dashboard_'.$onDate->toDateString();

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($onDate): array {
            return $this->buildStatus($onDate);
        });
    }

    /**
     * @return array{
     *     configured: bool,
     *     bucket: string|null,
     *     region: string|null,
     *     reference_date: string,
     *     reference_date_label: string,
     *     collected_today: array<int, array<string, mixed>>,
     *     missing_today: array<int, array<string, mixed>>,
     *     unmapped_collectors: array<int, array<string, mixed>>,
     *     error: string|null,
     * }
     */
    private function buildStatus(Carbon $onDate): array
    {
        $base = [
            'configured' => $this->biometricLogsS3PullService->isConfigured(),
            'bucket' => config('biometric_logs.s3.bucket'),
            'region' => config('biometric_logs.s3.region'),
            'reference_date' => $onDate->toDateString(),
            'reference_date_label' => $onDate->format('F j, Y'),
            'collected_today' => [],
            'missing_today' => [],
            'unmapped_collectors' => [],
            'error' => null,
        ];

        if (! $base['configured']) {
            $base['error'] = 'Biometric S3 is not configured. Set DB_BACKUP_S3_* or BIOMETRIC_LOGS_S3_* in .env.';

            return $base;
        }

        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('campus_name')
            ->get();

        $lastPulledByCampus = $this->lastPulledAtByCampus();

        try {
            $monthActivity = $this->scanMonthCollectorActivity($onDate);
        } catch (Throwable $exception) {
            report($exception);
            $base['error'] = 'Unable to read biometric_logs from S3. Check credentials and network.';

            return $base;
        }

        $lastS3CollectByCampus = $this->lastS3CollectAtByCampus($monthActivity);

        /** @var array<int, array<string, mixed>> $collectedByCampusId */
        $collectedByCampusId = [];

        foreach ($monthActivity['today'] as $folder => $upload) {
            $campus = $this->biometricLogsS3PullService->matchCampusFromCollectorFolder($folder);

            if ($campus === null) {
                $base['unmapped_collectors'][] = [
                    'collector_folder' => $folder,
                    'file_count' => $upload['file_count'],
                    'latest_collect_at' => $upload['latest_collect_at']?->toDateTimeString(),
                    'latest_collect_label' => $this->formatTimestamp($upload['latest_collect_at']),
                ];

                continue;
            }

            $campusId = (int) $campus->campus_id;
            $existing = $collectedByCampusId[$campusId] ?? null;

            if ($existing === null) {
                $collectedByCampusId[$campusId] = $this->campusRow(
                    $campus,
                    $folder,
                    $upload,
                    $lastPulledByCampus,
                    $lastS3CollectByCampus,
                );

                continue;
            }

            $collectedByCampusId[$campusId]['collector_folders'][] = $folder;
            $collectedByCampusId[$campusId]['file_count'] += $upload['file_count'];

            if (
                $upload['latest_collect_at'] !== null
                && (
                    $existing['latest_collect_at'] === null
                    || $upload['latest_collect_at']->gt(Carbon::parse($existing['latest_collect_at']))
                )
            ) {
                $collectedByCampusId[$campusId]['latest_collect_at'] = $upload['latest_collect_at']->toDateTimeString();
                $collectedByCampusId[$campusId]['latest_collect_label'] = $this->formatTimestamp($upload['latest_collect_at']);
            }
        }

        $base['collected_today'] = collect($collectedByCampusId)
            ->sortBy(fn (array $row) => $row['campus_name'])
            ->values()
            ->all();

        foreach ($campuses as $campus) {
            if (isset($collectedByCampusId[(int) $campus->campus_id])) {
                continue;
            }

            $base['missing_today'][] = $this->campusRow($campus, null, null, $lastPulledByCampus, $lastS3CollectByCampus);
        }

        usort($base['missing_today'], fn (array $a, array $b) => strcmp($a['campus_name'], $b['campus_name']));

        return $base;
    }

    /**
     * @return array{
     *     today: array<string, array{file_count: int, latest_collect_at: ?Carbon}>,
     *     latest_by_folder: array<string, ?Carbon>,
     * }
     */
    private function scanMonthCollectorActivity(Carbon $onDate): array
    {
        $dateKey = $onDate->format('Ymd');
        $prefix = $this->biometricLogsS3PullService->monthPrefix(
            (int) $onDate->format('Y'),
            (int) $onDate->format('m'),
        );

        $disk = Storage::disk($this->biometricLogsS3PullService->disk());

        try {
            $files = $disk->allFiles($prefix);
        } catch (Throwable $exception) {
            throw $exception;
        }

        /** @var array<string, array{file_count: int, latest_collect_at: ?Carbon}> $todayUploads */
        $todayUploads = [];

        /** @var array<string, ?Carbon> $latestByFolder */
        $latestByFolder = [];

        foreach ($files as $path) {
            $lower = strtolower($path);

            if (! str_ends_with($lower, '.json.gzip') && ! str_ends_with($lower, '.json.gz')) {
                continue;
            }

            if (! preg_match('/(\d{14})\.json\.(?:gzip|gz)$/i', $path, $matches)) {
                continue;
            }

            $folder = basename(dirname($path));

            if ($folder === '' || $folder === '.' || $folder === '..') {
                continue;
            }

            try {
                $stamp = Carbon::createFromFormat('YmdHis', $matches[1], config('app.timezone'));
            } catch (Throwable) {
                continue;
            }

            if (! isset($latestByFolder[$folder]) || $latestByFolder[$folder] === null || $stamp->gt($latestByFolder[$folder])) {
                $latestByFolder[$folder] = $stamp;
            }

            if (! str_starts_with($matches[1], $dateKey)) {
                continue;
            }

            if (! isset($todayUploads[$folder])) {
                $todayUploads[$folder] = [
                    'file_count' => 0,
                    'latest_collect_at' => null,
                ];
            }

            $todayUploads[$folder]['file_count']++;

            if ($todayUploads[$folder]['latest_collect_at'] === null || $stamp->gt($todayUploads[$folder]['latest_collect_at'])) {
                $todayUploads[$folder]['latest_collect_at'] = $stamp;
            }
        }

        return [
            'today' => $todayUploads,
            'latest_by_folder' => $latestByFolder,
        ];
    }

    /**
     * @param  array{
     *     today: array<string, array{file_count: int, latest_collect_at: ?Carbon}>,
     *     latest_by_folder: array<string, ?Carbon>,
     * }  $monthActivity
     * @return Collection<int, Carbon>
     */
    private function lastS3CollectAtByCampus(array $monthActivity): Collection
    {
        /** @var array<int, Carbon> $byCampus */
        $byCampus = [];

        foreach ($monthActivity['latest_by_folder'] as $folder => $stamp) {
            if (! $stamp instanceof Carbon) {
                continue;
            }

            $campus = $this->biometricLogsS3PullService->matchCampusFromCollectorFolder($folder);

            if ($campus === null) {
                continue;
            }

            $campusId = (int) $campus->campus_id;
            $existing = $byCampus[$campusId] ?? null;

            if ($existing === null || $stamp->gt($existing)) {
                $byCampus[$campusId] = $stamp;
            }
        }

        return collect($byCampus);
    }

    /**
     * @param  Collection<int, int|string|null>  $lastPulledByCampus
     * @param  Collection<int, Carbon>  $lastS3CollectByCampus
     * @param  array{file_count: int, latest_collect_at: ?Carbon}|null  $upload
     * @return array<string, mixed>
     */
    private function campusRow(
        Campus $campus,
        ?string $collectorFolder,
        ?array $upload,
        Collection $lastPulledByCampus,
        Collection $lastS3CollectByCampus,
    ): array {
        $campusId = (int) $campus->campus_id;
        $lastPulledRaw = $lastPulledByCampus->get($campusId);
        $lastPulledAt = $lastPulledRaw ? Carbon::parse($lastPulledRaw) : null;
        $lastS3CollectAt = $lastS3CollectByCampus->get($campusId);

        return [
            'campus_id' => $campusId,
            'campus_code' => (string) $campus->campus_code,
            'campus_name' => (string) $campus->campus_name,
            'collector_folders' => $collectorFolder !== null ? [$collectorFolder] : [],
            'file_count' => $upload['file_count'] ?? 0,
            'latest_collect_at' => isset($upload['latest_collect_at']) && $upload['latest_collect_at'] instanceof Carbon
                ? $upload['latest_collect_at']->toDateTimeString()
                : null,
            'latest_collect_label' => $this->formatTimestamp($upload['latest_collect_at'] ?? null),
            'last_s3_collect_at' => $lastS3CollectAt?->toDateTimeString(),
            'last_s3_collect_label' => $this->formatTimestamp($lastS3CollectAt),
            'last_pulled_at' => $lastPulledAt?->toDateTimeString(),
            'last_pulled_label' => $this->formatTimestamp($lastPulledAt),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function lastPulledAtByCampus(): Collection
    {
        /** @var array<int, Carbon> $byCampus */
        $byCampus = [];

        RawTimekeepingTransaction::query()
            ->where('filename', 'like', 'biometric_logs/%')
            ->orderBy('timekeeping_transaction_id')
            ->chunkById(500, function ($transactions) use (&$byCampus): void {
                foreach ($transactions as $transaction) {
                    $campusId = $transaction->campus_id !== null
                        ? (int) $transaction->campus_id
                        : null;

                    if ($campusId === null) {
                        $folder = basename(dirname((string) $transaction->filename));
                        $campus = $this->biometricLogsS3PullService->matchCampusFromCollectorFolder($folder);
                        $campusId = $campus !== null ? (int) $campus->campus_id : null;
                    }

                    if ($campusId === null) {
                        continue;
                    }

                    $uploadedAt = Carbon::parse($transaction->dt_uploaded);
                    $existing = $byCampus[$campusId] ?? null;

                    if ($existing === null || $uploadedAt->gt($existing)) {
                        $byCampus[$campusId] = $uploadedAt;
                    }
                }
            }, 'timekeeping_transaction_id');

        return collect($byCampus)->map(fn (Carbon $value) => $value->toDateTimeString());
    }

    private function formatTimestamp(?Carbon $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }
}
