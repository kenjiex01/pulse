<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class TimekeepingPolicyBreak extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_timekeeping_policy_breaks';

    protected $primaryKey = 'timekeeping_policy_break_id';

    protected $fillable = ['timekeeping_policy_id', 'time_from', 'time_to', 'equivalent'];

    protected function casts(): array
    {
        return [
            'time_from' => 'decimal:4',
            'time_to' => 'decimal:4',
            'equivalent' => 'decimal:4',
        ];
    }
}
