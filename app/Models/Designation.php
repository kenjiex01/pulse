<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_designations';

    protected $primaryKey = 'designation_id';

    protected $fillable = [
        'designation_name',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
