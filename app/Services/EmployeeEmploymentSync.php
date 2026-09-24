<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\RawTimekeepingInandout;
use Carbon\Carbon;

class EmployeeEmploymentSync
{
    public static function sync(Employee $employee, array $records): void
    {
        $records = array_values($records);
        $existing = $employee->employmentInformations()
            ->orderBy('sort_order')
            ->orderBy('employment_info_id')
            ->get();
        $lastLogDate = self::lastLogDate($employee->employee_id);

        foreach ($records as $index => $record) {
            $employment = $existing->get($index);
            $payload = self::payload($record, $employment, $index);
            $payload['separation_date'] = self::separationDate(
                $payload['last_payroll_date'],
                $lastLogDate,
            );

            if ($employment) {
                if (self::shouldArchive($employment, $payload)) {
                    self::archive($employment, $payload['date_effective_from']);
                }

                $employment->update($payload);
            } else {
                $employee->employmentInformations()->create($payload);
            }
        }

        if ($existing->count() > count($records)) {
            $existing
                ->slice(count($records))
                ->each(function (EmployeeEmploymentInformation $info) {
                    $info->previousEmployments()->delete();
                    $info->forceDelete();
                });
        }

        $employee->unsetRelation('employmentInformations');
    }

    /**
     * Separation date is the last time-log date, and never later than Last Payroll Date.
     * It is stored only when Last Payroll Date is set.
     */
    public static function separationDate(?string $lastPayrollDate, ?string $lastLogDate): ?string
    {
        if (blank($lastPayrollDate)) {
            return null;
        }

        if (blank($lastLogDate)) {
            return null;
        }

        $payroll = Carbon::parse($lastPayrollDate)->toDateString();
        $log = Carbon::parse($lastLogDate)->toDateString();

        return $log < $payroll ? $log : $payroll;
    }

    public static function lastLogDate(int $employeeId): ?string
    {
        $latest = RawTimekeepingInandout::query()
            ->where('employee_id', $employeeId)
            ->max('dt_datetime');

        if (blank($latest)) {
            return null;
        }

        return Carbon::parse($latest)->toDateString();
    }

    public static function normalizeRecords(array $records, bool $isHybrid): array
    {
        $records = array_values(array_filter($records, fn ($record) => is_array($record)));

        if ($isHybrid) {
            usort($records, function (array $left, array $right) {
                $order = [
                    EmployeeEmploymentInformation::TYPE_FACULTY => 0,
                    EmployeeEmploymentInformation::TYPE_STAFF => 1,
                ];

                return ($order[$left['user_type'] ?? ''] ?? 99) <=> ($order[$right['user_type'] ?? ''] ?? 99);
            });

            return [
                array_merge($records[0] ?? [], ['user_type' => EmployeeEmploymentInformation::TYPE_FACULTY]),
                array_merge($records[1] ?? [], ['user_type' => EmployeeEmploymentInformation::TYPE_STAFF]),
            ];
        }

        return [array_merge($records[0] ?? [], [
            'user_type' => $records[0]['user_type'] ?? EmployeeEmploymentInformation::TYPE_STAFF,
        ])];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(array $record, ?EmployeeEmploymentInformation $existing, int $index): array
    {
        $effectiveFrom = array_key_exists('date_effective_from', $record) && filled($record['date_effective_from'])
            ? self::normalizeDate($record['date_effective_from'])
            : ($existing?->date_effective_from?->toDateString() ?? now()->toDateString());

        $lastPayroll = array_key_exists('last_payroll_date', $record)
            ? (filled($record['last_payroll_date'] ?? null) ? self::normalizeDate($record['last_payroll_date']) : null)
            : $existing?->last_payroll_date?->toDateString();

        return [
            'user_type' => $record['user_type'],
            'position' => filled($record['position'] ?? null) ? $record['position'] : null,
            'designation' => filled($record['designation'] ?? null) ? $record['designation'] : null,
            'rank' => filled($record['rank'] ?? null) ? $record['rank'] : null,
            'employment_type' => filled($record['employment_type'] ?? null) ? $record['employment_type'] : null,
            'hire_date' => filled($record['hire_date'] ?? null) ? self::normalizeDate($record['hire_date']) : null,
            'date_effective_from' => $effectiveFrom,
            'date_effective_to' => null,
            'last_payroll_date' => $lastPayroll,
            'lineage_employment_info_id' => null,
            'sort_order' => $index,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function shouldArchive(EmployeeEmploymentInformation $current, array $payload): bool
    {
        return self::comparable($current) !== self::comparablePayload($payload);
    }

    private static function archive(EmployeeEmploymentInformation $current, string $newEffectiveFrom): void
    {
        $currentFrom = $current->date_effective_from?->toDateString();
        $closeDate = Carbon::parse($newEffectiveFrom)->subDay()->toDateString();

        if ($currentFrom !== null && $currentFrom === $newEffectiveFrom) {
            $closeDate = $currentFrom;
        }

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $current->employee_id,
            'user_type' => $current->user_type,
            'position' => $current->position,
            'designation' => $current->designation,
            'rank' => $current->rank,
            'employment_type' => $current->employment_type,
            'hire_date' => $current->hire_date?->toDateString(),
            'date_effective_from' => $currentFrom ?? $closeDate,
            'date_effective_to' => $closeDate,
            'last_payroll_date' => $current->last_payroll_date?->toDateString(),
            'separation_date' => $current->separation_date?->toDateString(),
            'lineage_employment_info_id' => $current->employment_info_id,
            'sort_order' => $current->sort_order,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function comparable(EmployeeEmploymentInformation $current): array
    {
        return [
            'user_type' => (string) $current->user_type,
            'position' => (string) ($current->position ?? ''),
            'designation' => (string) ($current->designation ?? ''),
            'rank' => (string) ($current->rank ?? ''),
            'employment_type' => (string) ($current->employment_type ?? ''),
            'hire_date' => $current->hire_date?->toDateString() ?? '',
            'date_effective_from' => $current->date_effective_from?->toDateString() ?? '',
            'last_payroll_date' => $current->last_payroll_date?->toDateString() ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function comparablePayload(array $payload): array
    {
        return [
            'user_type' => (string) ($payload['user_type'] ?? ''),
            'position' => (string) ($payload['position'] ?? ''),
            'designation' => (string) ($payload['designation'] ?? ''),
            'rank' => (string) ($payload['rank'] ?? ''),
            'employment_type' => (string) ($payload['employment_type'] ?? ''),
            'hire_date' => (string) ($payload['hire_date'] ?? ''),
            'date_effective_from' => (string) ($payload['date_effective_from'] ?? ''),
            'last_payroll_date' => (string) ($payload['last_payroll_date'] ?? ''),
        ];
    }

    private static function normalizeDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->toDateString();
    }
}
