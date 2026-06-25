<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PayrollCalendarModule;

class PayrollCalendarPolicy
{
    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = PayrollCalendarModule::subModule();

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

    public function update(User $user): bool
    {
        return $this->allowed($user, 'update');
    }

    public function delete(User $user): bool
    {
        return $this->allowed($user, 'delete');
    }
}
