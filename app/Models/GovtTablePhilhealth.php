<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GovtTablePhilhealth extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_philhealth';

    protected $primaryKey = 'govt_table_philhealth_id';

    protected $fillable = [
        'salary_from',
        'salary_to',
        'is_percent',
        'percentage',
        'employee_share',
        'employer_share',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'salary_from' => 'decimal:2',
            'salary_to' => 'decimal:2',
            'is_percent' => 'boolean',
            'percentage' => 'decimal:2',
            'employee_share' => 'decimal:2',
            'employer_share' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
