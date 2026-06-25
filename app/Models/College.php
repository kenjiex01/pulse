<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class College extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_colleges';

    protected $primaryKey = 'college_id';

    protected $fillable = [
        'campus_id',
        'college_code',
        'college_name',
        'description',
        'is_active',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'campus_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
