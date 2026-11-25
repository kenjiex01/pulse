<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentType extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_employment_types';

    protected $primaryKey = 'employment_type_id';

    protected $fillable = [
        'type_code',
        'type_name',
        'description',
        'sort_order',
        'is_active',
        'deduct_government_deductions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deduct_government_deductions' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Government deductions apply when every matched employment type allows them.
     * Names that do not match a lookup row keep the previous behavior and still deduct.
     *
     * @param  list<string|null>  $names
     */
    public static function allowsGovernmentDeductions(array $names): bool
    {
        $needles = collect($names)
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter()
            ->unique()
            ->values();

        if ($needles->isEmpty()) {
            return true;
        }

        $matched = static::query()->get()->filter(function (self $type) use ($needles): bool {
            $name = strtolower(trim((string) $type->type_name));
            $code = strtolower(trim((string) $type->type_code));

            return $needles->contains($name) || ($code !== '' && $needles->contains($code));
        });

        if ($matched->isEmpty()) {
            return true;
        }

        return $matched->every(fn (self $type) => $type->deduct_government_deductions);
    }
}
