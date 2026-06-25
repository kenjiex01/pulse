<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubModule extends Model
{
    protected $table = 'sys_sub_modules';

    protected $fillable = [
        'module_id',
        'name',
        'route_name',
        'route_pattern',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tbl_role_sub_modules', 'sub_module_id', 'role_id')
            ->withPivot(['can_add', 'can_edit', 'can_update', 'can_delete', 'full_control'])
            ->withTimestamps();
    }
}
