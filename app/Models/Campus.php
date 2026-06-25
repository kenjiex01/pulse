<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_campuses';

    protected $primaryKey = 'campus_id';

    protected $fillable = [
        'campus_code',
        'campus_name',
        'address',
        'phone',
        'email',
        'website',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'campus_id', 'campus_id');
    }
}
