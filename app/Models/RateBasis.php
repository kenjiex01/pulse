<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateBasis extends Model
{
    public const FIXED_AMOUNT_PER_HOUR = 2;

    public $timestamps = false;

    protected $table = 'lu_rate_basis';

    protected $primaryKey = 'rate_basis_id';

    protected $fillable = ['rate_basis'];
}
