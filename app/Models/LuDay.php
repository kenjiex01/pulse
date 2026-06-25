<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuDay extends Model
{
    public $timestamps = false;

    protected $table = 'lu_days';

    protected $primaryKey = 'day_id';

    protected $fillable = ['day'];
}
