<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Campus extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_campuses';

    protected $primaryKey = 'campus_id';

    protected $fillable = [
        'campus_code',
        'campus_name',
        'parent_campus_id',
        'minimum_wage',
        'address',
        'phone',
        'email',
        'website',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_campus_id' => 'integer',
            'minimum_wage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Campus $campus) {
            $parentId = $campus->parent_campus_id ? (int) $campus->parent_campus_id : null;

            if ($parentId === null) {
                return;
            }

            if ($campus->exists && $parentId === (int) $campus->getKey()) {
                throw ValidationException::withMessages([
                    'parent_campus_id' => 'A campus cannot be under itself.',
                ]);
            }

            $visited = $campus->exists ? [(int) $campus->getKey() => true] : [];
            $currentId = $parentId;

            while ($currentId) {
                if (isset($visited[$currentId])) {
                    throw ValidationException::withMessages([
                        'parent_campus_id' => 'Select a parent campus that is not under this campus.',
                    ]);
                }

                $visited[$currentId] = true;
                $currentId = static::query()->whereKey($currentId)->value('parent_campus_id');
                $currentId = $currentId ? (int) $currentId : null;
            }
        });
    }

    public function parentCampus(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_campus_id', 'campus_id');
    }

    public function childCampuses(): HasMany
    {
        return $this->hasMany(self::class, 'parent_campus_id', 'campus_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'campus_id', 'campus_id');
    }

    /**
     * Walk optional Under Campus links for Payroll Register worksheet grouping.
     */
    public function payrollRegisterCampus(): self
    {
        $current = $this;
        $seen = [];

        while ($current->parent_campus_id) {
            $id = (int) $current->getKey();

            if ($id > 0 && isset($seen[$id])) {
                break;
            }

            if ($id > 0) {
                $seen[$id] = true;
            }

            $parent = $current->relationLoaded('parentCampus')
                ? $current->parentCampus
                : $current->parentCampus()->first();

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $current;
    }
}
