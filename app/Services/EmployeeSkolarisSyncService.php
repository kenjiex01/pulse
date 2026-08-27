<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class EmployeeSkolarisSyncService
{
    public const CACHE_KEY = 'employee_skolaris_sync:pending';

    private const CACHE_TTL_MINUTES = 10;

    /**
     * @var array<string, true>
     */
    private const APPLY_FIELDS = [
        'first_name' => true,
        'middle_name' => true,
        'last_name' => true,
        'suffix' => true,
        'email' => true,
        'phone' => true,
        'home_phone' => true,
        'work_phone' => true,
        'fax_number' => true,
        'program' => true,
        'department' => true,
        'college' => true,
        'campus' => true,
        'employment_status' => true,
        'birth_date' => true,
        'place_of_birth' => true,
        'gender' => true,
        'civil_status' => true,
        'nationality' => true,
        'religion' => true,
        'language_dialect' => true,
        'height_cm' => true,
        'weight_kg' => true,
        'tin_number' => true,
        'sss_number' => true,
        'philhealth_number' => true,
        'pagibig_number' => true,
        'gsis_number' => true,
        'tax_status' => true,
        'emergency_contact_name' => true,
        'emergency_contact_relationship' => true,
        'emergency_contact_phone' => true,
        'emergency_contact_email' => true,
        'emergency_contact_address' => true,
        'address_line' => true,
        'country' => true,
        'region' => true,
        'province' => true,
        'city_municipality' => true,
        'barangay' => true,
        'postal_code' => true,
    ];

    public function __construct(
        private readonly SkolarisApiService $skolarisApi,
        private readonly EmployeeHistoryService $historyService,
    ) {}

    /**
     * @return array{ok: bool, count: int, error: ?string, employees: array<int, array<string, mixed>>}
     */
    public function pendingSummaries(bool $refresh = false): array
    {
        try {
            $candidates = $this->pendingCandidates($refresh);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'count' => 0,
                'error' => $exception->getMessage(),
                'employees' => [],
            ];
        }

        return [
            'ok' => true,
            'count' => count($candidates),
            'error' => null,
            'employees' => array_map(fn (array $row) => [
                'employee_number' => $row['sync_key'],
                'pulse_employee_number' => $row['pulse_employee_number'],
                'name' => $row['name'],
                'kind' => $row['kind'],
                'can_sync' => $row['can_sync'],
                'change_count' => count($row['changes']),
            ], $candidates),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function preview(string $syncKey): ?array
    {
        $syncKey = trim($syncKey);

        foreach ($this->pendingCandidates() as $row) {
            if (strcasecmp((string) $row['sync_key'], $syncKey) === 0) {
                return [
                    'employee_number' => $row['sync_key'],
                    'pulse_employee_number' => $row['pulse_employee_number'],
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'can_sync' => $row['can_sync'],
                    'changes' => $row['changes'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $syncKeys
     * @return array{applied: int, created: int, updated: int, failed: array<int, array{employee_number: string, message: string}>}
     */
    public function apply(array $syncKeys): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            fn ($key) => trim((string) $key),
            $syncKeys,
        ), fn (string $key) => $key !== '')));

        $byKey = [];
        foreach ($this->pendingCandidates() as $row) {
            $byKey[strtolower((string) $row['sync_key'])] = $row;
        }

        $result = [
            'applied' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => [],
        ];

        foreach ($wanted as $key) {
            $row = $byKey[strtolower($key)] ?? null;

            if ($row === null) {
                $result['failed'][] = [
                    'employee_number' => $key,
                    'message' => 'This profile is no longer pending. Refresh the list and try again.',
                ];
                continue;
            }

            try {
                $this->applyCandidate($row);
                $result['applied']++;
                $result['updated']++;
            } catch (Throwable $exception) {
                $result['failed'][] = [
                    'employee_number' => $key,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->forgetPendingCache();

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingCandidates(bool $refresh = false): array
    {
        if ($refresh) {
            $this->forgetPendingCache();
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            return $this->buildPendingCandidates();
        });
    }

    public function forgetPendingCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPendingCandidates(): array
    {
        $updates = $this->skolarisApi->listLocalEmployeeUpdates('pending', 500);
        $grouped = [];

        foreach ($updates as $update) {
            if (! is_array($update)) {
                continue;
            }

            $skolarisId = trim((string) ($update['employee_id'] ?? ''));
            $field = trim((string) ($update['field_name'] ?? ''));
            $updateId = (int) ($update['update_id'] ?? 0);

            if ($skolarisId === '' || $field === '' || $updateId <= 0) {
                continue;
            }

            if (($update['status'] ?? 'pending') !== 'pending') {
                continue;
            }

            $grouped[$skolarisId][] = $update;
        }

        $pending = [];

        foreach ($grouped as $employeeId => $rows) {
            $skolarisCard = [];
            $pulse = $this->matchPulseEmployee((string) $employeeId);

            if ($pulse === null) {
                $skolarisCard = $this->skolarisApi->timekeepingEmployeeCard($employeeId);
                $skolarisNumber = trim((string) ($skolarisCard['employee_number'] ?? ''));
                if ($skolarisNumber !== '') {
                    $pulse = $this->matchPulseEmployee($skolarisNumber);
                }
            }

            $changes = $this->changesForGroup($rows, $pulse);

            if ($changes === []) {
                continue;
            }

            $attributes = $this->attributesFromUpdates($rows);

            $pending[] = [
                'sync_key' => (string) $employeeId,
                'pulse_employee_number' => $pulse?->employee_number,
                'name' => $this->candidateDisplayName($pulse, (string) $employeeId, $rows, $attributes, $skolarisCard),
                'kind' => $pulse ? 'changed' : 'unmatched',
                'can_sync' => $pulse !== null,
                'changes' => $changes,
                'update_ids' => array_map(fn (array $row) => (int) $row['update_id'], $rows),
                'attributes' => $attributes,
                'employee_id' => $pulse?->employee_id,
            ];
        }

        usort($pending, fn (array $left, array $right) => strcasecmp((string) $left['name'], (string) $right['name']));

        return $pending;
    }

    private function matchPulseEmployee(string $employeeId): ?Employee
    {
        $id = (int) $employeeId;

        if ($id > 0) {
            $byId = Employee::query()->find($id);
            if ($byId) {
                return $byId;
            }
        }

        return Employee::query()
            ->where('employee_number', $employeeId)
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $skolarisCard
     */
    private function candidateDisplayName(?Employee $pulse, string $employeeId, array $rows, array $attributes, array $skolarisCard = []): string
    {
        $pulseName = trim((string) ($pulse?->full_name ?? ''));
        if ($pulseName !== '') {
            return $pulseName;
        }

        $skolarisName = $this->nameFromSkolarisCard($skolarisCard);
        if ($skolarisName !== '') {
            return $skolarisName;
        }

        foreach ($rows as $row) {
            $nested = $row['employee'] ?? null;
            if (is_array($nested)) {
                $fromNested = $this->nameFromSkolarisCard($nested);
                if ($fromNested !== '') {
                    return $fromNested;
                }
            }

            foreach (['employee_name', 'full_name', 'name'] as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $composed = trim(implode(' ', array_filter([
            $this->normalizeValue($attributes['first_name'] ?? $pulse?->first_name),
            $this->normalizeValue($attributes['middle_name'] ?? $pulse?->middle_name),
            $this->normalizeValue($attributes['last_name'] ?? $pulse?->last_name),
            $this->normalizeValue($attributes['suffix'] ?? $pulse?->suffix),
        ], fn ($part) => $part !== null && $part !== '')));

        return $composed !== '' ? $composed : 'Employee ID '.$employeeId;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function nameFromSkolarisCard(array $card): string
    {
        foreach (['full_name', 'employee_name', 'name'] as $key) {
            $value = trim((string) ($card[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $personal = $card['personal_information'] ?? $card['personalInformation'] ?? $card['personal_info'] ?? [];
        if (! is_array($personal)) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $this->normalizeValue($personal['first_name'] ?? null),
            $this->normalizeValue($personal['middle_name'] ?? null),
            $this->normalizeValue($personal['last_name'] ?? null),
            $this->normalizeValue($personal['suffix'] ?? null),
        ], fn ($part) => $part !== null && $part !== '')));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed, update_id: int}>
     */
    private function changesForGroup(array $rows, ?Employee $pulse): array
    {
        $changes = [];

        foreach ($rows as $row) {
            $field = trim((string) ($row['field_name'] ?? ''));
            if ($field === '' || ! isset(self::APPLY_FIELDS[$field])) {
                continue;
            }

            $new = $this->normalizeValue($row['new_value'] ?? null);
            $queuedOld = $this->normalizeValue($row['previous_value'] ?? null);
            $current = $pulse ? $this->normalizeValue($pulse->getAttribute($field)) : null;

            if ($pulse && $this->valuesEqual($current, $new)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $this->fieldLabel($field),
                'old' => $pulse ? $current : $queuedOld,
                'new' => $new,
                'update_id' => (int) ($row['update_id'] ?? 0),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function attributesFromUpdates(array $rows): array
    {
        $attributes = [];

        foreach ($rows as $row) {
            $field = trim((string) ($row['field_name'] ?? ''));
            if (! isset(self::APPLY_FIELDS[$field])) {
                continue;
            }

            $value = $this->normalizeValue($row['new_value'] ?? null);
            if ($value === null) {
                continue;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function applyCandidate(array $candidate): void
    {
        if (! ($candidate['can_sync'] ?? false)) {
            throw new RuntimeException('No People360 employee with this employee ID.');
        }

        $attributes = $candidate['attributes'] ?? [];
        $updateIds = array_values(array_filter(array_map('intval', $candidate['update_ids'] ?? [])));

        if ($attributes === [] || $updateIds === []) {
            throw new RuntimeException('No applicable field updates for this employee.');
        }

        $employee = Employee::query()->find($candidate['employee_id'] ?? 0);
        if (! $employee) {
            throw new RuntimeException('People360 employee record was not found.');
        }

        DB::transaction(function () use ($employee, $attributes): void {
            $oldValues = $employee->logSnapshot();
            $employee->update($attributes);

            SysLogService::record(
                action: 'update',
                table: 'tbl_employees',
                recordId: $employee->employee_id,
                oldValues: $oldValues,
                newValues: $employee->fresh()->logSnapshot(),
                description: 'Synced pending ISKOLARIS field updates: '.$employee->employee_number,
            );
        });

        $this->skolarisApi->markLocalEmployeeUpdatesApplied($updateIds);
    }

    private function fieldLabel(string $field): string
    {
        $labels = $this->historyService->fieldLabels();

        return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return $this->normalizeValue($left) === $this->normalizeValue($right);
    }
}
