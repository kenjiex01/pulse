<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timekeeping_templates';

    protected $primaryKey = 'timekeeping_template_id';

    protected $fillable = [
        'template_name',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function templateType(): BelongsTo
    {
        return $this->belongsTo(LuTemplate::class, 'template_name', 'template_id');
    }
}
