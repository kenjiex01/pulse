<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;

class EmployeeBiometricResolver
{
    public function resolve(int $campusId, string $biometricId): ?Employee
    {
        $biometricId = trim($biometricId);

        if ($campusId <= 0 || $biometricId === '') {
            return null;
        }

        $assignment = EmployeeCampusAssignment::query()
            ->where('campus_id', $campusId)
            ->where('biometric_id', $biometricId)
            ->first();

        if ($assignment === null) {
            return null;
        }

        return Employee::query()->find($assignment->employee_id);
    }

    public function campusName(int $campusId): string
    {
        return (string) (Campus::query()->where('campus_id', $campusId)->value('campus_name') ?? 'selected campus');
    }
}
