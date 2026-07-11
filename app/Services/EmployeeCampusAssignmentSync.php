<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;

class EmployeeCampusAssignmentSync
{
    public static function sync(Employee $employee, array $records): void
    {
        $records = self::normalizeRecords($records);
        $existing = $employee->campusAssignments()
            ->orderBy('sort_order')
            ->orderBy('employee_campus_assignment_id')
            ->get();

        foreach ($records as $index => $record) {
            $payload = [
                'campus_id' => (int) $record['campus_id'],
                'biometric_id' => filled($record['biometric_id'] ?? null)
                    ? trim((string) $record['biometric_id'])
                    : null,
                'college' => $record['college'] ?? null,
                'department' => $record['department'] ?? null,
                'program' => $record['program'] ?? null,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ];

            $assignment = $existing->get($index);

            if ($assignment) {
                $assignment->update($payload);
            } else {
                $employee->campusAssignments()->create($payload);
            }
        }

        if ($existing->count() > count($records)) {
            $existing
                ->slice(count($records))
                ->each(fn (EmployeeCampusAssignment $assignment) => $assignment->forceDelete());
        }

        $employee->unsetRelation('campusAssignments');
        self::syncPrimaryToEmployee($employee->fresh(['campusAssignments']));
    }

    public static function normalizeRecords(array $records): array
    {
        return array_values(array_filter(
            array_map(function ($record) {
                if (! is_array($record)) {
                    return null;
                }

                $campusId = (int) ($record['campus_id'] ?? 0);

                if ($campusId <= 0) {
                    return null;
                }

                return [
                    'campus_id' => $campusId,
                    'biometric_id' => $record['biometric_id'] ?? null,
                    'college' => $record['college'] ?? null,
                    'department' => $record['department'] ?? null,
                    'program' => $record['program'] ?? null,
                ];
            }, $records),
            fn ($record) => $record !== null,
        ));
    }

    public static function syncPrimaryToEmployee(Employee $employee): void
    {
        $primary = $employee->campusAssignments
            ->sortBy('sort_order')
            ->first();

        if ($primary === null) {
            return;
        }

        $campus = Campus::query()->find($primary->campus_id);

        $employee->update([
            'campus_id' => $primary->campus_id,
            'campus' => $campus?->campus_code,
            'college' => $primary->college,
            'department' => $primary->department,
            'program' => $primary->program,
        ]);
    }
}
