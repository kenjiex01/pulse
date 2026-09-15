<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LuIcctOffensePenalty extends Model
{
    public const EFFECTIVE_DATE = '2011-02-16';

    protected $table = 'lu_icct_offense_penalties';

    protected $primaryKey = 'icct_offense_penalty_id';

    protected $fillable = [
        'category',
        'frequency_ordinal',
        'frequency_label',
        'penalty',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'frequency_ordinal' => 'integer',
            'effective_date' => 'date',
        ];
    }

    public static function frequencyLabel(int $ordinal): string
    {
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

        if (! in_array($category, ['A', 'B', 'C', 'D'], true) || $frequencyOrdinal < 1 || $frequencyOrdinal > 6) {
            return null;
        }

        return self::query()
            ->where('category', $category)
            ->where('frequency_ordinal', $frequencyOrdinal)
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
