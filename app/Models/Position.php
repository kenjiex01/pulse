<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_positions';

    protected $primaryKey = 'position_id';

    protected $fillable = [
        'position_name',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
