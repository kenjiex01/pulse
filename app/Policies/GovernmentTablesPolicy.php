<?php

namespace App\Policies;

use App\Models\User;
use App\Support\GovernmentTables;
use Illuminate\Database\Eloquent\Model;

class GovernmentTablesPolicy
{
    private function allowed(User $user, ?string $permission = null): bool
    {
        $subModule = GovernmentTables::subModule();

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

    public function update(User $user, ?Model $record = null): bool
    {
        return $this->allowed($user, 'update');
    }

    public function delete(User $user, ?Model $record = null): bool
    {
        return $this->allowed($user, 'delete');
    }
}
