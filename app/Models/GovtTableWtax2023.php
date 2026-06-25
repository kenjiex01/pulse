<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovtTableWtax2023 extends Model
{
    public const DAILY = 1;

    public const WEEKLY = 2;

    public const SEMI_MONTHLY = 3;

    public const MONTHLY = 4;

    public const COLUMN_COUNT = 6;

    public $timestamps = false;

    protected $table = 'tbl_govt_table_wtax_2023';

    protected $primaryKey = 'govt_table_wtax_2023_id';

    protected $fillable = [
        'withholding_tax_table_type_id',
        'column_id',
        'amount',
        'tax_plus',
        'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'withholding_tax_table_type_id' => 'integer',
            'column_id' => 'integer',
            'amount' => 'decimal:2',
            'tax_plus' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }
}
