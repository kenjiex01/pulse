<?php

namespace App\Services;

class EmployeeWizardSession
{
    public const SESSION_KEY = 'employee_wizard';

    public static function all(): array
    {
        return session(self::SESSION_KEY, [
            'campus_id' => null,
            'data' => [],
        ]);
    }

    public static function campusId(): ?int
    {
        $campusId = self::all()['campus_id'] ?? null;

        return $campusId ? (int) $campusId : null;
    }

    public static function data(): array
    {
        return self::all()['data'] ?? [];
    }

    public static function putCampus(int $campusId): void
    {
        $wizard = self::all();
        $wizard['campus_id'] = $campusId;
        session([self::SESSION_KEY => $wizard]);
    }

    public static function mergeData(array $data): void
    {
        $wizard = self::all();
        $wizard['data'] = array_merge($wizard['data'] ?? [], $data);
        session([self::SESSION_KEY => $wizard]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
