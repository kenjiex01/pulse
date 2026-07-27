<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'mpf_salary_credit',
        'employer_sss',
        'employee_sss',
        'employer_mpf_share',
        'employee_mpf_share',
        'employer_ec',
    ];

    protected function casts(): array
    {
        return [
            'compensation_from' => 'decimal:2',
            'compensation_to' => 'decimal:2',
            'salary_credit' => 'decimal:2',
            'mpf_salary_credit' => 'decimal:2',
            'employer_sss' => 'decimal:2',
            'employee_sss' => 'decimal:2',
            'employer_mpf_share' => 'decimal:2',
            'employee_mpf_share' => 'decimal:2',
            'employer_ec' => 'decimal:2',
        ];
    }
}
