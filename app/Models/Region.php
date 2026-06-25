<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'tbl_regions';

    protected $primaryKey = 'region_id';

    protected $fillable = [
        'region_code',
        'region_name',
        'is_active',
    ];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'region_id', 'region_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
