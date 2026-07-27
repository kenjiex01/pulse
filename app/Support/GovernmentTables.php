<?php

namespace App\Support;

use App\Models\GovtTableWtax2023;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class GovernmentTables
{
    public const SUB_MODULE_ROUTE = 'payroll.government-tables.index';

    public const WTAX2023_FREQUENCIES = [
        'daily' => ['label' => 'Daily', 'type_id' => GovtTableWtax2023::DAILY],
        'weekly' => ['label' => 'Weekly', 'type_id' => GovtTableWtax2023::WEEKLY],
        'semi-monthly' => ['label' => 'Semi-Monthly', 'type_id' => GovtTableWtax2023::SEMI_MONTHLY],
        'monthly' => ['label' => 'Monthly', 'type_id' => GovtTableWtax2023::MONTHLY],
        'annual' => ['label' => 'Annual', 'type_id' => null],
    ];

    public static function keys(): array
    {
        return array_keys(config('government_tables', []));
    }

    public static function crudTabs(): array
    {
        return array_values(array_filter(self::keys(), fn (string $tab) => (self::config($tab)['type'] ?? null) !== 'wtax2023'));
    }

    public static function storeTabs(): array
    {
        return array_values(array_filter(self::crudTabs(), fn (string $tab) => self::config($tab)['allow_create'] ?? true));
    }

    public static function destroyTabs(): array
    {
        return array_values(array_filter(self::crudTabs(), fn (string $tab) => self::config($tab)['allow_delete'] ?? true));
    }

    public static function allowsCreate(string $tab): bool
    {
        return in_array($tab, self::storeTabs(), true);
    }

    public static function allowsDelete(string $tab): bool
    {
        return in_array($tab, self::destroyTabs(), true);
    }

    public static function config(string $tab): array
    {
        $config = config("government_tables.$tab");

        if (! $config) {
            abort(404);
        }

        return $config;
    }

    public static function tabs(): array
    {
        return [
            'pag-ibig' => 'Pag-IBIG',
            'philhealth' => 'PhilHealth',
            'philhealth-minimum' => 'Philhealth Minimum',
            'sss' => 'SSS',
            'withholding-tax-2023' => 'Withholding Tax',
        ];
    }

    public static function defaultTab(): string
    {
        return 'pag-ibig';
    }

    public static function resolveTab(?string $tab): string
    {
        $tab = $tab ?: self::defaultTab();

        if (! in_array($tab, self::keys(), true)) {
            abort(404);
        }

        return $tab;
    }

    public static function resolveWtax2023Frequency(?string $frequency): string
    {
        $frequency = $frequency ?: 'daily';

        if (! array_key_exists($frequency, self::WTAX2023_FREQUENCIES)) {
            abort(404);
        }

        return $frequency;
    }

    public static function routeName(string $action = 'tab'): string
    {
        if (in_array($action, ['index', 'tab'], true)) {
            return 'payroll.government-tables.tab';
        }

        return "payroll.government-tables.$action";
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

    public static function modelQuery(string $tab)
    {
        $config = self::config($tab);
        $query = $config['model']::query();

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

        if ($tab === 'philhealth') {
            $rules['salary_to'][] = 'gte:salary_from';
            $rules['percentage'] = [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(fn () => filter_var(request()->input('is_percent'), FILTER_VALIDATE_BOOLEAN)),
            ];
        }

        if ($tab === 'sss') {
            $rules['compensation_to'][] = 'gte:compensation_from';
        }

        return $rules;
    }

    public static function validatedPayload(string $tab, array $data): array
    {
        $config = self::config($tab);
        $payload = Arr::only($data, collect($config['fields'])->pluck('name')->all());

        foreach ($config['fields'] as $field) {
            $fieldName = $field['name'];
            $fieldType = $field['type'] ?? 'text';

            if ($fieldType === 'checkbox') {
                $payload[$fieldName] = filter_var($data[$fieldName] ?? false, FILTER_VALIDATE_BOOLEAN);
            } elseif (($fieldType === 'number' || $fieldType === 'select') && ($payload[$fieldName] ?? '') === '') {
                $payload[$fieldName] = null;
            }
        }

        if (($config['log_table'] ?? null) === 'tbl_govt_table_philhealth') {
            $payload['is_percent'] = (bool) ($payload['is_percent'] ?? false);
            $payload['is_active'] = array_key_exists('is_active', $data)
                ? (bool) ($payload['is_active'] ?? false)
                : true;
            $payload['percentage'] = $payload['is_percent']
                ? (float) ($payload['percentage'] ?? 0)
                : 0.0;
        }

        return $payload;
    }

    public static function columnValue(Model $record, array $column): mixed
    {
        $type = $column['type'] ?? null;
        $key = $column['key'];

        if ($type === 'computed_sum') {
            $total = 0;
            foreach ($column['operands'] ?? [] as $operand) {
                $total += (float) data_get($record, $operand, 0);
            }

            return number_format($total, 2);
        }

        $value = data_get($record, $key);

        return match ($type) {
            'decimal' => $value === null ? '—' : number_format((float) $value, 2),
            'check' => $value ? 'Yes' : '—',
            'yes_no' => $value ? 'Yes' : 'No',
            default => $value,
        };
    }

    public static function recordLabel(Model $record, string $tab): string
    {
        $config = self::config($tab);
        $labelField = collect($config['columns'] ?? [])->firstWhere('key', 'description')['key']
            ?? collect($config['columns'] ?? [])->first()['key']
            ?? 'id';

        return (string) data_get($record, $labelField, $record->getKey());
    }

    public static function roundWtaxValue(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Keep BIR table precision (e.g. 10,416.67) — do not round to whole pesos.
        return round((float) $value, 2);
    }

    public static function formatWtaxGridValue(float|int|string|null $value): string
    {
        return number_format(self::roundWtaxValue($value), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{tax_amount: float, tax_plus: float, amount: float}
     */
    public static function normalizeWtaxGridColumn(array $row): array
    {
        return [
            'tax_amount' => self::roundWtaxValue($row['tax_amount'] ?? 0),
            'tax_plus' => self::roundWtaxValue($row['tax_plus'] ?? 0),
            'amount' => self::roundWtaxValue($row['amount'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, float>
     */
    public static function normalizeAnnualWtaxAttributes(array $attributes): array
    {
        return [
            'income_from' => min(self::roundWtaxValue($attributes['income_from'] ?? 0), 99_999_999.99),
            'income_to' => min(self::roundWtaxValue($attributes['income_to'] ?? 0), 99_999_999.99),
            'amount_due' => self::roundWtaxValue($attributes['amount_due'] ?? 0),
            'percentage_due' => self::roundWtaxValue($attributes['percentage_due'] ?? 0),
        ];
    }

    public static function wtax2023Grid(int $typeId): array
    {
        $rows = GovtTableWtax2023::query()
            ->where('withholding_tax_table_type_id', $typeId)
            ->orderBy('column_id')
            ->get()
            ->keyBy('column_id');

        $grid = [];
        for ($column = 1; $column <= GovtTableWtax2023::COLUMN_COUNT; $column++) {
            $entry = $rows->get($column);
            $grid[$column] = [
                'tax_amount' => self::formatWtaxGridValue($entry?->tax_amount ?? 0),
                'tax_plus' => self::formatWtaxGridValue($entry?->tax_plus ?? 0),
                'amount' => self::formatWtaxGridValue($entry?->amount ?? 0),
            ];
        }

        return $grid;
    }

    public static function syncWtax2023Grid(int $typeId, array $columns): void
    {
        GovtTableWtax2023::query()->where('withholding_tax_table_type_id', $typeId)->forceDelete();

        for ($column = 1; $column <= GovtTableWtax2023::COLUMN_COUNT; $column++) {
            $row = $columns[$column] ?? $columns[(string) $column] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $normalized = self::normalizeWtaxGridColumn($row);

            GovtTableWtax2023::query()->create([
                'withholding_tax_table_type_id' => $typeId,
                'column_id' => $column,
                'tax_amount' => $normalized['tax_amount'],
                'tax_plus' => $normalized['tax_plus'],
                'amount' => $normalized['amount'],
            ]);
        }
    }

    public static function annualValidationRules(?Model $record = null): array
    {
        return [
            'income_from' => ['required', 'numeric', 'min:0'],
            'income_to' => ['required', 'numeric', 'min:0', 'gte:income_from'],
            'amount_due' => ['required', 'numeric', 'min:0'],
            'percentage_due' => ['required', 'numeric', 'min:0'],
        ];
    }
}
