<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class LuIcctOffensePenalty extends Model
{
    use SoftDeletes;

    public const EFFECTIVE_DATE = '2011-02-16';

    protected $table = 'lu_icct_offense_penalties';

    protected $primaryKey = 'icct_offense_penalty_id';

    protected $fillable = [
        'icct_offense_category_id',
        'category',
        'frequency_ordinal',
        'frequency_label',
        'penalty',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'icct_offense_category_id' => 'integer',
            'frequency_ordinal' => 'integer',
            'effective_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $penalty): void {
            if ($penalty->frequency_ordinal !== null && $penalty->frequency_ordinal !== '') {
                $penalty->frequency_label = self::frequencyLabel((int) $penalty->frequency_ordinal);
            }

            if ($penalty->icct_offense_category_id) {
                $category = $penalty->relationLoaded('offenseCategory')
                    ? $penalty->offenseCategory
                    : LuIcctOffenseCategory::query()->find($penalty->icct_offense_category_id);

                if ($category !== null) {
                    $penalty->category = $category->code;
                }
            }

            if (blank($penalty->effective_date)) {
                $penalty->effective_date = self::EFFECTIVE_DATE;
            }
        });
    }

    public function offenseCategory(): BelongsTo
    {
        return $this->belongsTo(LuIcctOffenseCategory::class, 'icct_offense_category_id', 'icct_offense_category_id')->withTrashed();
    }

    public static function frequencyLabel(int $ordinal): string
    {
        $label = LuIcctOffenseFrequency::labelForOrdinal($ordinal);

        if ($label !== null && $label !== '') {
            return $label;
        }

        return match ($ordinal) {
            1 => 'First Offense',
            2 => 'Second Offense',
            3 => 'Third Offense',
            4 => 'Fourth Offense',
            5 => 'Fifth Offense',
            6 => 'Sixth Offense',
            default => 'Offense '.$ordinal,
        };
    }

    public static function ordinalSuffix(int $ordinal): string
    {
        return match ($ordinal) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    public static function penaltyFor(string $category, int $frequencyOrdinal): ?string
    {
        $category = strtoupper(trim($category));

        if ($frequencyOrdinal < 1) {
            return null;
        }

        $exact = self::query()
            ->where('category', $category)
            ->where('frequency_ordinal', $frequencyOrdinal)
            ->value('penalty');

        if ($exact !== null && $exact !== '') {
            return $exact;
        }

        // Categories like D may only define 1st offense (Dismissal); higher frequencies use the last tier.
        return self::query()
            ->where('category', $category)
            ->where('frequency_ordinal', '<=', $frequencyOrdinal)
            ->orderByDesc('frequency_ordinal')
            ->value('penalty');
    }

    /**
     * @return Collection<int, self>
     */
    public static function matrixOrdered(): Collection
    {
        return self::query()
            ->orderBy('frequency_ordinal')
            ->orderBy('category')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function distinctPenalties(): array
    {
        return self::query()
            ->orderBy('penalty')
            ->distinct()
            ->pluck('penalty')
            ->values()
            ->all();
    }
}
