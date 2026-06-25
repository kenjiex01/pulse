<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class LoanType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_loan_types';

    protected $primaryKey = 'loan_type_id';

    protected $fillable = [
        'loan_type_code',
        'description',
        'loan_class_id',
        'sss_loan_type',
        'is_viewable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'loan_class_id' => 'integer',
            'is_viewable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function loanClass()
    {
        return $this->belongsTo(LoanClass::class, 'loan_class_id', 'loan_class_id');
    }
}
