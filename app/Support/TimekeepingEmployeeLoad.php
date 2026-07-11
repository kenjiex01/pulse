<?php

namespace App\Support;

use App\Models\RawEmployeeLoadTransaction;
use App\Models\SubModule;
use App\Models\User;

class TimekeepingEmployeeLoad
{
    public const SUB_MODULE_ROUTE = 'timekeeping.employee-load.index';

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'timekeeping.employee-load.index',
            default => "timekeeping.employee-load.$action",
        };
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(User $user, string $permission = 'view'): void
    {
        $subModule = self::subModule();

        if (! $subModule) {
            if (! $user->isAdmin()) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if ($permission === 'view') {
            if (! $user->hasSubModuleAccess($subModule)) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        $gatePermission = match ($permission) {
            'add' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            default => $permission,
        };

        if (! $user->can("employee-load.$gatePermission")) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    /**
     * @return array<int, array{key: string, label: string, type?: string}>
     */
    public static function listColumns(): array
    {
        return (array) config('employee_load.list_columns', []);
    }

    public static function columnValue(RawEmployeeLoadTransaction $record, string $key, ?string $type = null): mixed
    {
        return match ($key) {
            'batch_no' => $record->formattedBatchNo(),
            'uploaded_by_name' => $record->uploadedBy?->name,
            'date_range' => $record->dateRangeLabel(),
            'records_count' => $record->entries()->count(),
            'dt_uploaded' => $record->dt_uploaded?->format('M j, Y g:i A'),
            default => data_get($record, $key),
        };
    }
}
