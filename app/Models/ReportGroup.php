<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGroup extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_report_groups';

    protected $primaryKey = 'report_group_id';

    protected $fillable = [
        'report_classification_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'report_classification_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ReportClassification::class, 'report_classification_id', 'report_classification_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'report_group_id', 'report_group_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }
}
