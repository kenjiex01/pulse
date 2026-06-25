<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Role;
use App\Models\SubModule;
use Illuminate\Database\Seeder;

class RoleModuleSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->first();

        if (! $adminRole) {
            return;
        }

        $parentModuleIds = Module::query()
            ->whereHas('subModules', fn ($query) => $query->where('is_active', true))
            ->pluck('id')
            ->all();

        $modulePermissions = Module::query()
            ->where('is_active', true)
            ->whereNotIn('id', $parentModuleIds)
            ->pluck('id')
            ->mapWithKeys(fn (int $moduleId) => [
                $moduleId => [
                    'can_add' => true,
                    'can_edit' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'full_control' => true,
                ],
            ])
            ->all();

        $subModulePermissions = SubModule::query()
            ->where('is_active', true)
            ->pluck('id')
            ->mapWithKeys(fn (int $subModuleId) => [
                $subModuleId => [
                    'can_add' => true,
                    'can_edit' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'full_control' => true,
                ],
            ])
            ->all();

        $adminRole->modules()->sync($modulePermissions);
        $adminRole->subModules()->sync($subModulePermissions);
    }
}
