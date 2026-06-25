<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NdRateGroup extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tbl_nd_rate_groups';

    protected $primaryKey = 'nd_rate_group_id';

    protected $fillable = [
        'rate_basis_id',
        'nd_rate_group_code',
        'description',
        'tm_start',
        'tm_end',
    ];

    protected function casts(): array
    {
        return [
            'rate_basis_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (NdRateGroup $ndRateGroup) {
            if ($ndRateGroup->isForceDeleting()) {
                return;
            }

            $ndRateGroup->dayTypeRates()->delete();
        });
    }

    public function rateBasis(): BelongsTo
    {
        return $this->belongsTo(RateBasis::class, 'rate_basis_id', 'rate_basis_id');
    }

    public function dayTypeRates(): HasMany
    {
        return $this->hasMany(NdRateGroupDayType::class, 'nd_rate_group_id', 'nd_rate_group_id');
    }
}
