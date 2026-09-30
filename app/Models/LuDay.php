<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class LuDay extends Model
{
    public $timestamps = false;

    protected $table = 'lu_days';

    protected $primaryKey = 'day_id';

    protected $fillable = ['day'];

    /** lu_days: 1 = Sunday … 7 = Saturday (matches Carbon dayOfWeek). */
    public static function idFromDate(CarbonInterface $date): int
    {
        $dayOfWeek = $date->dayOfWeek;

        return $dayOfWeek === 0 ? 1 : $dayOfWeek + 1;
    }
}
