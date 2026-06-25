<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuExcessHour extends Model
{
    public $timestamps = false;

    protected $table = 'lu_excess_hours';

    protected $primaryKey = 'excess_hour_id';

    protected $fillable = ['excess_hour'];
}
