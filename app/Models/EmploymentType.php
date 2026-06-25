<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employment_types';

    protected $primaryKey = 'employment_type_id';

    protected $fillable = [
        'type_code',
        'type_name',
        'description',
        'sort_order',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
