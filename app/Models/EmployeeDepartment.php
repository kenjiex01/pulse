<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeDepartment extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employee_departments';

    protected $primaryKey = 'employee_department_id';

    protected $fillable = [
        'department_code',
        'department_name',
        'description',
        'sort_order',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
