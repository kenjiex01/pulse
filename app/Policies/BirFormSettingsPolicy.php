<?php

namespace App\Policies;

use App\Models\SubModule;
use App\Models\User;

class BirFormSettingsPolicy
{
    public const SUB_MODULE_ROUTE = 'payroll.bir-forms.index';

    private function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = $this->subModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        if ($permission === null) {
            return $user->hasSubModuleAccess($subModule);
        }

        return $user->hasSubModulePermission($subModule, $permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user): bool
    {
        return $this->allowed($user, 'update') || $this->allowed($user, 'edit');
    }
}
