<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithholdingTaxComputation extends Model
{
    public $timestamps = false;

    protected $table = 'lu_withholding_tax_computations';

    protected $primaryKey = 'withholding_tax_computation_id';

    protected $fillable = [
        'withholding_tax_computation',
    ];

    public function payrollBatches(): HasMany
    {
        return $this->hasMany(PayrollBatch::class, 'withholding_tax_computation_id', 'withholding_tax_computation_id');
    }
}
