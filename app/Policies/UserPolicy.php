<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->isAdmin() && User::query()->whereHas('roles', fn ($query) => $query->where('slug', \App\Models\Role::SLUG_ADMIN))->count() <= 1) {
            return false;
        }

        return true;
    }
}
