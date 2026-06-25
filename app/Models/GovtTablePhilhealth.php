<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class GovtTablePhilhealth extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_philhealth';

    protected $primaryKey = 'govt_table_philhealth_id';

    protected $fillable = [
        'salary_from',
        'salary_to',
        'contribution_base',
        'employee_share',
        'employer_share',
    ];

    protected function casts(): array
    {
        return [
            'salary_from' => 'decimal:2',
            'salary_to' => 'decimal:2',
            'contribution_base' => 'decimal:2',
            'employee_share' => 'decimal:2',
            'employer_share' => 'decimal:2',
        ];
    }
}
