<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SidebarNavigationService
{
    /** @var array<int, Collection<string, EloquentCollection<int, Module>>> */
    private static array $memory = [];

    /**
     * @return Collection<string, EloquentCollection<int, Module>>
     */
    public function groupedModulesFor(User $user): Collection
    {
        if (isset(self::$memory[$user->id])) {
            return self::$memory[$user->id];
        }

        return self::$memory[$user->id] = $this->buildGroupedModules($user);
    }

    public static function forgetForUser(int $userId): void
    {
        unset(self::$memory[$userId]);
    }

    public static function forgetForRole(Role $role): void
    {
        $role->users()
            ->pluck('users.id')
            ->each(fn (int $userId) => self::forgetForUser($userId));
    }

    /**
     * @return Collection<string, EloquentCollection<int, Module>>
     */
    private function buildGroupedModules(User $user): Collection
    {
        $accessibleSubModuleIds = array_flip($user->accessibleSubModuleIds());
        $accessibleModuleIds = array_flip($user->accessibleModuleIds());

        $modules = Module::query()
            ->with(['subModules' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Module $module) use ($accessibleSubModuleIds) {
                if ($module->subModules->isNotEmpty()) {
                    $module->setRelation(
                        'subModules',
                        $module->subModules->filter(
                            fn ($subModule) => isset($accessibleSubModuleIds[$subModule->id]),
                        )->values(),
                    );
                }

                return $module;
            })
            ->filter(function (Module $module) use ($accessibleModuleIds) {
                if ($module->subModules->isNotEmpty()) {
                    return $module->subModules->isNotEmpty();
                }

                return filled($module->route_name) && isset($accessibleModuleIds[$module->id]);
            })
            ->values();

        /** @var Collection<string, EloquentCollection<int, Module>> $grouped */
        $grouped = $modules->groupBy('section');

        return $grouped;
    }

    /**
     * @return array<int, true>
     */
    public static function permissionGrantedMap(string $table, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $entityColumn = $table === 'tbl_role_sub_modules' ? 'sub_module_id' : 'module_id';

        $rows = DB::table($table)
            ->whereIn('role_id', $roleIds)
            ->where(function ($permissionQuery) {
                $permissionQuery->where('full_control', true)
                    ->orWhere('can_add', true)
                    ->orWhere('can_edit', true)
                    ->orWhere('can_update', true)
                    ->orWhere('can_delete', true);
            })
            ->distinct()
            ->pluck($entityColumn);

        $map = [];

        foreach ($rows as $entityId) {
            $map[(int) $entityId] = true;
        }

        return $map;
    }
}
