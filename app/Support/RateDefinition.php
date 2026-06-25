<?php

namespace App\Support;

use App\Models\ComputationBasis;
use App\Models\DayType;
use App\Models\IncomeType;
use App\Models\LuDay;
use App\Models\NdRateGroup;
use App\Models\NdRateGroupDayType;
use App\Models\RateBasis;
use App\Models\RateGroup;
use App\Models\RateGroupDayType;
use App\Models\SubModule;
use App\Models\TimeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class RateDefinition
{
    public const SUB_MODULE_ROUTE = 'payroll.rate-definitions.index';

    public static function keys(): array
    {
        return array_keys(config('rate_definition', []));
    }

    public static function config(string $tab): array
    {
        $config = config("rate_definition.$tab");

        if (! $config) {
            abort(404);
        }

        return $config;
    }

    public static function tabs(): array
    {
        return [
            'rate-groups' => 'Rate Groups',
            'nd-rate-groups' => 'Night Diff. Rate Groups',
            'day-types' => 'Day Types',
        ];
    }

    public static function defaultTab(): string
    {
        return 'rate-groups';
    }

    public static function resolveTab(?string $tab): string
    {
        $tab = $tab ?: self::defaultTab();

        if (! in_array($tab, self::keys(), true)) {
            abort(404);
        }

        return $tab;
    }

    public static function routeName(string $action = 'tab'): string
    {
        if (in_array($action, ['index', 'tab'], true)) {
            return 'payroll.rate-definitions.tab';
        }

        return "payroll.rate-definitions.$action";
    }

    public static function entryRouteName(): string
    {
        return 'payroll.rate-definitions.index';
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(User $user, string $permission): void
    {
        $subModule = self::subModule();

        if (! $subModule) {
            if (! $user->isAdmin()) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if ($permission === 'view') {
            if (! $user->hasSubModuleAccess($subModule)) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if (! $user->hasSubModulePermission($subModule, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    public static function selectOptions(): array
    {
        return [
            'days' => LuDay::query()
                ->orderBy('day_id')
                ->pluck('day', 'day_id')
                ->all(),
            'rate_basis' => RateBasis::query()
                ->orderBy('rate_basis_id')
                ->pluck('rate_basis', 'rate_basis_id')
                ->all(),
            'computation_basis' => ComputationBasis::query()
                ->orderBy('computation_basis_id')
                ->pluck('description', 'computation_basis_id')
                ->all(),
            'income_types' => IncomeType::query()
                ->where('is_active', true)
                ->orderBy('description')
                ->pluck('description', 'income_type_id')
                ->all(),
        ];
    }

    public static function modelQuery(string $tab)
    {
        $config = self::config($tab);
        $query = $config['model']::query();

        if (! empty($config['with'])) {
            $query->with($config['with']);
        }

        foreach ($config['order'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    public static function findOrFail(string $tab, int|string $id): Model
    {
        $config = self::config($tab);

        return $config['model']::query()->findOrFail($id);
    }

    public static function validationRules(string $tab, ?Model $record = null): array
    {
        $config = self::config($tab);
        $primaryKey = $config['primary_key'];
        $table = (new $config['model'])->getTable();
        $rules = [];

        foreach ($config['fields'] as $field) {
            $fieldRules = $field['rules'] ?? [];

            if (! empty($field['unique'])) {
                $fieldRules[] = Rule::unique($table, $field['name'])
                    ->whereNull('deleted_at')
                    ->ignore($record?->getKey(), $primaryKey);
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    public static function validatedDayTypePayload(array $data, ?DayType $record = null): array
    {
        $payload = [
            'description' => $data['description'],
            'is_restday' => filter_var($data['is_restday'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'is_special_holiday' => filter_var($data['is_special_holiday'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'is_legal_holiday' => filter_var($data['is_legal_holiday'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'day_id' => filled($data['day_id'] ?? null) ? (int) $data['day_id'] : null,
        ];

        if (! $record?->isInUse()) {
            $payload['day_type_code'] = strtoupper($data['day_type_code']);
        }

        return $payload;
    }

    public static function columnValue(Model $record, string $key, ?string $type = null): mixed
    {
        $value = data_get($record, $key);

        return match ($type) {
            'check' => $value ? 'Yes' : '—',
            default => $value,
        };
    }

    public static function recordLabel(Model $record, string $tab): string
    {
        $config = self::config($tab);
        $labelField = collect($config['columns'])->firstWhere('key', 'description')['key']
            ?? collect($config['columns'])->first()['key']
            ?? 'id';

        return (string) data_get($record, $labelField, $record->getKey());
    }

    public static function rateGroupHeaderRules(?RateGroup $record = null): array
    {
        return [
            'rate_group_code' => [
                'required', 'string', 'min:1', 'max:4',
                Rule::unique('tbl_rate_groups', 'rate_group_code')
                    ->whereNull('deleted_at')
                    ->ignore($record?->rate_group_id, 'rate_group_id'),
            ],
            'description' => ['required', 'string', 'min:1', 'max:45'],
            'rate_basis_id' => ['required', 'integer', 'exists:lu_rate_basis,rate_basis_id'],
            'rates' => ['nullable', 'array'],
        ];
    }

    public static function ndRateGroupHeaderRules(?NdRateGroup $record = null): array
    {
        return [
            'nd_rate_group_code' => [
                'required', 'string', 'min:1', 'max:4',
                Rule::unique('tbl_nd_rate_groups', 'nd_rate_group_code')
                    ->whereNull('deleted_at')
                    ->ignore($record?->nd_rate_group_id, 'nd_rate_group_id'),
            ],
            'description' => ['required', 'string', 'min:1', 'max:45'],
            'rate_basis_id' => ['required', 'integer', 'exists:lu_rate_basis,rate_basis_id'],
            'tm_start' => ['required', 'date_format:H:i'],
            'tm_end' => ['required', 'date_format:H:i'],
            'rates' => ['nullable', 'array'],
        ];
    }

    public static function syncRateGroupDayTypes(RateGroup $rateGroup, array $rates, int $timeClassId): void
    {
        $rateGroup->dayTypeRates()->forceDelete();
        self::insertDayTypeRates(
            $rates,
            (int) $rateGroup->rate_basis_id,
            $timeClassId,
            fn (array $row) => RateGroupDayType::query()->create(array_merge($row, [
                'rate_group_id' => $rateGroup->rate_group_id,
            ])),
        );
    }

    public static function syncNdRateGroupDayTypes(NdRateGroup $ndRateGroup, array $rates, int $timeClassId): void
    {
        $ndRateGroup->dayTypeRates()->forceDelete();
        self::insertDayTypeRates(
            $rates,
            (int) $ndRateGroup->rate_basis_id,
            $timeClassId,
            fn (array $row) => NdRateGroupDayType::query()->create(array_merge($row, [
                'nd_rate_group_id' => $ndRateGroup->nd_rate_group_id,
            ])),
        );
    }

    private static function insertDayTypeRates(array $rates, int $rateBasisId, int $timeClassId, callable $create): void
    {
        $timeTypeIds = TimeType::query()
            ->where('time_class_id', $timeClassId)
            ->pluck('time_type_id')
            ->all();

        foreach ($rates as $dayTypeId => $timeRows) {
            if (! is_array($timeRows)) {
                continue;
            }

            foreach ($timeRows as $timeTypeId => $row) {
                if (! in_array((int) $timeTypeId, $timeTypeIds, true)) {
                    continue;
                }

                if (! is_array($row)) {
                    continue;
                }

                $computationBasisId = $row['computation_basis_id'] ?? null;
                $incomeTypeId = $row['income_type_id'] ?? null;
                $rate = $row['rate'] ?? null;
                $isTaxable = $row['is_taxable'] ?? null;

                if (! filled($computationBasisId) && ! filled($incomeTypeId) && ! filled($rate)) {
                    continue;
                }

                if (! filled($incomeTypeId) || ! filled($rate)) {
                    continue;
                }

                if ($rateBasisId !== RateBasis::FIXED_AMOUNT_PER_HOUR && ! filled($computationBasisId)) {
                    continue;
                }

                $create([
                    'day_type_id' => (int) $dayTypeId,
                    'time_type_id' => (int) $timeTypeId,
                    'computation_basis_id' => filled($computationBasisId) ? (int) $computationBasisId : null,
                    'income_type_id' => (int) $incomeTypeId,
                    'rate' => $rate,
                    'is_taxable' => filter_var($isTaxable ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    public static function existingRatesMap(Model $group, int $timeClassId): array
    {
        $map = [];

        foreach ($group->dayTypeRates as $entry) {
            if ((int) $entry->timeType?->time_class_id !== $timeClassId) {
                continue;
            }

            $map[$entry->day_type_id.'_'.$entry->time_type_id] = $entry;
        }

        return $map;
    }

    public static function incomeTaxOptions(?int $incomeTypeId): array
    {
        if (! $incomeTypeId) {
            return [];
        }

        $incomeType = IncomeType::query()->find($incomeTypeId);

        if (! $incomeType) {
            return [];
        }

        if (! $incomeType->is_non_taxable) {
            return [1 => 'Taxable'];
        }

        return [1 => 'Taxable', 0 => 'Non-Taxable'];
    }

    public static function formContextData(int $timeClassId): array
    {
        return [
            'dayTypes' => DayType::query()->orderBy('description')->get(),
            'timeTypes' => TimeType::query()->where('time_class_id', $timeClassId)->orderBy('description')->get(),
            'selectOptions' => self::selectOptions(),
        ];
    }
}
