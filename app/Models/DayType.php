<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayType extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_day_types';

    protected $primaryKey = 'day_type_id';

    protected $fillable = [
        'day_type_code',
        'description',
        'is_restday',
        'is_special_holiday',
        'is_legal_holiday',
        'day_id',
    ];

    protected function casts(): array
    {
        return [
            'is_restday' => 'boolean',
            'is_special_holiday' => 'boolean',
            'is_legal_holiday' => 'boolean',
            'day_id' => 'integer',
        ];
    }

    public function dayOfWeek(): BelongsTo
    {
        return $this->belongsTo(LuDay::class, 'day_id', 'day_id');
    }

    public function rateGroupDayTypes(): HasMany
    {
        return $this->hasMany(RateGroupDayType::class, 'day_type_id', 'day_type_id');
    }

    public function ndRateGroupDayTypes(): HasMany
    {
        return $this->hasMany(NdRateGroupDayType::class, 'day_type_id', 'day_type_id');
    }

    public function isInUse(): bool
    {
        return $this->rateGroupDayTypes()->exists() || $this->ndRateGroupDayTypes()->exists();
    }
}
