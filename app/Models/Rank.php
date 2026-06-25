<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_ranks';

    protected $primaryKey = 'rank_id';

    protected $fillable = [
        'rank_name',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
