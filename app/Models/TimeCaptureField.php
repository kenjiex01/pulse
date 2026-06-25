<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeCaptureField extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timecapture_fields';

    protected $primaryKey = 'timecapture_field_id';

    protected $fillable = [
        'timecapture_format_id',
        'field_name',
        'column',
        'new_field',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'column' => 'integer',
            'new_field' => 'boolean',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(TimeCaptureFormat::class, 'timecapture_format_id', 'timecapture_format_id');
    }
}
