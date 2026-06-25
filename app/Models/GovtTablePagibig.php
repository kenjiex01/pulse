<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class GovtTablePagibig extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_pagibig';

    protected $primaryKey = 'govt_table_pagibig_id';

    protected $fillable = [
        'salary_cap',
        'employee_contribution',
        'employer_contribution',
    ];

    protected function casts(): array
    {
        return [
            'salary_cap' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
        ];
    }
}
