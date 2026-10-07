<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\EmployeeNumberMatch;
use App\Support\EncryptedEnv;
use App\Support\PhpExecutionTime;
use App\Support\SkolarisJwtCredentials;
use Carbon\CarbonImmutable;
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
    /**
     * @param  array<int, string>|null  $employeeNumbers
     */
    public function attendanceCheckerCampuses(?string $date = null, ?array $employeeNumbers = null): array
    {
        $params = [];

        if ($date !== null && $date !== '') {
            $params['date'] = $date;
        }

        if ($employeeNumbers !== null && $employeeNumbers !== []) {
            $params['employee_numbers'] = implode(',', array_values(array_unique(array_map('strval', $employeeNumbers))));
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
     * Resolve the Skolaris HR employee row for a People360 faculty member (employee_id / employee_number).
     *
     * @return array<string, mixed>|null
     */
    public function resolveSkolarisEmployeeRecord(Employee $employee): ?array
    {
        $localNumber = trim((string) $employee->employee_number);
        $email = trim(strtolower((string) ($employee->email ?? '')));
        $cacheKey = 'skolaris:hr-employee:'.hash('sha256', $localNumber.'|'.$email);
        $cached = Cache::get($cacheKey);

        if ($cached === false) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $row = $this->fetchSkolarisEmployeeRecord($employee);
        Cache::put($cacheKey, $row ?? false, $row !== null ? now()->addHours(12) : now()->addMinutes(20));

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSkolarisEmployeeRecord(Employee $employee): ?array
    {
        $localNumber = trim((string) $employee->employee_number);
        $email = trim(strtolower((string) ($employee->email ?? '')));
        $fullName = trim((string) $employee->full_name);

        $searches = array_values(array_unique(array_filter([
            $localNumber,
            $email,
            $fullName !== '' ? strtok($fullName, ' ') : '',
        ])));

        foreach ($searches as $term) {
            try {
                $response = $this->request('get', '/employees', [
                    'search' => $term,
                    'per_page' => 50,
                    'page' => 1,
                ]);
            } catch (Throwable) {
                continue;
            }

            $rows = $response->json('data.data') ?? $response->json('data') ?? [];

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if ($localNumber !== '' && EmployeeNumberMatch::same($row['employee_number'] ?? null, $localNumber)) {
                    return $row;
                }

                $rowEmail = strtolower(trim((string) ($row['user']['email'] ?? $row['email'] ?? '')));

                if ($email !== '' && $rowEmail !== '' && $rowEmail === $email) {
                    return $row;
                }
            }
        }

        foreach ($this->employeeSearchTermsFromFacultyLabel($fullName) as $term) {
            try {
                $response = $this->request('get', '/employees', [
                    'search' => $term,
                    'per_page' => 30,
                    'page' => 1,
                ]);
            } catch (Throwable) {
                continue;
            }

            $rows = $response->json('data.data') ?? $response->json('data') ?? [];

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowEmail = strtolower(trim((string) ($row['user']['email'] ?? $row['email'] ?? '')));

                if ($email !== '' && $rowEmail !== '' && $rowEmail === $email) {
                    return $row;
                }
            }

            if (count($rows) === 1 && is_array($rows[0])) {
                return $rows[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, string> section code => faculty employee_number
     */
    public function mapSectionCodesToFacultyEmployeeNumbers(array $sectionCodes): array
    {
        $sectionCodes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $sectionCodes,
        ))));

        if ($sectionCodes === []) {
            return [];
        }

        $map = [];

        foreach (array_chunk($sectionCodes, 40) as $chunk) {
            try {
                $response = $this->request('get', '/course-offerings', [
                    'sections' => $chunk,
                    'per_page' => 500,
                    'page' => 1,
                ], $this->employeeLoadApiTimeout());
            } catch (Throwable $exception) {
                Log::warning('Course offering section lookup skipped', [
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            $rows = $response->json('data') ?? [];

            if (isset($rows['data']) && is_array($rows['data'])) {
                $rows = $rows['data'];
            }

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $section = strtoupper(trim((string) ($row['section'] ?? '')));
                $facultyNumber = trim((string) ($row['faculty']['employee_number'] ?? ''));

                if ($section !== '' && $facultyNumber !== '') {
                    $map[$section] = $facultyNumber;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public function sectionCodesForFacultyId(int $facultyId): array
    {
        if ($facultyId <= 0) {
            return [];
        }

        try {
            $response = $this->request('get', '/course-offerings', [
                'faculty_id' => $facultyId,
                'per_page' => 500,
                'page' => 1,
            ], $this->employeeLoadApiTimeout());
        } catch (Throwable $exception) {
            Log::warning('Course offerings by faculty_id skipped', [
                'faculty_id' => $facultyId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $rows = $response->json('data') ?? [];

        if (isset($rows['data']) && is_array($rows['data'])) {
            $rows = $rows['data'];
        }

        if (! is_array($rows)) {
            return [];
        }

        $sections = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $section = strtoupper(trim((string) ($row['section'] ?? '')));

            if ($section !== '') {
                $sections[] = $section;
            }
        }

        return array_values(array_unique($sections));
    }

    /**
     * Subject rows from Skolaris Uploaded Faculty Loading for one employee.
     * Matches Skolaris employee_id / employee_number (not PDF faculty names).
     *
     * @return array<int, array{upload: array<string, mixed>, item: array<string, mixed>}>
     */
    public function uploadedFacultyLoadSubjectItemsForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
    ): array {
        PhpExecutionTime::ensureAtLeast(max(30, (int) config('employee_load.pull_step_time_limit_seconds', 900)));

        $localNumber = trim((string) $employee->employee_number);

        if ($localNumber === '') {
            return [];
        }

        $skolarisEmployee = $this->resolveSkolarisEmployeeRecord($employee);
        $skolarisEmployeeId = (int) ($skolarisEmployee['employee_id'] ?? 0);
        $skolarisNumber = trim((string) ($skolarisEmployee['employee_number'] ?? ''));

        if ($skolarisEmployeeId <= 0) {
            Log::warning('Uploaded faculty loading pull skipped — no Skolaris employee_id for People360 employee', [
                'people360_employee_id' => $employee->employee_id,
                'employee_number' => $localNumber,
            ]);

            return [];
        }

        $matches = [];

        foreach ($this->cachedUploadedFacultyLoadSummaries(parseStatus: 'parsed') as $summary) {
            if (! is_array($summary)) {
                continue;
            }
                $uploadId = (int) ($summary['upload_id'] ?? 0);

                if ($uploadId <= 0 || ! $this->uploadedFacultyLoadSummaryOverlapsRange($summary, $dateFrom, $dateTo)) {
                    continue;
                }

                $summaryNumber = trim((string) ($summary['employee_number'] ?? ''));

                if ($summaryNumber !== ''
                    && ! EmployeeNumberMatch::same($summaryNumber, $localNumber)
                    && ! EmployeeNumberMatch::same($summaryNumber, $skolarisNumber)) {
                    continue;
                }

                try {
                    $upload = $this->getUploadedFacultyLoad($uploadId);
                } catch (Throwable $exception) {
                    Log::warning('Uploaded faculty loading detail skipped', [
                        'upload_id' => $uploadId,
                        'employee_number' => $localNumber,
                        'message' => $exception->getMessage(),
                    ]);

                    continue;
                }

                $uploadNumber = trim((string) ($upload['employee_number'] ?? ''));

                if ($uploadNumber !== ''
                    && ! EmployeeNumberMatch::same($uploadNumber, $localNumber)
                    && ! EmployeeNumberMatch::same($uploadNumber, $skolarisNumber)) {
                    continue;
                }

                $items = is_array($upload['items'] ?? null) ? $upload['items'] : [];
                $employeeIdByFacultyLabel = $this->skolarisEmployeeIdsByUploadedFacultyLabels($items, $skolarisEmployeeId);

                foreach ($items as $item) {
                    if (! is_array($item) || ($item['row_type'] ?? '') !== 'subject') {
                        continue;
                    }

                    if ($this->uploadedFacultyItemMatchesEmployee(
                        $item,
                        $localNumber,
                        $skolarisNumber,
                        $skolarisEmployeeId,
                        $employeeIdByFacultyLabel,
                    )) {
                        $matches[] = [
                            'upload' => $upload,
                            'item' => $item,
                        ];
                    }
                }
        }

        return $matches;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cachedUploadedFacultyLoadSummaries(?string $parseStatus = 'parsed'): array
    {
        static $cache = [];

        $cacheKey = $parseStatus ?? 'all';

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $summaries = [];
        $page = 1;
        $lastPage = 1;

        do {
            $payload = $this->listUploadedFacultyLoads(page: $page, perPage: 100, parseStatus: $parseStatus);
            $lastPage = max(1, (int) ($payload['meta']['last_page'] ?? 1));

            foreach ($payload['data'] as $summary) {
                if (is_array($summary)) {
                    $summaries[] = $summary;
                }
            }

            $page++;
        } while ($page <= $lastPage && $page <= 100);

        return $cache[$cacheKey] = $summaries;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $employeeIdByFacultyLabel
     */
    private function uploadedFacultyItemMatchesEmployee(
        array $item,
        string $localEmployeeNumber,
        string $skolarisEmployeeNumber,
        int $skolarisEmployeeId,
        array $employeeIdByFacultyLabel,
    ): bool {
        $itemNumber = trim((string) ($item['employee_number'] ?? ''));

        if ($itemNumber !== '') {
            return EmployeeNumberMatch::same($itemNumber, $localEmployeeNumber)
                || EmployeeNumberMatch::same($itemNumber, $skolarisEmployeeNumber);
        }

        $itemEmployeeId = (int) ($item['employee_id'] ?? 0);

        $label = trim((string) ($item['faculty_name'] ?? ''));

        if ($itemEmployeeId <= 0 && $label !== '') {
            $itemEmployeeId = (int) ($employeeIdByFacultyLabel[$label] ?? 0);
        }

        if ($itemEmployeeId <= 0) {
            return false;
        }

        return $itemEmployeeId === $skolarisEmployeeId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int> faculty_name => employees.employee_id
     */
    private function skolarisEmployeeIdsByUploadedFacultyLabels(array $items, int $preferSkolarisEmployeeId): array
    {
        $labels = [];

        foreach ($items as $item) {
            if (! is_array($item) || ($item['row_type'] ?? '') !== 'subject') {
                continue;
            }

            if ((int) ($item['employee_id'] ?? 0) > 0) {
                continue;
            }

            $label = trim((string) ($item['faculty_name'] ?? ''));

            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        $map = [];

        foreach (array_keys($labels) as $label) {
            $resolvedId = $this->resolveSkolarisEmployeeIdForFacultyLabel($label, $preferSkolarisEmployeeId);

            if ($resolvedId > 0 && $resolvedId === $preferSkolarisEmployeeId) {
                $map[$label] = $resolvedId;
            }
        }

        return $map;
    }

    /**
     * Map a parsed PDF faculty label to Skolaris employees.employee_id (HR directory).
     */
    private function resolveSkolarisEmployeeIdForFacultyLabel(string $facultyName, int $preferSkolarisEmployeeId = 0): int
    {
        static $cache = [];

        if ($facultyName === '') {
            return 0;
        }

        $cacheKey = $facultyName.'|'.$preferSkolarisEmployeeId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        foreach ($this->employeeSearchTermsFromFacultyLabel($facultyName) as $term) {
            try {
                $response = $this->request('get', '/employees', [
                    'search' => $term,
                    'per_page' => 30,
                    'page' => 1,
                ]);
            } catch (Throwable) {
                continue;
            }

            $rows = $response->json('data.data') ?? $response->json('data') ?? [];

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            if ($preferSkolarisEmployeeId > 0) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    if ((int) ($row['employee_id'] ?? 0) === $preferSkolarisEmployeeId) {
                        return $cache[$cacheKey] = $preferSkolarisEmployeeId;
                    }
                }
            }

            if (count($rows) === 1 && is_array($rows[0])) {
                return $cache[$cacheKey] = (int) ($rows[0]['employee_id'] ?? 0);
            }
        }

        return $cache[$cacheKey] = 0;
    }

    /**
     * @return array<int, string>
     */
    private function employeeSearchTermsFromFacultyLabel(string $facultyName): array
    {
        $normalized = preg_replace('/[^A-Za-z\s]/', ' ', $facultyName) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];
        $terms = [];

        foreach ($parts as $part) {
            if (strlen($part) >= 4) {
                $terms[] = $part;
            }
        }

        if ($parts !== []) {
            $terms[] = end($parts);
        }

        return array_values(array_unique(array_filter($terms)));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function uploadedFacultyLoadSummaryOverlapsRange(array $summary, string $dateFrom, string $dateTo): bool
    {
        $rangeStart = trim((string) ($summary['period_start'] ?? ''));
        $rangeEnd = trim((string) ($summary['period_end'] ?? ''));

        if ($rangeStart === '' || $rangeEnd === '') {
            return true;
        }

        try {
            $start = CarbonImmutable::parse($rangeStart);
            $end = CarbonImmutable::parse($rangeEnd);
            $from = CarbonImmutable::parse($dateFrom);
            $to = CarbonImmutable::parse($dateTo);
        } catch (Throwable) {
            return true;
        }

        return $start->lte($to) && $end->gte($from);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFacultyLoad(int $uploadId): array
    {
        static $cache = [];

        if (isset($cache[$uploadId])) {
            return $cache[$uploadId];
        }

        $response = $this->request('get', '/pulse-uploaded-faculty-loading/'.$uploadId);
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Uploaded faculty load #'.$uploadId.' was not found in Skolaris.');
        }

        return $cache[$uploadId] = $data;
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
     * Loading Attendance for one Skolaris HR employee — match path by employees.employee_id.
     *
     * @return array{logs: array<int, array<string, mixed>>, source: string|null}
     */
    public function timekeepingEmployeeAttendance(
        int $skolarisEmployeeId,
        string $dateFrom,
        string $dateTo,
    ): array {
        if ($skolarisEmployeeId <= 0) {
            return ['logs' => [], 'source' => null];
        }

        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $timeout = $this->employeeLoadApiTimeout();

        try {
            if ($this->usesPulseApiKey()) {
                $response = $this->pulseApiRequest(
                    'get',
                    '/timekeeping/employees/'.$skolarisEmployeeId.'/attendance',
                    $params,
                    $timeout,
                );
            } else {
                $response = $this->request(
                    'get',
                    '/employees/'.$skolarisEmployeeId.'/timekeeping-attendance',
                    $params,
                    $timeout,
                );
            }
        } catch (Throwable $exception) {
            Log::warning('Skolaris timekeeping attendance skipped during teaching load pull', [
                'skolaris_employee_id' => $skolarisEmployeeId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'message' => $exception->getMessage(),
            ]);

            return ['logs' => [], 'source' => null];
        }

        $logs = $response->json('data.logs') ?? [];
        $source = $response->json('data.source');

        return [
            'logs' => is_array($logs) ? array_values(array_filter($logs, 'is_array')) : [],
            'source' => is_string($source) ? $source : null,
        ];
    }

    /**
     * Parallel Employee Attendance for many Skolaris employee ids.
     *
     * @param  array<int, int>  $skolarisEmployeeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function timekeepingEmployeeAttendanceMany(array $skolarisEmployeeIds, string $dateFrom, string $dateTo): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $skolarisEmployeeIds), fn (int $id) => $id > 0)));
        $out = [];

        foreach ($ids as $id) {
            $out[$id] = [];
        }

        if ($ids === [] || ! $this->usesPulseApiKey()) {
            foreach ($ids as $id) {
                $out[$id] = $this->timekeepingEmployeeAttendance($id, $dateFrom, $dateTo)['logs'];
            }

            return $out;
        }

        $apiKey = EncryptedEnv::reveal((string) config('skolaris.pulse_api_key'));
        $baseUrl = rtrim((string) config('skolaris.pulse_api_base_url'), '/');
        $timeout = $this->employeeLoadApiTimeout();

        $responses = Http::pool(function (Pool $pool) use ($ids, $dateFrom, $dateTo, $apiKey, $baseUrl, $timeout) {
            $requests = [];

            foreach ($ids as $id) {
                $requests[] = $pool->as((string) $id)
                    ->baseUrl($baseUrl)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->withHeaders(['X-API-Key' => $apiKey])
                    ->get('/timekeeping/employees/'.$id.'/attendance', [
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ]);
            }

            return $requests;
        });

        foreach ($ids as $id) {
            $response = $responses[(string) $id] ?? null;

            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $logs = $response->json('data.logs') ?? [];
            $out[$id] = is_array($logs) ? array_values(array_filter($logs, 'is_array')) : [];
        }

        return $out;
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
