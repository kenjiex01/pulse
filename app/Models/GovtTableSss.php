<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class GovtTableSss extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_sss';

    protected $primaryKey = 'govt_table_sss_id';

    protected $fillable = [
        'compensation_from',
        'compensation_to',
        'salary_credit',
        'employer_sss',
        'employee_sss',
        'employer_ec',
    ];

    protected function casts(): array
    {
        return [
            'compensation_from' => 'decimal:2',
            'compensation_to' => 'decimal:2',
            'salary_credit' => 'decimal:2',
            'employer_sss' => 'decimal:2',
            'employee_sss' => 'decimal:2',
            'employer_ec' => 'decimal:2',
        ];
    }
}
