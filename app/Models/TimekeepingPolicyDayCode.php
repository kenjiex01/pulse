<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimekeepingPolicyDayCode extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_timekeeping_policy_day_codes';

    protected $primaryKey = 'timekeeping_policy_day_code_id';

    protected $fillable = [
        'timekeeping_policy_id',
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(TimekeepingPolicy::class, 'timekeeping_policy_id', 'timekeeping_policy_id');
    }
}
