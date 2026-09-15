<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class LuIcctOffense extends Model
{
    use SoftDeletes;

    public const EFFECTIVE_DATE = '2011-02-16';

    /** @var array<string, string> */
    public const HEADING_LABELS = [
        'I' => 'I. Offenses Against Company Interest and Policy',
        'II' => 'II. Offenses Against Administration',
        'III' => 'III. Offenses Against Authority',
        'IV' => 'IV. Offenses Against Persons',
        'V' => 'V. Offenses Against Property',
        'VI' => 'VI. Offenses Against Decency, Good Customs or Ethics',
        'VII' => 'VII. Offenses Against Security and Public Order',
        'VIII' => 'VIII. Others (repeat infractions within 12 months)',
    ];

    /** @var array<string, string> */
    public const CATEGORY_OPTIONS = [
        'A' => 'A',
        'B' => 'B',
        'B - C' => 'B - C',
        'B - D' => 'B - D',
        'C' => 'C',
        'C - D' => 'C - D',
        'D' => 'D',
    ];

    protected $table = 'lu_icct_offenses';

    protected $primaryKey = 'icct_offense_id';

    protected $fillable = [
        'heading_roman',
        'heading_label',
        'section_number',
        'section_code',
        'nature_of_offense',
        'category',
        'sort_order',
        'effective_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'section_number' => 'integer',
            'sort_order' => 'integer',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $offense): void {
            $heading = trim((string) $offense->heading_roman);
            $sectionNumber = $offense->section_number;

            if ($heading !== '') {
                $offense->heading_label = self::headingLabel($heading);

                if ($sectionNumber !== null && $sectionNumber !== '') {
                    $offense->section_code = $heading.'.'.$sectionNumber;
                }
            }

            if (blank($offense->effective_date)) {
                $offense->effective_date = self::EFFECTIVE_DATE;
            }

            if ($offense->sort_order === null || $offense->sort_order === '') {
                $offense->sort_order = (int) self::withTrashed()->max('sort_order') + 1;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function companyDocumentForms(): HasMany
    {
        return $this->hasMany(CompanyDocumentForm::class, 'icct_offense_id', 'icct_offense_id');
    }

    public function dropdownLabel(): string
    {
        return $this->section_code.' — '.$this->nature_of_offense;
    }

    public function compactDropdownLabel(int $maxNatureLength = 72): string
    {
        $nature = $this->nature_of_offense;

        if (strlen($nature) > $maxNatureLength) {
            $nature = rtrim(substr($nature, 0, max(1, $maxNatureLength - 1))).'…';
        }

        return $this->section_code.' — '.$nature;
    }

    public function dropdownChoiceValue(): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $this->dropdownLabel()) ?? ''));

        return trim($slug, '_') ?: 'offense';
    }

    /**
     * @return list<string>
     */
    public static function natureDropdownLabels(): array
    {
        return self::catalogOrdered()
            ->map(fn (self $offense) => $offense->dropdownLabel())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, self>
     */
    public static function catalogOrdered(): Collection
    {
        return self::query()->active()->orderBy('sort_order')->get();
    }

    /**
     * Active catalog plus any currently assigned (possibly inactive) offenses.
     *
     * @param  iterable<int|string|null>  $includeIds
     * @return Collection<int, self>
     */
    public static function catalogForSelection(iterable $includeIds = []): Collection
    {
        $includeIds = collect($includeIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return self::query()
            ->where(function (Builder $query) use ($includeIds) {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('icct_offense_id', $includeIds);
                }
            })
            ->orderBy('sort_order')
            ->get();
    }

    public static function headingLabel(string $roman): string
    {
        return self::HEADING_LABELS[$roman] ?? $roman;
    }
}
