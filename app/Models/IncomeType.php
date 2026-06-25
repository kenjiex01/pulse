<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class IncomeType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_income_types';

    protected $primaryKey = 'income_type_id';

    protected $fillable = [
        'income_class_id',
        'income_type_code',
        'description',
        'is_non_taxable',
        'is_in_compensation_limit',
        'breakdown_in_ytd_report',
        'is_default_basic',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'income_class_id' => 'integer',
            'is_non_taxable' => 'boolean',
            'is_in_compensation_limit' => 'boolean',
            'breakdown_in_ytd_report' => 'boolean',
            'is_default_basic' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function incomeClass()
    {
        return $this->belongsTo(IncomeClass::class, 'income_class_id', 'income_class_id');
    }
}
