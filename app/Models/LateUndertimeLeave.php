<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LateUndertimeLeave extends Model
{
    public $timestamps = false;

    protected $table = 'lu_late_undertime_leaves';

    protected $primaryKey = 'late_undertime_leave_id';

    protected $fillable = [
        'late_undertime_leave_type',
    ];
}
