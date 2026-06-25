<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingHoliday extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timekeeping_holidays';

    protected $primaryKey = 'timekeeping_holiday_id';

    protected $fillable = [
        'timekeeping_holiday_code',
        'description',
        'short_description',
        'dt_datestamp',
        'is_legal',
        'recurring',
    ];

    protected function casts(): array
    {
        return [
            'dt_datestamp' => 'date',
            'is_legal' => 'boolean',
            'recurring' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            TimekeepingHolidayGroup::class,
            'tbl_timekeeping_holiday_group_list',
            'timekeeping_holiday_id',
            'timekeeping_holiday_group_id',
            'timekeeping_holiday_id',
            'timekeeping_holiday_group_id',
        );
    }

    public function yearEntries(): HasMany
    {
        return $this->hasMany(TimekeepingHolidayYear::class, 'timekeeping_holiday_id', 'timekeeping_holiday_id');
    }
}
