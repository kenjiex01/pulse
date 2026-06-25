<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PayrollMaintenance;
use Illuminate\Database\Eloquent\Model;

class PayrollMaintenancePolicy
{
    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = PayrollMaintenance::subModule();

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

    public function update(User $user, Model $record): bool
    {
        return $this->allowed($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        if ($record->is_active) {
            return false;
        }

        return $this->allowed($user, 'delete');
    }
}
