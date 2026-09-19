<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class LuIcctOffenseFrequency extends Model
{
    use SoftDeletes;

    protected $table = 'lu_icct_offense_frequencies';

    protected $primaryKey = 'icct_offense_frequency_id';

    protected $fillable = [
        'frequency_ordinal',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency_ordinal' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $frequency): void {
            if ($frequency->frequency_ordinal === null || $frequency->frequency_ordinal === '') {
                $frequency->frequency_ordinal = (int) self::withTrashed()->max('frequency_ordinal') + 1;
            }

            if ($frequency->sort_order === null || $frequency->sort_order === '') {
                $frequency->sort_order = $frequency->frequency_ordinal;
            }
        });

        static::deleted(function (self $frequency): void {
            LuIcctOffensePenalty::query()
                ->where('frequency_ordinal', $frequency->frequency_ordinal)
                ->delete();
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        return LuIcctOffensePenalty::query()
            ->where('frequency_ordinal', $this->frequency_ordinal)
            ->exists();
    }

    /**
     * @return Collection<int, self>
     */
    public static function catalogOrdered(): Collection
    {
        return self::query()->active()->orderBy('sort_order')->orderBy('frequency_ordinal')->get();
    }

    /**
     * @return list<int>
     */
    public static function activeOrdinals(): array
    {
        return self::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('frequency_ordinal')
            ->pluck('frequency_ordinal')
            ->map(fn ($ordinal) => (int) $ordinal)
            ->values()
            ->all();
    }

    public static function labelForOrdinal(int $ordinal): ?string
    {
        return self::query()
            ->where('frequency_ordinal', $ordinal)
            ->value('label');
    }
}
