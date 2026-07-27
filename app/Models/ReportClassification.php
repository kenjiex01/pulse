<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportClassification extends Model
{
    public const CODE_PAYROLL = 'payroll';

    public const CODE_TIMEKEEPING = 'timekeeping';

    public const CODE_HUMAN_RESOURCE = 'human-resource';

    public $timestamps = false;

    protected $table = 'lu_report_classifications';

    protected $primaryKey = 'report_classification_id';

    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ReportGroup::class, 'report_classification_id', 'report_classification_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'report_classification_id', 'report_classification_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }
}
