<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeClass extends Model
{
    public $timestamps = false;

    protected $table = 'lu_income_classes';

    protected $primaryKey = 'income_class_id';

    protected $fillable = [
        'income_class',
    ];
}
