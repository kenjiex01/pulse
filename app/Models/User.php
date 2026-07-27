<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    public const SUPER_ADMIN_EMAIL = 'superadmin@icct.edu.ph';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /** @var array<int, true>|null */
    private ?array $accessibleSubModuleIdMap = null;

    /** @var array<int, true>|null */
    private ?array $accessibleModuleIdMap = null;

    /** @var array<int, array<string, bool>>|null */
    private ?array $subModulePermissionMap = null;

    /** @var array<int, array<string, bool>>|null */
    private ?array $modulePermissionMap = null;

    /** @var array<int, int>|null */
    private ?array $roleIdsCache = null;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tbl_user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->slug === $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => in_array($role->slug, $slugs, true));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::SLUG_ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return strcasecmp($this->email, self::SUPER_ADMIN_EMAIL) === 0;
    }

    public function roleLabel(): string
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('name')->join(', ') ?: '—';
    }

    /**
     * @return array<int, int>
     */
    public function roleIds(): array
    {
        if ($this->roleIdsCache !== null) {
            return $this->roleIdsCache;
        }

        $this->loadMissing('roles');
        $this->roleIdsCache = $this->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->roleIdsCache;
    }

    /**
     * @return array<int, int>
     */
    public function accessibleSubModuleIds(): array
    {
        $this->resolveAccessibleSubModuleIdMap();

        return array_keys($this->accessibleSubModuleIdMap ?? []);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleModuleIds(): array
    {
        $this->resolveAccessibleModuleIdMap();

        return array_keys($this->accessibleModuleIdMap ?? []);
    }

    public function hasModuleAccess(int|Module $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roleIds() === []) {
            return false;
        }

        $moduleId = $module instanceof Module ? $module->id : $module;
        $this->resolveAccessibleModuleIdMap();

        return isset($this->accessibleModuleIdMap[$moduleId]);
    }

    public function hasModulePermission(int|Module $module, string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roleIds() === []) {
            return false;
        }

        $moduleId = $module instanceof Module ? $module->id : $module;
        $permissions = $this->modulePermissionMap()[$moduleId] ?? [];

        return $this->permissionGranted($permissions, $permission);
    }

    public function hasSubModuleAccess(int|SubModule $subModule): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roleIds() === []) {
            return false;
        }

        $subModuleId = $subModule instanceof SubModule ? $subModule->id : $subModule;
        $this->resolveAccessibleSubModuleIdMap();

        return isset($this->accessibleSubModuleIdMap[$subModuleId]);
    }

    public function hasSubModulePermission(int|SubModule $subModule, string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roleIds() === []) {
            return false;
        }

        $subModuleId = $subModule instanceof SubModule ? $subModule->id : $subModule;
        $permissions = $this->subModulePermissionMap()[$subModuleId] ?? [];

        return $this->permissionGranted($permissions, $permission);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function subModulePermissionMap(): array
    {
        if ($this->subModulePermissionMap !== null) {
            return $this->subModulePermissionMap;
        }

        $this->subModulePermissionMap = $this->buildPermissionMap('tbl_role_sub_modules', 'sub_module_id');

        return $this->subModulePermissionMap;
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function modulePermissionMap(): array
    {
        if ($this->modulePermissionMap !== null) {
            return $this->modulePermissionMap;
        }

        $this->modulePermissionMap = $this->buildPermissionMap('tbl_role_modules', 'module_id');

        return $this->modulePermissionMap;
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function buildPermissionMap(string $table, string $entityColumn): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        $roleIds = $this->roleIds();

        if ($roleIds === []) {
            return [];
        }

        $rows = DB::table($table)
            ->whereIn('role_id', $roleIds)
            ->get(['role_id', $entityColumn, 'can_add', 'can_edit', 'can_update', 'can_delete', 'full_control']);

        $map = [];

        foreach ($rows as $row) {
            $entityId = (int) $row->{$entityColumn};
            $existing = $map[$entityId] ?? [
                'add' => false,
                'edit' => false,
                'update' => false,
                'delete' => false,
                'full_control' => false,
            ];

            $map[$entityId] = [
                'add' => $existing['add'] || (bool) $row->can_add || (bool) $row->full_control,
                'edit' => $existing['edit'] || (bool) $row->can_edit || (bool) $row->full_control,
                'update' => $existing['update'] || (bool) $row->can_update || (bool) $row->full_control,
                'delete' => $existing['delete'] || (bool) $row->can_delete || (bool) $row->full_control,
                'full_control' => $existing['full_control'] || (bool) $row->full_control,
            ];
        }

        return $map;
    }

    private function resolveAccessibleSubModuleIdMap(): void
    {
        if ($this->accessibleSubModuleIdMap !== null) {
            return;
        }

        if ($this->isAdmin()) {
            $this->accessibleSubModuleIdMap = SubModule::query()
                ->where('is_active', true)
                ->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => true])
                ->all();

            return;
        }

        $roleIds = $this->roleIds();

        if ($roleIds === []) {
            $this->accessibleSubModuleIdMap = [];

            return;
        }

        $this->accessibleSubModuleIdMap = \App\Services\SidebarNavigationService::permissionGrantedMap(
            'tbl_role_sub_modules',
            $roleIds,
        );
    }

    private function resolveAccessibleModuleIdMap(): void
    {
        if ($this->accessibleModuleIdMap !== null) {
            return;
        }

        if ($this->isAdmin()) {
            $this->accessibleModuleIdMap = Module::query()
                ->where('is_active', true)
                ->whereNotNull('route_name')
                ->whereDoesntHave('subModules', fn ($query) => $query->where('is_active', true))
                ->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => true])
                ->all();

            return;
        }

        $roleIds = $this->roleIds();

        if ($roleIds === []) {
            $this->accessibleModuleIdMap = [];

            return;
        }

        $this->accessibleModuleIdMap = \App\Services\SidebarNavigationService::permissionGrantedMap(
            'tbl_role_modules',
            $roleIds,
        );
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function permissionGranted(array $permissions, string $permission): bool
    {
        if (($permissions['full_control'] ?? false) === true) {
            return true;
        }

        return match ($permission) {
            'add' => (bool) ($permissions['add'] ?? false),
            'edit' => (bool) ($permissions['edit'] ?? false),
            'update' => (bool) ($permissions['update'] ?? false),
            'delete' => (bool) ($permissions['delete'] ?? false),
            default => false,
        };
    }

    public function logSnapshot(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role_ids' => $this->roles()->pluck('roles.id')->all(),
        ];
    }
}
