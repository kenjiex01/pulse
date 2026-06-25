<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingHolidayGroupList extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_timekeeping_holiday_group_list';

    protected $primaryKey = 'timekeeping_holiday_group_list_id';

    protected $fillable = [
        'timekeeping_holiday_group_id',
        'timekeeping_holiday_id',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TimekeepingHolidayGroup::class, 'timekeeping_holiday_group_id', 'timekeeping_holiday_group_id');
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(TimekeepingHoliday::class, 'timekeeping_holiday_id', 'timekeeping_holiday_id');
    }
}
