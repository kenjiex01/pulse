<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasicComputation extends Model
{
    public const TIME_IN_OUT = 1;

    public const LEAVES = 2;

    public $timestamps = false;

    protected $table = 'lu_basic_computations';

    protected $primaryKey = 'basic_computation_id';

    protected $fillable = [
        'basic_computation',
    ];
}
