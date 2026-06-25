<?php

namespace App\Policies;

use App\Models\User;
use App\Support\HrLookup;
use Illuminate\Database\Eloquent\Model;

class HrLookupPolicy
{
    private function allowed(User $user, string $lookup, ?string $permission = null): bool
    {
        $subModule = HrLookup::subModule($lookup);

        if (! $subModule) {
            return $user->isAdmin();
        }

        if ($permission === null) {
            return $user->hasSubModuleAccess($subModule);
        }

        return $user->hasSubModulePermission($subModule, $permission);
    }

    public function viewAny(User $user, string $lookup): bool
    {
        return $this->allowed($user, $lookup);
    }

    public function create(User $user, string $lookup): bool
    {
        return $this->allowed($user, $lookup, 'add');
    }

    public function update(User $user, string $lookup, Model $record): bool
    {
        return $this->allowed($user, $lookup, 'update');
    }

    public function delete(User $user, string $lookup, Model $record): bool
    {
        if ($record->is_active) {
            return false;
        }

        return $this->allowed($user, $lookup, 'delete');
    }
}
