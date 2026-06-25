<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingHolidayYear extends Model
{
    protected $table = 'tbl_timekeeping_holiday_years';

    protected $primaryKey = 'timekeeping_holiday_year_id';

    protected $fillable = [
        'timekeeping_year_id',
        'timekeeping_holiday_id',
        'timekeeping_holiday_code',
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

    public function year(): BelongsTo
    {
        return $this->belongsTo(TimekeepingYear::class, 'timekeeping_year_id', 'timekeeping_year_id');
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(TimekeepingHoliday::class, 'timekeeping_holiday_id', 'timekeeping_holiday_id');
    }
}
