<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Province;
use App\Models\Region;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class HrLookup
{
    public static function keys(): array
    {
        return array_keys(config('hr_lookups', []));
    }

    public static function config(string $lookup): array
    {
        $config = config("hr_lookups.$lookup");

        if (! $config) {
            abort(404);
        }

        return $config;
    }

    public static function routeName(string $lookup, string $action = 'index'): string
    {
        return "hr.$lookup.$action";
    }

    public static function fromRoute(?\Illuminate\Routing\Route $route = null): string
    {
        $route ??= request()->route();
        $name = $route?->getName() ?? '';

        if (preg_match('/^hr\.([^.]+)\./', $name, $matches)) {
            $lookup = $matches[1];

            if (in_array($lookup, self::keys(), true)) {
                return $lookup;
            }
        }

        abort(404);
    }

    public static function subModule(string $lookup): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::routeName($lookup))
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(User $user, string $lookup, string $permission): void
    {
        $subModule = self::subModule($lookup);

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

    public static function modelQuery(string $lookup)
    {
        $config = self::config($lookup);
        $query = $config['model']::query();

        foreach ($config['with'] ?? [] as $relation) {
            $query->with($relation);
        }

        foreach ($config['order'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    public static function findOrFail(string $lookup, int|string $id): Model
    {
        $config = self::config($lookup);

        return $config['model']::query()->findOrFail($id);
    }

    public static function validationRules(string $lookup, ?Model $record = null): array
    {
        $config = self::config($lookup);
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

    public static function validatedPayload(string $lookup, array $data): array
    {
        $config = self::config($lookup);
        $payload = Arr::only($data, collect($config['fields'])->pluck('name')->all());

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? 'text') === 'checkbox') {
                $payload[$field['name']] = filter_var($data[$field['name']] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $payload;
    }

    public static function selectOptions(string $source): array
    {
        return match ($source) {
            'campuses' => Campus::query()->where('is_active', true)->orderBy('campus_name')->pluck('campus_name', 'campus_id')->all(),
            'regions' => Region::query()->where('is_active', true)->orderBy('region_name')->pluck('region_name', 'region_id')->all(),
            'provinces' => Province::query()->with('region')->where('is_active', true)->orderBy('province_name')->get()
                ->mapWithKeys(fn ($province) => [$province->province_id => $province->province_name.' ('.($province->region?->region_name ?? '—').')'])
                ->all(),
            default => [],
        };
    }

    public static function columnValue(Model $record, string $key): mixed
    {
        return data_get($record, $key);
    }

    public static function recordLabel(Model $record, string $lookup): string
    {
        $config = self::config($lookup);
        $labelField = collect($config['columns'])->first()['key'] ?? 'id';

        if (str_contains($labelField, '.')) {
            $labelField = collect($config['fields'])->firstWhere('type', 'text')['name'] ?? $labelField;
        }

        return (string) data_get($record, $labelField, $record->getKey());
    }
}
