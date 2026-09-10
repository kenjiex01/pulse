<?php

namespace App\Support;

use App\Models\SubModule;
use App\Models\User;

class TimekeepingMemoSetup
{
    public const SUB_MODULE_ROUTE = 'timekeeping.memo-setup.index';

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'timekeeping.memo-setup.index',
            'update' => 'timekeeping.memo-setup.update',
            default => "timekeeping.memo-setup.$action",
        };
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(?User $user, string $permission = 'view'): void
    {
        abort_unless($user !== null, 403);

        $gate = match ($permission) {
            'view' => 'memo-setup.viewAny',
            'update' => 'memo-setup.update',
            default => 'memo-setup.viewAny',
        };

        abort_unless($user->can($gate), 403);
    }
}
