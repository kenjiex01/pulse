<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
