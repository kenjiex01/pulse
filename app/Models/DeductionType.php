<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_deduction_types';

    protected $primaryKey = 'deduction_type_id';

    protected $fillable = [
        'deduction_type_code',
        'description',
        'employer_amount',
        'is_amount_percentage',
        'is_valid_govt_deduction',
        'govt_table_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'employer_amount' => 'decimal:2',
            'is_amount_percentage' => 'boolean',
            'is_valid_govt_deduction' => 'boolean',
            'govt_table_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function govtTable()
    {
        return $this->belongsTo(GovtTable::class, 'govt_table_id', 'govt_table_id');
    }
}
