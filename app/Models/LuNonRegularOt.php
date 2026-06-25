<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuNonRegularOt extends Model
{
    public $timestamps = false;

    protected $table = 'lu_non_regular_ot';

    protected $primaryKey = 'non_regular_ot_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['non_regular_ot_id', 'description'];
}
