<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateGroupDayType extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_rate_group_day_types';

    protected $primaryKey = 'rate_group_day_type_id';

    protected $fillable = [
        'rate_group_id',
        'day_type_id',
        'time_type_id',
        'computation_basis_id',
        'income_type_id',
        'rate',
        'is_taxable',
    ];

    protected function casts(): array
    {
        return [
            'rate_group_id' => 'integer',
            'day_type_id' => 'integer',
            'time_type_id' => 'integer',
            'computation_basis_id' => 'integer',
            'income_type_id' => 'integer',
            'rate' => 'decimal:4',
            'is_taxable' => 'boolean',
        ];
    }

    public function dayType(): BelongsTo
    {
        return $this->belongsTo(DayType::class, 'day_type_id', 'day_type_id');
    }

    public function timeType(): BelongsTo
    {
        return $this->belongsTo(TimeType::class, 'time_type_id', 'time_type_id');
    }

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id', 'income_type_id');
    }

    public function computationBasis(): BelongsTo
    {
        return $this->belongsTo(ComputationBasis::class, 'computation_basis_id', 'computation_basis_id');
    }
}
