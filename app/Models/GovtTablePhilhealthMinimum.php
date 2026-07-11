<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GovtTablePhilhealthMinimum extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_philhealth_minimum';

    protected $primaryKey = 'govt_table_philhealth_minimum_id';

    protected $fillable = [
        'employee_amount',
        'employer_amount',
    ];

    protected function casts(): array
    {
        return [
            'employee_amount' => 'decimal:2',
            'employer_amount' => 'decimal:2',
        ];
    }
}
