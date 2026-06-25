<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;

class EmployeeEmploymentSync
{
    public static function sync(Employee $employee, array $records): void
    {
        $records = array_values($records);
        $existing = $employee->employmentInformations()
            ->orderBy('sort_order')
            ->orderBy('employment_info_id')
            ->get();

        foreach ($records as $index => $record) {
            $payload = [
                'user_type' => $record['user_type'],
                'position' => $record['position'] ?? null,
                'designation' => $record['designation'] ?? null,
                'rank' => $record['rank'] ?? null,
                'employment_type' => $record['employment_type'] ?? null,
                'hire_date' => $record['hire_date'] ?? null,
                'sort_order' => $index,
            ];

            $employment = $existing->get($index);

            if ($employment) {
                $employment->update($payload);
            } else {
                $employee->employmentInformations()->create($payload);
            }
        }

        if ($existing->count() > count($records)) {
            $existing
                ->slice(count($records))
                ->each(fn (EmployeeEmploymentInformation $info) => $info->forceDelete());
        }

        $employee->unsetRelation('employmentInformations');
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
}
