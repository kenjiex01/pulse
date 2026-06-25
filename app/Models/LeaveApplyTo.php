<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApplyTo extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_leave_apply_to';

    protected $primaryKey = 'leave_apply_to_id';

    protected $fillable = [
        'name',
    ];
}
