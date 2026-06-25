<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class GovtTableWtaxAnnual2023 extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_wtax_annual_2023';

    protected $primaryKey = 'govt_table_wtax_annual_2023_id';

    protected $fillable = [
        'income_from',
        'income_to',
        'amount_due',
        'percentage_due',
    ];

    protected function casts(): array
    {
        return [
            'income_from' => 'decimal:2',
            'income_to' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'percentage_due' => 'decimal:2',
        ];
    }
}
