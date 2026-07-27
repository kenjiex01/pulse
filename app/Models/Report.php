<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Report extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_reports';

    protected $primaryKey = 'report_id';

    protected $fillable = [
        'report_classification_id',
        'report_group_id',
        'title',
        'description',
        'options_key',
        'generator_key',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'report_classification_id' => 'integer',
            'report_group_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ReportClassification::class, 'report_classification_id', 'report_classification_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id', 'report_group_id');
    }

    public function fileTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            ReportFileType::class,
            'tbl_report_file_type_options',
            'report_id',
            'report_file_type_id',
            'report_id',
            'report_file_type_id',
        );
    }
}
