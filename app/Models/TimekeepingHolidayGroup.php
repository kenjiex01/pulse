<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingHolidayGroup extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timekeeping_holiday_groups';

    protected $primaryKey = 'timekeeping_holiday_group_id';

    protected $fillable = [
        'timekeeping_holiday_group_code',
        'description',
    ];

    public function groupList(): HasMany
    {
        return $this->hasMany(TimekeepingHolidayGroupList::class, 'timekeeping_holiday_group_id', 'timekeeping_holiday_group_id');
    }

    public function holidays(): BelongsToMany
    {
        return $this->belongsToMany(
            TimekeepingHoliday::class,
            'tbl_timekeeping_holiday_group_list',
            'timekeeping_holiday_group_id',
            'timekeeping_holiday_id',
            'timekeeping_holiday_group_id',
            'timekeeping_holiday_id',
        )->orderBy('timekeeping_holiday_code');
    }
}
