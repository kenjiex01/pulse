<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RateGroup extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_rate_groups';

    protected $primaryKey = 'rate_group_id';

    protected $fillable = [
        'rate_basis_id',
        'rate_group_code',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rate_basis_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (RateGroup $rateGroup) {
            if ($rateGroup->isForceDeleting()) {
                return;
            }

            $rateGroup->dayTypeRates()->delete();
        });
    }

    public function rateBasis(): BelongsTo
    {
        return $this->belongsTo(RateBasis::class, 'rate_basis_id', 'rate_basis_id');
    }

    public function dayTypeRates(): HasMany
    {
        return $this->hasMany(RateGroupDayType::class, 'rate_group_id', 'rate_group_id');
    }
}
