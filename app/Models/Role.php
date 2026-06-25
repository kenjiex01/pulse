<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use SoftDeletes;

    public const SLUG_ADMIN = 'admin';

    public const SLUG_STAFF = 'staff';

    public const SLUG_VIEWER = 'viewer';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tbl_user_roles', 'role_id', 'user_id')
            ->withTimestamps();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tbl_role_modules', 'role_id', 'module_id')
            ->withPivot(['can_add', 'can_edit', 'can_update', 'can_delete', 'full_control'])
            ->withTimestamps();
    }

    public function subModules(): BelongsToMany
    {
        return $this->belongsToMany(SubModule::class, 'tbl_role_sub_modules', 'role_id', 'sub_module_id')
            ->withPivot(['can_add', 'can_edit', 'can_update', 'can_delete', 'full_control'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::SLUG_ADMIN;
    }

    public function syncModulePermissions(array $modulesInput): void
    {
        $sync = [];

        foreach ($modulesInput as $moduleId => $permissions) {
            if (! is_array($permissions)) {
                continue;
            }

            $fullControl = filter_var($permissions['full_control'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canAdd = $fullControl || filter_var($permissions['can_add'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canEdit = $fullControl || filter_var($permissions['can_edit'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canUpdate = $fullControl || filter_var($permissions['can_update'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canDelete = $fullControl || filter_var($permissions['can_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $canAdd && ! $canEdit && ! $canUpdate && ! $canDelete && ! $fullControl) {
                continue;
            }

            $sync[$moduleId] = [
                'can_add' => $canAdd,
                'can_edit' => $canEdit,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'full_control' => $fullControl,
            ];
        }

        $this->modules()->sync($sync);
    }

    public function syncSubModulePermissions(array $subModulesInput): void
    {
        $sync = [];

        foreach ($subModulesInput as $subModuleId => $permissions) {
            if (! is_array($permissions)) {
                continue;
            }

            $fullControl = filter_var($permissions['full_control'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canAdd = $fullControl || filter_var($permissions['can_add'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canEdit = $fullControl || filter_var($permissions['can_edit'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canUpdate = $fullControl || filter_var($permissions['can_update'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canDelete = $fullControl || filter_var($permissions['can_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $canAdd && ! $canEdit && ! $canUpdate && ! $canDelete && ! $fullControl) {
                continue;
            }

            $sync[$subModuleId] = [
                'can_add' => $canAdd,
                'can_edit' => $canEdit,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'full_control' => $fullControl,
            ];
        }

        $this->subModules()->sync($sync);
    }

    public function syncMembers(array $memberIds): void
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $currentMemberIds = $this->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $toDetach = array_diff($currentMemberIds, $memberIds);
        if ($toDetach !== []) {
            $this->users()->detach($toDetach);
        }

        $toAttach = array_diff($memberIds, $currentMemberIds);
        if ($toAttach !== []) {
            $this->users()->attach($toAttach);
        }
    }
}
