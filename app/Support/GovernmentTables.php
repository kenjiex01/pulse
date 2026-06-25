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
            'sss' => 'SSS',
            'wtax-classification' => 'Withholding Tax Classification',
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
                $payload[$fieldName] = filter_var($data[$fieldName] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null;
            } elseif ($fieldType === 'text' && $fieldName === 'withholding_tax_class_code') {
                $payload[$fieldName] = strtoupper($payload[$fieldName]);
            } elseif (($fieldType === 'number' || $fieldType === 'select') && ($payload[$fieldName] ?? '') === '') {
                $payload[$fieldName] = null;
            }
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
                'tax_amount' => $entry?->tax_amount ?? '',
                'tax_plus' => $entry?->tax_plus ?? '',
                'amount' => $entry?->amount ?? '',
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

            GovtTableWtax2023::query()->create([
                'withholding_tax_table_type_id' => $typeId,
                'column_id' => $column,
                'tax_amount' => $row['tax_amount'] ?? 0,
                'tax_plus' => $row['tax_plus'] ?? 0,
                'amount' => $row['amount'] ?? 0,
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
