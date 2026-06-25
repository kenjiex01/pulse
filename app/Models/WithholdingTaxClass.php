<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class WithholdingTaxClass extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_withholding_tax_classes';

    protected $primaryKey = 'withholding_tax_class_id';

    protected $fillable = [
        'withholding_tax_class_code',
        'description',
        'number_of_dependents',
        'exemption_amount',
        'is_married',
    ];

    protected function casts(): array
    {
        return [
            'number_of_dependents' => 'integer',
            'exemption_amount' => 'decimal:2',
            'is_married' => 'boolean',
        ];
    }
}
