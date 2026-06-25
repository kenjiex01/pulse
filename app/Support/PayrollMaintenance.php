<?php

namespace App\Support;

use App\Models\ComputationBasis;
use App\Models\GovtTable;
use App\Models\IncomeClass;
use App\Models\LateUndertimeLeave;
use App\Models\LeaveApplyTo;
use App\Models\LoanClass;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PayrollMaintenance
{
    public const SUB_MODULE_ROUTE = 'payroll.maintenance-table.index';

    public static function keys(): array
    {
        return array_keys(config('payroll_maintenance', []));
    }

    public static function config(string $tab): array
    {
        $config = config("payroll_maintenance.$tab");

        if (! $config) {
            abort(404);
        }

        return $config;
    }

    public static function tabs(): array
    {
        return [
            'income-types' => 'Income Type',
            'deduction-types' => 'Deduction Type',
            'loan-types' => 'Loan Type',
            'leave-types' => 'Leave Type',
        ];
    }

    public static function defaultTab(): string
    {
        return 'income-types';
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
            return 'payroll.maintenance-table.tab';
        }

        return "payroll.maintenance-table.$action";
    }

    public static function entryRouteName(): string
    {
        return 'payroll.maintenance-table.index';
    }

    public static function tabFromRoute(?\Illuminate\Routing\Route $route = null): string
    {
        $route ??= request()->route();
        $tab = $route?->parameter('tab');

        return self::resolveTab(is_string($tab) ? $tab : null);
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
            'income_classes' => IncomeClass::query()
                ->orderBy('income_class_id')
                ->pluck('income_class', 'income_class_id')
                ->all(),
            'loan_classes' => LoanClass::query()
                ->orderBy('loan_class_id')
                ->pluck('loan_class', 'loan_class_id')
                ->all(),
            'govt_tables' => GovtTable::query()
                ->orderBy('order_by')
                ->get()
                ->mapWithKeys(fn (GovtTable $table) => [$table->govt_table_id => $table->description])
                ->all(),
            'computation_basis' => ComputationBasis::query()
                ->orderBy('computation_basis_id')
                ->pluck('description', 'computation_basis_id')
                ->all(),
            'leave_apply_to' => LeaveApplyTo::query()
                ->orderBy('leave_apply_to_id')
                ->pluck('name', 'leave_apply_to_id')
                ->all(),
            'late_undertime_leaves' => LateUndertimeLeave::query()
                ->orderBy('late_undertime_leave_id')
                ->pluck('late_undertime_leave_type', 'late_undertime_leave_id')
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

        if ($tab === 'deduction-types') {
            $rules['govt_table_id'][] = Rule::requiredIf(fn () => filter_var(request()->input('is_valid_govt_deduction'), FILTER_VALIDATE_BOOLEAN));
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
            } elseif ($fieldType === 'select' && ($payload[$fieldName] ?? '') === '') {
                $payload[$fieldName] = null;
            }
        }

        if ($tab === 'deduction-types' && ! ($payload['is_valid_govt_deduction'] ?? false)) {
            $payload['govt_table_id'] = null;
        }

        if ($tab === 'loan-types' && (int) ($payload['loan_class_id'] ?? 0) !== 1) {
            $payload['sss_loan_type'] = null;
        }

        if ($tab === 'leave-types' && ! ($payload['is_convertible_to_cash'] ?? false)) {
            $payload['hours_non_taxable'] = null;
        }

        return $payload;
    }

    public static function columnValue(Model $record, string $key, ?string $type = null): mixed
    {
        $value = data_get($record, $key);

        return match ($type) {
            'yes_no' => $value === null ? '—' : ($value ? 'Yes' : 'No'),
            'decimal' => $value === null ? '—' : number_format((float) $value, 2),
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

    public static function protectedCodeField(string $tab): ?string
    {
        return match ($tab) {
            'income-types' => 'income_type_code',
            'leave-types' => 'leave_type_code',
            default => null,
        };
    }

    public static function isProtectedRecord(Model $record, string $tab): bool
    {
        $codeField = self::protectedCodeField($tab);

        if ($codeField === null) {
            return false;
        }

        $protectedCodes = self::config($tab)['protected_codes'] ?? [];

        return in_array((string) $record->{$codeField}, $protectedCodes, true);
    }

    public static function protectedRecordErrorMessage(string $tab): string
    {
        $name = self::config($tab)['name'] ?? 'Record';

        return "System default {$name} records cannot be edited, deactivated, or deleted.";
    }
}
