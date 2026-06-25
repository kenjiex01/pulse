<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeCaptureFormat extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timecapture_formats';

    protected $primaryKey = 'timecapture_format_id';

    protected $fillable = [
        'device_name',
        'description',
        'time_in_identifier',
        'time_out_identifier',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TimeCaptureFormat $format) {
            if ($format->isForceDeleting()) {
                return;
            }

            $format->fields()->delete();
        });
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TimeCaptureField::class, 'timecapture_format_id', 'timecapture_format_id')
            ->orderBy('column')
            ->orderBy('timecapture_field_id');
    }

    public function standardFields(): HasMany
    {
        return $this->fields()->where('new_field', false);
    }

    public function customFields(): HasMany
    {
        return $this->fields()->where('new_field', true);
    }
}
