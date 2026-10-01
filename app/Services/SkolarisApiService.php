<?php

namespace App\Services;

use App\Support\EncryptedEnv;
use App\Support\SkolarisJwtCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Thin HTTP client for the Skolaris REST API (the same endpoints used by the
 * Skolaris frontend). Handles JWT login + refresh, caches the access token,
 * and retries once on a 401 by refreshing / re-logging in.
 */
class SkolarisApiService
{
    private const ACCESS_TOKEN_CACHE_KEY = 'skolaris_api:access_token';

    private const REFRESH_TOKEN_CACHE_KEY = 'skolaris_api:refresh_token';

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = (string) config('skolaris.base_url');
    }

    /**
     * Fetch enrollment periods for the dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function enrollmentPeriods(): array
    {
        $response = $this->request('get', '/enrollment-periods', ['per_page' => 200]);
        $payload = $response->json();

        $rows = $payload['data'] ?? $payload;

        if (isset($rows['data']) && is_array($rows['data'])) {
            $rows = $rows['data'];
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Fetch the faculty loading overview for an enrollment period.
     *
     * @return array<int, array<string, mixed>> list of campuses
     */
    public function facultyOverview(int $enrollmentPeriodId): array
    {
        $response = $this->request('get', '/faculty-grades/overview', [
            'enrollment_period_id' => $enrollmentPeriodId,
        ], $this->employeeLoadApiTimeout());

        $payload = $response->json();

        return $payload['data']['campuses'] ?? [];
    }

    /**
     * Fetch full offering details for a set of offering ids.
     *
     * @param  array<int, int>  $offeringIds
     * @return array<int, array<string, mixed>>
     */
    public function courseOfferingBatchDetails(array $offeringIds): array
    {
        $offeringIds = array_values(array_unique(array_map('intval', $offeringIds)));

        if ($offeringIds === []) {
            return [];
        }

        $results = [];

        $timeout = $this->employeeLoadApiTimeout();

        foreach (array_chunk($offeringIds, 200) as $chunk) {
            $response = $this->request('post', '/course-offerings/batch-details', [
                'offering_ids' => $chunk,
            ], $timeout);

            $rows = $response->json('data') ?? [];

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['offering_id'])) {
                    $results[(int) $row['offering_id']] = $row;
                }
            }
        }

        return $results;
    }


    /**
     * Fetch teaching load / daily schedule breakdown for a date range.
     *
     * @param  array<int, string>  $employeeNumbers
     * @return array<int, array<string, mixed>>
     */
    public function dailyLoads(string $dateFrom, string $dateTo, array $employeeNumbers = []): array
    {
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        if ($employeeNumbers !== []) {
            $params['employee_numbers'] = implode(',', array_values(array_unique(array_map('strval', $employeeNumbers))));
        }

        $timeout = $this->employeeLoadApiTimeout();

        if ($this->usesPulseApiKey()) {
            $response = $this->pulseApiRequest('get', '/timekeeping/daily-loads', $params, $timeout);
        } else {
            $response = $this->request('get', '/employees/timekeeping/daily-loads', $params, $timeout);
        }

        $rows = $response->json('data') ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Single-range daily-loads for Employee Load template (optionally file-cached).
     *
     * @return array<int, array<string, mixed>>
     */
    public function dailyLoadsForEmployeeLoadTemplate(string $dateFrom, string $dateTo): array
    {
        $cacheMinutes = max(0, (int) config('employee_load.daily_loads_response_cache_minutes', 15));

        if ($cacheMinutes > 0) {
            $path = $this->dailyLoadsTemplateCachePath($dateFrom, $dateTo);

            if (is_file($path) && filemtime($path) >= time() - ($cacheMinutes * 60)) {
                $raw = gzdecode((string) file_get_contents($path));

                if ($raw !== false && $raw !== '') {
                    $decoded = json_decode($raw, true);

                    if (is_array($decoded)) {
                        return array_values($decoded);
                    }
                }
            }
        }

        $rows = $this->dailyLoads($dateFrom, $dateTo);

        if ($cacheMinutes > 0 && $rows !== []) {
            $path = $this->dailyLoadsTemplateCachePath($dateFrom, $dateTo);
            $directory = dirname($path);

            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            @file_put_contents($path, gzencode(json_encode($rows), 6));
        }

        return $rows;
    }

    /**
     * Parallel daily-loads requests for large faculty lists (lower peak memory per response).
     *
     * @param  array<int, string>  $employeeNumbers
     * @return array<int, array<string, mixed>>
     */
    public function dailyLoadsConcurrent(string $dateFrom, string $dateTo, array $employeeNumbers): array
    {
        $employeeNumbers = array_values(array_unique(array_filter(array_map(
            fn ($number) => trim((string) $number),
            $employeeNumbers,
        ))));

        if ($employeeNumbers === []) {
            return [];
        }

        $chunkSize = max(1, (int) config('employee_load.daily_loads_chunk_size', 100));
        $parallel = max(1, (int) config('employee_load.daily_loads_parallel_requests', 8));
        $chunks = array_chunk($employeeNumbers, $chunkSize);
        $merged = [];

        foreach (array_chunk($chunks, $parallel) as $batchIndex => $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $batchIndex, $dateFrom, $dateTo) {
                $requests = [];

                foreach ($batch as $index => $chunk) {
                    $requests[] = $this->registerDailyLoadsPoolRequest(
                        $pool,
                        'batch_'.$batchIndex.'_chunk_'.$index,
                        $dateFrom,
                        $dateTo,
                        $chunk,
                    );
                }

                return $requests;
            });

            foreach ($responses as $response) {
                if (! $response instanceof Response || $response->failed()) {
                    continue;
                }

                foreach ($response->json('data') ?? [] as $row) {
                    if (is_array($row)) {
                        $merged[] = $row;
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, string>  $employeeNumbers
     */
    private function registerDailyLoadsPoolRequest(
        Pool $pool,
        string $as,
        string $dateFrom,
        string $dateTo,
        array $employeeNumbers,
    ): mixed {
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        if ($employeeNumbers !== []) {
            $params['employee_numbers'] = implode(',', $employeeNumbers);
        }

        $timeout = $this->employeeLoadApiTimeout();

        if ($this->usesPulseApiKey()) {
            $apiKey = EncryptedEnv::reveal((string) config('skolaris.pulse_api_key'));
            $baseUrl = (string) config('skolaris.pulse_api_base_url');

            return $pool->as($as)
                ->baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->get('/timekeeping/daily-loads', $params);
        }

        return $pool->as($as)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($timeout)
            ->withToken($this->accessToken())
            ->get('/employees/timekeeping/daily-loads', $params);
    }

    private function dailyLoadsTemplateCachePath(string $dateFrom, string $dateTo): string
    {
        $key = hash('sha256', $dateFrom.'|'.$dateTo);

        return storage_path('app/private/employee-load-daily-loads/'.$key.'.json.gz');
    }

    /**
     * @return array<int, string>
     */
    public function listEmployeeNumbers(): array
    {
        return Cache::remember('skolaris:employee_numbers', now()->addHours(6), function () {
            return $this->fetchAllEmployeeNumbers();
        });
    }

    public function forgetEmployeeNumberCache(): void
    {
        Cache::forget('skolaris:employee_numbers');
    }

    /**
     * @return array<int, string>
     */
    private function fetchAllEmployeeNumbers(): array
    {
        $numbers = [];

        if ($this->usesPulseApiKey()) {
            $numbers = array_merge($numbers, $this->fetchPulseEmployeeNumbersByMonth((int) now()->year));

            // Include prior year through current month for employees whose load spans terms.
            if (now()->month <= 6) {
                $numbers = array_merge($numbers, $this->fetchPulseEmployeeNumbersByMonth((int) now()->year - 1));
            }
        } else {
            $this->assertConfigured();
            $page = 1;

            do {
                $response = $this->request('get', '/employees', [
                    'status' => 'active',
                    'page' => $page,
                    'per_page' => 200,
                ]);

                $payload = $response->json();
                $rows = $payload['data'] ?? $payload;

                if (isset($rows['data']) && is_array($rows['data'])) {
                    $rows = $rows['data'];
                }

                if (! is_array($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $number = trim((string) ($row['employee_number'] ?? ''));

                    if ($number !== '') {
                        $numbers[] = $number;
                    }
                }

                $pagination = $payload['pagination'] ?? [];
                $hasMore = is_array($pagination)
                    ? (($pagination['current_page'] ?? $page) < ($pagination['last_page'] ?? $page))
                    : count($rows) === 200;
                $page++;
            } while ($hasMore && $page <= 500);
        }

        $numbers = array_values(array_unique($numbers));

        if ($numbers === []) {
            throw new RuntimeException('No employee numbers returned from Skolaris. Check API credentials.');
        }

        return $numbers;
    }

    /**
     * @return array<int, string>
     */
    private function fetchPulseEmployeeNumbersByMonth(int $year): array
    {
        $numbers = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthKey = sprintf('%04d-%02d', $year, $month);

            try {
                $response = $this->pulseApiRequest('get', '/timekeeping/daily-loads', [
                    'month' => $monthKey,
                ]);
            } catch (RuntimeException $exception) {
                Log::warning('Skolaris monthly daily-loads skipped', [
                    'month' => $monthKey,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            foreach ($response->json('data') ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $number = trim((string) ($row['employee_number'] ?? ''));

                if ($number !== '') {
                    $numbers[] = $number;
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attendanceCheckerCampuses(?string $date = null): array
    {
        $params = [];

        if ($date !== null && $date !== '') {
            $params['date'] = $date;
        }

        if ($this->usesPulseApiKey()) {
            $response = $this->pulseApiRequest('get', '/attendance-checker/campuses', $params);
        } else {
            $response = $this->request('get', '/employees/timekeeping/attendance-checker/campuses', $params);
        }

        return array_values($response->json('data') ?? []);
    }

    /**
     * @param  array<int, string>|null  $employeeNumbers
     * @return array{schedules: array<int, array<string, mixed>>, campus: array<string, mixed>|null, date: ?string}
     */
    public function attendanceCheckerDaily(int $campusId, string $date, ?array $employeeNumbers = null): array
    {
        $schedules = [];
        $page = 1;
        $lastPage = 1;
        $campus = null;
        $resolvedDate = $date;

        do {
            $params = [
                'campus_id' => $campusId,
                'date' => $date,
                'page' => $page,
                'per_page' => 50,
            ];

            if ($employeeNumbers !== null && $employeeNumbers !== []) {
                $params['employee_numbers'] = implode(',', array_values(array_unique(array_map('strval', $employeeNumbers))));
            }

            if ($this->usesPulseApiKey()) {
                $response = $this->pulseApiRequest('get', '/attendance-checker/daily', $params);
            } else {
                $response = $this->request('get', '/employees/timekeeping/attendance-checker/daily', $params);
            }

            $payload = $response->json('data') ?? [];
            $campus = is_array($payload['campus'] ?? null) ? $payload['campus'] : $campus;
            $resolvedDate = is_string($payload['date'] ?? null) ? $payload['date'] : $resolvedDate;

            foreach ($payload['schedules'] ?? [] as $schedule) {
                if (is_array($schedule)) {
                    $schedules[] = $schedule;
                }
            }

            $pagination = $response->json('meta.pagination') ?? [];
            $lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= 100);

        return [
            'schedules' => $schedules,
            'campus' => $campus,
            'date' => $resolvedDate,
        ];
    }

    /**
     * Uploaded faculty loading PDFs stored in Skolaris (Pulse workspace uploads).
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listUploadedFacultyLoads(
        int $page = 1,
        int $perPage = 100,
        ?string $search = null,
        ?string $parseStatus = null,
    ): array {
        $params = [
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ];

        if ($search !== null && trim($search) !== '') {
            $params['search'] = trim($search);
        }

        if ($parseStatus !== null && $parseStatus !== '' && $parseStatus !== 'all') {
            $params['parse_status'] = $parseStatus;
        }

        $response = $this->request('get', '/pulse-uploaded-faculty-loading', $params);

        return [
            'data' => array_values($response->json('data') ?? []),
            'meta' => is_array($response->json('meta')) ? $response->json('meta') : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFacultyLoad(int $uploadId): array
    {
        $response = $this->request('get', '/pulse-uploaded-faculty-loading/'.$uploadId);
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Uploaded faculty load #'.$uploadId.' was not found in Skolaris.');
        }

        return $data;
    }

    public function downloadUploadedFacultyLoadBinary(int $uploadId): string
    {
        $this->assertConfigured();

        $response = $this->client()
            ->withToken($this->accessToken())
            ->get('/pulse-uploaded-faculty-loading/'.$uploadId.'/download');

        if ($response->status() === 401) {
            Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
            $response = $this->client()
                ->withToken($this->accessToken(true))
                ->get('/pulse-uploaded-faculty-loading/'.$uploadId.'/download');
        }

        if ($response->failed()) {
            $this->throwForResponse('/pulse-uploaded-faculty-loading/'.$uploadId.'/download', $response, 'Failed to download faculty loading PDF from Skolaris.');
        }

        return $response->body();
    }

    /**
     * Pending field patches from GET /pulse-api/v1/local-employee-updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listLocalEmployeeUpdates(string $status = 'pending', int $limit = 500, ?string $employeeId = null): array
    {
        $params = [
            'status' => $status,
            'limit' => min(max(1, $limit), 500),
        ];

        if ($employeeId !== null && $employeeId !== '') {
            $params['employee_id'] = $employeeId;
        }

        $response = $this->pulseApiRequest('get', '/local-employee-updates', $params, timeoutSeconds: 60);
        $data = $response->json('data') ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @param  array<int, int|string>  $updateIds
     */
    public function markLocalEmployeeUpdatesApplied(array $updateIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $updateIds), fn (int $id) => $id > 0)));

        if ($ids === []) {
            return;
        }

        $this->pulseApiRequest('post', '/local-employee-updates/mark-applied', [
            'update_ids' => $ids,
        ], timeoutSeconds: 60);
    }

    /**
     * Name/number card from GET /timekeeping/employees/{id}/attendance.
     *
     * @return array<string, mixed>
     */
    public function timekeepingEmployeeCard(int|string $skolarisEmployeeId): array
    {
        $id = (int) $skolarisEmployeeId;
        if ($id <= 0) {
            return [];
        }

        try {
            $response = $this->pulseApiRequest(
                'get',
                '/timekeeping/employees/'.$id.'/attendance',
                ['month' => now()->format('Y-m')],
                timeoutSeconds: 30,
            );
        } catch (Throwable) {
            return [];
        }

        $employee = $response->json('data.employee');

        return is_array($employee) ? $employee : [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{data: list<array<string, mixed>>, synced_at: string|null, pagination: array<string, mixed>|null}
     */
    public function pullEmployeeRequestsSync(array $params = []): array
    {
        $response = $this->pulseApiRequest('get', '/employee-requests/sync', $params);
        $payload = $response->json();

        return [
            'data' => is_array($payload['data'] ?? null) ? array_values($payload['data']) : [],
            'synced_at' => is_string($payload['synced_at'] ?? null) ? $payload['synced_at'] : null,
            'pagination' => is_array($payload['pagination'] ?? null) ? $payload['pagination'] : null,
        ];
    }

    /**
     * NTE written explanations submitted on Skolaris web against synced Company Document templates.
     *
     * @param  array<string, mixed>  $params  template_code (required), updated_since, page, per_page
     * @return array{data: list<array<string, mixed>>, synced_at: string|null, pagination: array<string, mixed>|null}
     */
    public function pullCompanyDocumentNteResponsesSync(array $params = []): array
    {
        $response = $this->pulseApiRequest('get', '/company-documents/nte-responses/sync', $params);
        $payload = $response->json();

        return [
            'data' => is_array($payload['data'] ?? null) ? array_values($payload['data']) : [],
            'synced_at' => is_string($payload['synced_at'] ?? null) ? $payload['synced_at'] : null,
            'pagination' => is_array($payload['pagination'] ?? null) ? $payload['pagination'] : null,
        ];
    }

    private function usesPulseApiKey(): bool
    {
        return filled(config('skolaris.pulse_api_key'));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function pulseApiRequest(string $method, string $uri, array $params = [], ?int $timeoutSeconds = null): Response
    {
        $apiKey = EncryptedEnv::reveal((string) config('skolaris.pulse_api_key'));
        $baseUrl = (string) config('skolaris.pulse_api_base_url');

        if ($apiKey === '' || $baseUrl === '') {
            throw new RuntimeException('Skolaris Pulse API key is not configured. Set SKOLARIS_PULSE_API_KEY and SKOLARIS_PULSE_API_BASE_URL.');
        }

        $client = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeoutSeconds ?? (int) config('skolaris.timeout', 60))
            ->withHeaders(['X-API-Key' => $apiKey]);

        if ($method !== 'get') {
            $client = $client->asJson();
        }

        $response = $method === 'get'
            ? $client->get($uri, $params)
            : $client->post($uri, $params);

        if ($response->failed()) {
            $this->throwForResponse($uri, $response, 'Skolaris Pulse API request failed.');
        }

        return $response;
    }

    /**
     * Perform an authenticated request, retrying once on a 401 after refreshing.
     *
     * @param  array<string, mixed>  $params
     */
    private function request(string $method, string $uri, array $params = [], ?int $timeoutSeconds = null): Response
    {
        $this->assertConfigured();

        $response = $this->send($method, $uri, $params, $this->accessToken(), $timeoutSeconds);

        if ($response->status() === 401) {
            Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
            $response = $this->send($method, $uri, $params, $this->accessToken(true), $timeoutSeconds);
        }

        if ($response->failed()) {
            $this->throwForResponse($uri, $response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(string $method, string $uri, array $params, string $token, ?int $timeoutSeconds = null): Response
    {
        $request = $this->client($timeoutSeconds)->withToken($token);

        return $method === 'get'
            ? $request->get($uri, $params)
            : $request->post($uri, $params);
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $refreshToken = Cache::get(self::REFRESH_TOKEN_CACHE_KEY)
            ?: config('skolaris.refresh_token');

        if (is_string($refreshToken) && $refreshToken !== '') {
            $token = $this->refresh($refreshToken);

            if ($token !== null) {
                return $token;
            }
        }

        return $this->login();
    }

    private function login(): string
    {
        $response = $this->client()->post('/login', SkolarisJwtCredentials::loginPayload());

        if ($response->failed()) {
            $this->throwForResponse('/login', $response, 'Unable to authenticate with Skolaris. Check the service-account credentials.');
        }

        return $this->storeTokens($response);
    }

    private function refresh(string $refreshToken): ?string
    {
        $response = $this->client()->post('/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            Cache::forget(self::REFRESH_TOKEN_CACHE_KEY);

            return null;
        }

        return $this->storeTokens($response);
    }

    private function storeTokens(Response $response): string
    {
        $accessToken = (string) $response->json('access_token');
        $refreshToken = $response->json('refresh_token');

        if ($accessToken === '') {
            throw new RuntimeException('Skolaris did not return an access token.');
        }

        Cache::put(
            self::ACCESS_TOKEN_CACHE_KEY,
            $accessToken,
            now()->addMinutes((int) config('skolaris.token_ttl_minutes', 55)),
        );

        if (is_string($refreshToken) && $refreshToken !== '') {
            Cache::put(self::REFRESH_TOKEN_CACHE_KEY, $refreshToken, now()->addDays(7));
        }

        return $accessToken;
    }

    private function client(?int $timeoutSeconds = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeoutSeconds ?? (int) config('skolaris.timeout', 30));
    }

    private function employeeLoadApiTimeout(): int
    {
        return max(30, (int) config('employee_load.skolaris_api_timeout_seconds', 120));
    }

    private function assertConfigured(): void
    {
        if (! SkolarisJwtCredentials::isConfigured()) {
            throw new RuntimeException(
                'Skolaris API credentials are not configured. Set SKOLARIS_API_IDENTIFIER (Skolaris login email or username) '
                .'and SKOLARIS_API_PASSWORD (same password as skolaris.icct.edu.ph /login). '
                .'Use a global admin service account — not the People360 Pulse API key (skp_…).'
            );
        }
    }

    private function throwForResponse(string $uri, Response $response, ?string $friendly = null): void
    {
        $message = $response->json('message') ?: $response->reason();

        Log::warning('Skolaris API request failed', [
            'uri' => $uri,
            'status' => $response->status(),
            'message' => $message,
        ]);

        throw new RuntimeException(
            $friendly ?? 'Skolaris API request failed ('.$response->status().'): '.$message,
        );
    }
}
