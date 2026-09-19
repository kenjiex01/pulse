<?php

namespace App\Policies;

use App\Models\CompanyDocumentForm;
use App\Models\SubModule;
use App\Models\User;

class CompanyDocumentFormPolicy
{
    private bool $subModuleResolved = false;

    private ?SubModule $subModuleMemo = null;

    private function subModule(): ?SubModule
    {
        if ($this->subModuleResolved) {
            return $this->subModuleMemo;
        }

        $this->subModuleMemo = SubModule::query()
            ->where('route_name', 'company-documents.index')
            ->where('is_active', true)
            ->first();
        $this->subModuleResolved = true;

        return $this->subModuleMemo;
    }

    private function hasAccess(User $user): bool
    {
        $subModule = $this->subModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModuleAccess($subModule);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(User $user, CompanyDocumentForm $form): bool
    {
        return $this->hasAccess($user);
    }

    public function create(User $user): bool
    {
        $subModule = $this->subModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'add');
    }

    public function update(User $user, CompanyDocumentForm $form): bool
    {
        $subModule = $this->subModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'update');
    }

    public function delete(User $user, CompanyDocumentForm $form): bool
    {
        if ($form->submissions()->exists()) {
            return false;
        }

        $subModule = $this->subModule();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModulePermission($subModule, 'delete');
    }

    public function design(User $user, CompanyDocumentForm $form): bool
    {
        return $this->update($user, $form);
    }

    public function send(User $user, CompanyDocumentForm $form): bool
    {
        return $this->update($user, $form) && $form->is_active;
    }

    public function approve(User $user): bool
    {
        $subModule = SubModule::query()
            ->where('route_name', 'company-documents.approvals.index')
            ->where('is_active', true)
            ->first();

        if (! $subModule) {
            return $user->isAdmin();
        }

        return $user->hasSubModuleAccess($subModule);
    }
}
