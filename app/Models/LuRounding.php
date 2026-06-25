<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuRounding extends Model
{
    public $timestamps = false;

    protected $table = 'lu_rounding';

    protected $primaryKey = 'rounding_id';

    protected $fillable = ['rounding'];
}
