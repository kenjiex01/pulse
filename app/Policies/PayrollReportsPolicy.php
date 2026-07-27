<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PayrollReportsModule;

class PayrollReportsPolicy
{
    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = PayrollReportsModule::subModule();

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

    public function create(User $user): bool
    {
        return $this->allowed($user, 'add');
    }
}
