<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class LuIcctOffenseCategory extends Model
{
    use SoftDeletes;

    protected $table = 'lu_icct_offense_categories';

    protected $primaryKey = 'icct_offense_category_id';

    protected $fillable = [
        'code',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $code = trim((string) $category->code);
            $category->code = $code;

            if (blank($category->label)) {
                $category->label = $code;
            }

            if ($category->sort_order === null || $category->sort_order === '') {
                $category->sort_order = (int) self::withTrashed()->max('sort_order') + 1;
            }
        });

        static::saved(function (self $category): void {
            LuIcctOffense::withTrashed()
                ->where('icct_offense_category_id', $category->icct_offense_category_id)
                ->update(['category' => $category->code]);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function offenses(): HasMany
    {
        return $this->hasMany(LuIcctOffense::class, 'icct_offense_category_id', 'icct_offense_category_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LuIcctOffensePenalty::class, 'icct_offense_category_id', 'icct_offense_category_id');
    }

    public function isInUse(): bool
    {
        return $this->offenses()->exists() || $this->penalties()->exists();
    }

    /**
     * @return array<int, string>
     */
    public function penaltiesByFrequency(): array
    {
        return $this->penalties()
            ->orderBy('frequency_ordinal')
            ->pluck('penalty', 'frequency_ordinal')
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $penalties
     */
    public function syncPenalties(array $penalties): void
    {
        foreach (LuIcctOffenseFrequency::activeOrdinals() as $ordinal) {
            $penalty = trim((string) ($penalties[$ordinal] ?? $penalties[(string) $ordinal] ?? ''));

            $existing = LuIcctOffensePenalty::query()
                ->where('icct_offense_category_id', $this->icct_offense_category_id)
                ->where('frequency_ordinal', $ordinal)
                ->first();

            if ($penalty === '') {
                $existing?->delete();

                continue;
            }

            LuIcctOffensePenalty::query()->updateOrCreate(
                [
                    'icct_offense_category_id' => $this->icct_offense_category_id,
                    'frequency_ordinal' => $ordinal,
                ],
                [
                    'category' => $this->code,
                    'frequency_label' => LuIcctOffensePenalty::frequencyLabel($ordinal),
                    'penalty' => $penalty,
                    'effective_date' => LuIcctOffensePenalty::EFFECTIVE_DATE,
                ],
            );
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function penaltyMatrix(): array
    {
        $matrix = [];

        foreach (self::catalogOrdered() as $category) {
            $matrix[$category->code] = [];
        }

        LuIcctOffensePenalty::query()
            ->orderBy('frequency_ordinal')
            ->orderBy('category')
            ->get()
            ->each(function (LuIcctOffensePenalty $row) use (&$matrix): void {
                $matrix[$row->category][$row->frequency_ordinal] = $row->penalty;
            });

        return $matrix;
    }

    /**
     * @return array<int|string, string>
     */
    public static function selectOptions(): array
    {
        return self::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('label', 'icct_offense_category_id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function codeSelectOptions(): array
    {
        return self::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function allowedCategoryCodes(): array
    {
        return self::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('code')
            ->values()
            ->all();
    }

    public static function idForCode(string $code): ?int
    {
        $id = self::query()->where('code', $code)->value('icct_offense_category_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return Collection<int, self>
     */
    public static function catalogOrdered(): Collection
    {
        return self::query()->active()->orderBy('sort_order')->orderBy('code')->get();
    }
}
