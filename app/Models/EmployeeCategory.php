<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmployeeCategory extends Model
{
    use SoftDeletes;

    public const BASIS_LOGS = 'logs';

    public const BASIS_TEACHING_LOADS = 'teaching_loads';

    protected $table = 'tbl_employee_categories';

    protected $primaryKey = 'employee_category_id';

    protected $fillable = [
        'code',
        'name',
        'payroll_basis',
        'sort_order',
        'is_protected',
        'is_active',
    ];

    /** @var array<string, self>|null */
    private static ?array $cache = null;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_protected' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeCategory $category): void {
            if (blank($category->code)) {
                $category->code = self::codeFromName((string) $category->name);
            }

            if ($category->sort_order === null) {
                $category->sort_order = ((int) self::query()->max('sort_order')) + 1;
            }
        });

        static::saved(function (): void {
            self::$cache = null;
        });

        static::deleted(function (): void {
            self::$cache = null;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getPayrollBasisLabelAttribute(): string
    {
        return self::basisLabel((string) $this->payroll_basis);
    }

    public static function basisLabel(string $basis): string
    {
        return match ($basis) {
            self::BASIS_TEACHING_LOADS => 'Teaching Loads',
            default => 'Logs',
        };
    }

    public static function basisHelp(string $basis): string
    {
        if ($basis === '') {
            return 'Payroll hours follow the category. Logs uses time in/out like Staff. Teaching Loads uses class schedules like Faculty.';
        }

        if ($basis === self::BASIS_TEACHING_LOADS) {
            return 'This category uses teaching loads, same as Faculty. Basic hours come from class schedules even when Basic Computation is Leaves.';
        }

        return 'This category uses time logs, same as Staff. Basic hours count only when Basic Computation is Time In/Out.';
    }

    public function isInUse(): bool
    {
        return EmployeeEmploymentInformation::withTrashed()
            ->where('user_type', $this->code)
            ->exists();
    }

    public static function usesTeachingLoads(?string $code): bool
    {
        $code = strtolower(trim((string) $code));

        if ($code === '') {
            return false;
        }

        $category = self::findCached($code);

        if ($category !== null) {
            return $category->payroll_basis === self::BASIS_TEACHING_LOADS;
        }

        return $code === EmployeeEmploymentInformation::TYPE_FACULTY;
    }

    public static function labelFor(?string $code): string
    {
        $code = strtolower(trim((string) $code));

        if ($code === '') {
            return '';
        }

        return self::findCached($code)?->name
            ?? match ($code) {
                EmployeeEmploymentInformation::TYPE_FACULTY => 'Faculty',
                EmployeeEmploymentInformation::TYPE_STAFF => 'Staff',
                EmployeeEmploymentInformation::TYPE_ADMIN => 'Admin',
                default => $code,
            };
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForSelect(?string $includeCode = null): array
    {
        if (! self::tableReady()) {
            return self::fallbackOptions();
        }

        $rows = self::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($includeCode !== null && $includeCode !== '' && ! $rows->contains('code', $includeCode)) {
            $extra = self::query()->where('code', $includeCode)->first();

            if ($extra !== null) {
                $rows->push($extra);
            }
        }

        if ($rows->isEmpty()) {
            return self::fallbackOptions();
        }

        return $rows
            ->mapWithKeys(fn (EmployeeCategory $category) => [$category->code => $category->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function basisByCode(): array
    {
        if (! self::tableReady()) {
            return [
                EmployeeEmploymentInformation::TYPE_FACULTY => self::BASIS_TEACHING_LOADS,
                EmployeeEmploymentInformation::TYPE_STAFF => self::BASIS_LOGS,
                EmployeeEmploymentInformation::TYPE_ADMIN => self::BASIS_LOGS,
            ];
        }

        $map = self::query()->pluck('payroll_basis', 'code')->all();

        return $map !== [] ? $map : [
            EmployeeEmploymentInformation::TYPE_FACULTY => self::BASIS_TEACHING_LOADS,
            EmployeeEmploymentInformation::TYPE_STAFF => self::BASIS_LOGS,
            EmployeeEmploymentInformation::TYPE_ADMIN => self::BASIS_LOGS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function teachingLoadCodes(): array
    {
        return array_keys(array_filter(
            self::basisByCode(),
            fn (string $basis) => $basis === self::BASIS_TEACHING_LOADS,
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function knownCodes(): array
    {
        $codes = array_keys(self::basisByCode());

        return $codes !== [] ? $codes : [
            EmployeeEmploymentInformation::TYPE_FACULTY,
            EmployeeEmploymentInformation::TYPE_STAFF,
            EmployeeEmploymentInformation::TYPE_ADMIN,
        ];
    }

    public static function codeFromName(string $name): string
    {
        $base = substr(Str::slug($name), 0, 20);
        $base = $base !== '' ? $base : 'category';
        $code = $base;
        $suffix = 2;

        while (self::withTrashed()->where('code', $code)->exists()) {
            $tail = '-'.$suffix;
            $code = substr($base, 0, 20 - strlen($tail)).$tail;
            $suffix++;
        }

        return $code;
    }

    private static function findCached(string $code): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        if (self::$cache === null) {
            self::$cache = self::query()->get()->keyBy(fn (EmployeeCategory $category) => strtolower($category->code))->all();
        }

        return self::$cache[$code] ?? null;
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('tbl_employee_categories');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function fallbackOptions(): array
    {
        return [
            EmployeeEmploymentInformation::TYPE_FACULTY => 'Faculty',
            EmployeeEmploymentInformation::TYPE_STAFF => 'Staff',
            EmployeeEmploymentInformation::TYPE_ADMIN => 'Admin',
        ];
    }
}
