<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\EmployeeEmploymentInformation;
use App\Models\Position;
use Illuminate\Database\Eloquent\Model;

class EmployeeMaintenanceLookupService
{
    private bool $legacyNamesAdopted = false;

    /**
     * @param  array<int, array<string, mixed>>  $employments
     * @return array<int, array<string, mixed>>
     */
    public function ensureFromEmployment(array $employments): array
    {
        foreach ($employments as $index => $employment) {
            if (! is_array($employment)) {
                continue;
            }

            if (filled($employment['position'] ?? null)) {
                $employments[$index]['position'] = $this->ensurePosition((string) $employment['position']);
            }

            if (filled($employment['designation'] ?? null)) {
                $employments[$index]['designation'] = $this->ensureDesignation((string) $employment['designation']);
            }
        }

        return $employments;
    }

    public function adoptLegacyNames(): void
    {
        if ($this->legacyNamesAdopted) {
            return;
        }

        $this->legacyNamesAdopted = true;

        $this->adoptColumn('position', fn (string $name) => $this->ensurePosition($name, 'employee record'));
        $this->adoptColumn('designation', fn (string $name) => $this->ensureDesignation($name, 'employee record'));
    }

    public function ensurePosition(string $name, string $source = 'employee upload'): string
    {
        return $this->ensure(
            Position::class,
            'position_name',
            200,
            'tbl_positions',
            'position',
            $name,
            $source,
        );
    }

    public function ensureDesignation(string $name, string $source = 'employee upload'): string
    {
        return $this->ensure(
            Designation::class,
            'designation_name',
            100,
            'tbl_designations',
            'designation',
            $name,
            $source,
        );
    }

    private function adoptColumn(string $column, callable $ensure): void
    {
        $names = EmployeeEmploymentInformation::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->pluck($column);

        foreach ($names as $name) {
            $stored = (string) $name;
            $canonical = $ensure($stored);

            if ($canonical !== '' && $canonical !== $stored) {
                EmployeeEmploymentInformation::query()
                    ->where($column, $stored)
                    ->update([$column => $canonical]);
            }
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function ensure(
        string $modelClass,
        string $column,
        int $maxLength,
        string $table,
        string $label,
        string $name,
        string $source,
    ): string {
        $name = mb_substr(trim($name), 0, $maxLength);

        if ($name === '') {
            return '';
        }

        $existing = $modelClass::withTrashed()
            ->whereRaw('LOWER('.$column.') = ?', [mb_strtolower($name)])
            ->first();

        if ($existing instanceof Model) {
            $wasTrashed = method_exists($existing, 'trashed') && $existing->trashed();
            $wasInactive = ! (bool) $existing->getAttribute('is_active');

            if ($wasTrashed) {
                $existing->restore();
            }

            if ($wasInactive) {
                $existing->setAttribute('is_active', true);
                $existing->save();
            }

            if ($wasTrashed || $wasInactive) {
                SysLogService::record(
                    action: 'update',
                    table: $table,
                    recordId: (int) $existing->getKey(),
                    newValues: $existing->fresh()?->toArray(),
                    description: 'Reactivated '.$label.' from '.$source.': '.$existing->getAttribute($column),
                );
            }

            return (string) $existing->getAttribute($column);
        }

        $created = $modelClass::query()->create([
            $column => $name,
            'is_active' => true,
        ]);

        SysLogService::record(
            action: 'create',
            table: $table,
            recordId: (int) $created->getKey(),
            newValues: $created->toArray(),
            description: 'Created '.$label.' from '.$source.': '.$name,
        );

        return $name;
    }
}
