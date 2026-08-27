<?php

namespace App\Services;

use App\Support\EncryptedEnv;
use Illuminate\Http\Client\PendingRequest;
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
        ]);

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

        foreach (array_chunk($offeringIds, 200) as $chunk) {
            $response = $this->request('post', '/course-offerings/batch-details', [
                'offering_ids' => $chunk,
            ]);

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

        if ($this->usesPulseApiKey()) {
            $response = $this->pulseApiRequest('get', '/timekeeping/daily-loads', $params);
        } else {
            $response = $this->request('get', '/employees/timekeeping/daily-loads', $params);
        }

        $rows = $response->json('data') ?? [];

        return is_array($rows) ? array_values($rows) : [];
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
    private function request(string $method, string $uri, array $params = []): Response
    {
        $this->assertConfigured();

        $response = $this->send($method, $uri, $params, $this->accessToken());

        if ($response->status() === 401) {
            Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
            $response = $this->send($method, $uri, $params, $this->accessToken(true));
        }

        if ($response->failed()) {
            $this->throwForResponse($uri, $response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(string $method, string $uri, array $params, string $token): Response
    {
        $request = $this->client()->withToken($token);

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
        $response = $this->client()->post('/login', [
            'identifier' => config('skolaris.identifier'),
            'password' => config('skolaris.password'),
        ]);

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

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('skolaris.timeout', 30));
    }

    private function assertConfigured(): void
    {
        if (blank(config('skolaris.identifier')) || blank(config('skolaris.password'))) {
            throw new RuntimeException('Skolaris API credentials are not configured. Set SKOLARIS_API_IDENTIFIER and SKOLARIS_API_PASSWORD.');
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
