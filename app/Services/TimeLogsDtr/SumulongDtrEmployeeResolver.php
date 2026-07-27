<?php

namespace App\Services\TimeLogsDtr;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;

class SumulongDtrEmployeeResolver
{
    public function resolve(int $campusId, string $identifier, ?string $employeeName = null): ?Employee
    {
        $identifier = trim($identifier);

        if ($campusId <= 0 || $identifier === '') {
            return null;
        }

        $employee = $this->resolveByEmployeeNumber($identifier);

        if ($employee !== null) {
            return $employee;
        }

        $employee = $this->resolveByBiometricId($campusId, $identifier);

        if ($employee !== null) {
            return $employee;
        }

        if (filled($employeeName)) {
            return $this->resolveByName($campusId, $employeeName);
        }

        return null;
    }

    private function resolveByEmployeeNumber(string $identifier): ?Employee
    {
        $employee = Employee::query()
            ->where('employee_number', $identifier)
            ->first();

        if ($employee !== null) {
            return $employee;
        }

        $padded = str_pad($identifier, 5, '0', STR_PAD_LEFT);

        return Employee::query()
            ->where(function ($query) use ($identifier, $padded) {
                $query->where('employee_number', 'like', '%-'.$padded)
                    ->orWhere('employee_number', 'like', '%-'.$identifier);
            })
            ->first();
    }

    private function resolveByBiometricId(int $campusId, string $identifier): ?Employee
    {
        $assignment = EmployeeCampusAssignment::query()
            ->where('campus_id', $campusId)
            ->where('biometric_id', $identifier)
            ->first();

        if ($assignment === null) {
            return null;
        }

        return Employee::query()->find($assignment->employee_id);
    }

    private function resolveByName(int $campusId, string $employeeName): ?Employee
    {
        $employeeName = trim(preg_replace('/\s+/', ' ', $employeeName) ?? $employeeName);

        if ($employeeName === '') {
            return null;
        }

        [$lastName, $firstName] = $this->splitEmployeeName($employeeName);

        if ($lastName === '') {
            return null;
        }

        $query = Employee::query()->where('campus_id', $campusId);

        if ($firstName !== '') {
            $query->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
                ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)]);
        } else {
            $query->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)]);
        }

        $matches = $query->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitEmployeeName(string $employeeName): array
    {
        if (str_contains($employeeName, ',')) {
            [$lastName, $firstName] = array_map('trim', explode(',', $employeeName, 2));

            return [$lastName, $firstName];
        }

        $parts = preg_split('/\s+/', $employeeName) ?: [];
        $lastName = array_pop($parts) ?? '';
        $firstName = trim(implode(' ', $parts));

        return [$lastName, $firstName];
    }

    public function campusName(int $campusId): string
    {
        return (string) (Campus::query()->where('campus_id', $campusId)->value('campus_name') ?? 'selected campus');
    }
}
