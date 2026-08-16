<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_document_types';

    protected $primaryKey = 'document_type_id';

    protected $fillable = [
        'type_code',
        'type_name',
        'description',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
