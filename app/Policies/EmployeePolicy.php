<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\SubModule;
use App\Models\User;

class EmployeePolicy
{
    private function employeesSubModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', 'employees.index')
            ->where('is_active', true)
            ->first();
    }

    private function hasAccess(User $user): bool
    {
        $subModule = $this->employeesSubModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModuleAccess($subModule);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->hasAccess($user);
    }

    public function create(User $user): bool
    {
        $subModule = $this->employeesSubModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'add');
    }

    public function syncFromSkolaris(User $user): bool
    {
        return $this->create($user) || $this->canUpdateEmployees($user);
    }

    private function canUpdateEmployees(User $user): bool
    {
        $subModule = $this->employeesSubModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'update');
    }

    public function update(User $user, Employee $employee): bool
    {
        $subModule = $this->employeesSubModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        if (! $employee->isInactive()) {
            return false;
        }

        $subModule = $this->employeesSubModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'delete');
    }
}
