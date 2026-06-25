<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComputationBasis extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_computation_basis';

    protected $primaryKey = 'computation_basis_id';

    protected $fillable = [
        'computation_basis_code',
        'description',
    ];
}
