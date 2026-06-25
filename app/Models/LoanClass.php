<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanClass extends Model
{
    public $timestamps = false;

    protected $table = 'lu_loan_classes';

    protected $primaryKey = 'loan_class_id';

    protected $fillable = [
        'loan_class',
    ];
}
