<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;

class EmployeeCampusAssignmentSync
{
    public static function sync(Employee $employee, array $records): void
    {
        $records = self::ensureSinglePrimary(self::normalizeRecords($records));
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
                'is_primary' => (bool) ($record['is_primary'] ?? false),
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
                    'is_primary' => self::isTruthy($record['is_primary'] ?? false),
                ];
            }, $records),
            fn ($record) => $record !== null,
        ));
    }

    public static function setMainCampus(Employee $employee, int $campusId): void
    {
        $assignments = $employee->campusAssignments()
            ->orderBy('sort_order')
            ->orderBy('employee_campus_assignment_id')
            ->get();

        $match = $assignments->first(
            fn (EmployeeCampusAssignment $assignment) => (int) $assignment->campus_id === $campusId
        );

        if ($match === null) {
            throw new \InvalidArgumentException('Campus is not assigned to this employee.');
        }

        foreach ($assignments as $assignment) {
            $isPrimary = (int) $assignment->employee_campus_assignment_id === (int) $match->employee_campus_assignment_id;

            if ((bool) $assignment->is_primary !== $isPrimary) {
                $assignment->is_primary = $isPrimary;
                $assignment->save();
            }
        }

        $employee->unsetRelation('campusAssignments');
        self::syncPrimaryToEmployee($employee->fresh(['campusAssignments']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function ensureSinglePrimary(array $records): array
    {
        if ($records === []) {
            return $records;
        }

        $primaryIndex = null;

        foreach ($records as $index => $record) {
            if (self::isTruthy($record['is_primary'] ?? false)) {
                $primaryIndex = $index;
                break;
            }
        }

        if ($primaryIndex === null) {
            $primaryIndex = 0;
        }

        foreach ($records as $index => $record) {
            $records[$index]['is_primary'] = $index === $primaryIndex;
        }

        return $records;
    }

    public static function syncPrimaryToEmployee(Employee $employee): void
    {
        $assignments = $employee->campusAssignments->sortBy('sort_order')->values();
        $primary = $assignments->first(fn (EmployeeCampusAssignment $assignment) => (bool) $assignment->is_primary)
            ?? $assignments->first();

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

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'on'], true);
    }
}
