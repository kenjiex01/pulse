<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($role->users()->exists()) {
            return false;
        }

        return ! in_array($role->slug, [Role::SLUG_ADMIN, Role::SLUG_STAFF, Role::SLUG_VIEWER], true);
    }
}
