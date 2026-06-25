<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $table = 'tbl_modules';

    protected $fillable = [
        'name',
        'route_name',
        'route_pattern',
        'icon',
        'section',
        'admin_only',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'admin_only' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tbl_role_modules', 'module_id', 'role_id')
            ->withPivot(['can_add', 'can_edit', 'can_update', 'can_delete', 'full_control'])
            ->withTimestamps();
    }

    public function subModules(): HasMany
    {
        return $this->hasMany(SubModule::class, 'module_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
