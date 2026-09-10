<?php

namespace App\Policies;

use App\Models\User;
use App\Support\TimekeepingMemoSetup;
use Illuminate\Database\Eloquent\Model;

class TimekeepingMemoSetupPolicy
{
    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = TimekeepingMemoSetup::subModule();

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

    public function update(User $user, ?Model $record = null): bool
    {
        return $this->allowed($user, 'update');
    }
}
