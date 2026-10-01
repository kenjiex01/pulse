<?php

namespace App\Support;

use App\Models\SubModule;
use App\Models\User;

class HrSetup
{
    public const SUB_MODULE_ROUTE = 'hr.setup.index';

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'hr.setup.index',
            'update' => 'hr.setup.update',
            default => "hr.setup.$action",
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
            'view' => 'hr-setup.viewAny',
            'update' => 'hr-setup.update',
            default => 'hr-setup.viewAny',
        };

        abort_unless($user->can($gate), 403);
    }
}
