<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const SUPER_ADMIN_EMAIL = 'superadmin@icct.edu.ph';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
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
        return $this->roles->pluck('name')->join(', ') ?: '—';
    }

    public function hasModuleAccess(int|Module $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roles()->doesntExist()) {
            return false;
        }

        $moduleId = $module instanceof Module ? $module->id : $module;

        return $this->roles()
            ->whereHas('modules', function ($query) use ($moduleId) {
                $query->where('module_id', $moduleId)
                    ->where(function ($permissionQuery) {
                        $permissionQuery->where('tbl_role_modules.full_control', true)
                            ->orWhere('tbl_role_modules.can_add', true)
                            ->orWhere('tbl_role_modules.can_edit', true)
                            ->orWhere('tbl_role_modules.can_update', true)
                            ->orWhere('tbl_role_modules.can_delete', true);
                    });
            })
            ->exists();
    }

    public function hasModulePermission(int|Module $module, string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->roles()->doesntExist()) {
            return false;
        }

        $moduleId = $module instanceof Module ? $module->id : $module;

        return $this->roles()
            ->whereHas('modules', function ($query) use ($moduleId, $permission) {
                $query->where('module_id', $moduleId)
                    ->where(function ($permissionQuery) use ($permission) {
                        $permissionQuery->where('tbl_role_modules.full_control', true);

                        match ($permission) {
                            'add' => $permissionQuery->orWhere('tbl_role_modules.can_add', true),
                            'edit' => $permissionQuery->orWhere('tbl_role_modules.can_edit', true),
                            'update' => $permissionQuery->orWhere('tbl_role_modules.can_update', true),
                            'delete' => $permissionQuery->orWhere('tbl_role_modules.can_delete', true),
                            default => null,
                        };
                    });
            })
            ->exists();
    }

    public function hasSubModuleAccess(int|SubModule $subModule): bool
    {
        if ($this->roles()->doesntExist()) {
            return false;
        }

        $subModuleId = $subModule instanceof SubModule ? $subModule->id : $subModule;

        return $this->roles()
            ->whereHas('subModules', function ($query) use ($subModuleId) {
                $query->where('sub_module_id', $subModuleId)
                    ->where(function ($permissionQuery) {
                        $permissionQuery->where('tbl_role_sub_modules.full_control', true)
                            ->orWhere('tbl_role_sub_modules.can_add', true)
                            ->orWhere('tbl_role_sub_modules.can_edit', true)
                            ->orWhere('tbl_role_sub_modules.can_update', true)
                            ->orWhere('tbl_role_sub_modules.can_delete', true);
                    });
            })
            ->exists();
    }

    public function hasSubModulePermission(int|SubModule $subModule, string $permission): bool
    {
        if ($this->roles()->doesntExist()) {
            return false;
        }

        $subModuleId = $subModule instanceof SubModule ? $subModule->id : $subModule;

        return $this->roles()
            ->whereHas('subModules', function ($query) use ($subModuleId, $permission) {
                $query->where('sub_module_id', $subModuleId)
                    ->where(function ($permissionQuery) use ($permission) {
                        $permissionQuery->where('tbl_role_sub_modules.full_control', true);

                        match ($permission) {
                            'add' => $permissionQuery->orWhere('tbl_role_sub_modules.can_add', true),
                            'edit' => $permissionQuery->orWhere('tbl_role_sub_modules.can_edit', true),
                            'update' => $permissionQuery->orWhere('tbl_role_sub_modules.can_update', true),
                            'delete' => $permissionQuery->orWhere('tbl_role_sub_modules.can_delete', true),
                            default => null,
                        };
                    });
            })
            ->exists();
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
