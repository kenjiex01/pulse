<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimekeepingYear extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_timekeeping_years';

    protected $primaryKey = 'timekeeping_year_id';

    protected $fillable = [
        'timekeeping_year',
    ];

    protected function casts(): array
    {
        return [
            'timekeeping_year' => 'integer',
        ];
    }

    public function holidayYears(): HasMany
    {
        return $this->hasMany(TimekeepingHolidayYear::class, 'timekeeping_year_id', 'timekeeping_year_id')
            ->orderBy('dt_datestamp');
    }
}
