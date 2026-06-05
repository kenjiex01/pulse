<?php

namespace App\Services;

use App\Models\SysLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SysLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public static function record(
        string $action,
        string $table,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?int $userId = null,
    ): void {
        try {
            SysLog::query()->create([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'old_values' => self::sanitize($oldValues),
                'new_values' => self::sanitize($newValues),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'description' => $description,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('sys_logs write failed', [
                'action' => $action,
                'table' => $table,
                'record_id' => $recordId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
