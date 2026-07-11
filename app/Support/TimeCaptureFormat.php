<?php

namespace App\Support;

use App\Models\TimeCaptureField;
use App\Models\TimeCaptureFormat as TimeCaptureFormatModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class TimeCaptureFormat
{
    public const LOG_TABLE = 'tbl_timecapture_formats';

    public const DEVICE_NAME_PATTERN = '/^[A-Za-z0-9_\-\s]+$/';

    public static function authorize(?User $user, string $permission): void
    {
        TimekeepingPolicy::authorize($user, $permission);
    }

    public static function routeName(string $action): string
    {
        return "timekeeping.time-capture-formats.$action";
    }

    public static function listQuery(): Builder
    {
        return TimeCaptureFormatModel::query()
            ->withCount('fields')
            ->orderBy('device_name');
    }

    public static function findOrFail(int $id): TimeCaptureFormatModel
    {
        return TimeCaptureFormatModel::query()->with('fields')->findOrFail($id);
    }

    public static function recordLabel(TimeCaptureFormatModel $record): string
    {
        return $record->device_name.' — '.$record->description;
    }

    public static function validationRules(?int $ignoreId = null): array
    {
        return [
            'device_name' => [
                'required',
                'string',
                'max:50',
                'regex:'.self::DEVICE_NAME_PATTERN,
                Rule::unique('tbl_timecapture_formats', 'device_name')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId, 'timecapture_format_id'),
            ],
            'description' => ['required', 'string', 'max:100'],
            'employee_id_type' => ['required', Rule::in(array_keys(config('time_capturing_settings.employee_id_types', [])))],
            'employee_id_column' => ['required', 'integer', 'min:1', 'max:99'],
            'date_type' => ['required', Rule::in(array_keys(config('time_capturing_settings.date_types', [])))],
            'date_column' => ['required', 'integer', 'min:1', 'max:99'],
            'reason_enabled' => ['nullable', 'boolean'],
            'reason_column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'time_in_type' => ['nullable', Rule::in(array_keys(config('time_capturing_settings.time_in_types', [])))],
            'time_in_column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'worktime_column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'time_out_enabled' => ['nullable', 'boolean'],
            'time_out_column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'same_column_indicator' => ['nullable', 'boolean'],
            'indicator_column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'time_in_identifier' => ['nullable', 'string', 'size:1'],
            'time_out_identifier' => ['nullable', 'string', 'size:1', 'different:time_in_identifier'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.timecapture_field_id' => ['nullable', 'integer'],
            'custom_fields.*.field_name' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'],
            'custom_fields.*.column' => ['nullable', 'integer', 'min:1', 'max:99'],
            'custom_fields.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, self::validationRules($ignoreId));

        $validator->after(function ($validator) use ($data) {
            self::validateMappingRules($validator, $data);
        });

        return $validator->validate();
    }

    private static function validateMappingRules($validator, array $data): void
    {
        $sameColumn = filter_var($data['same_column_indicator'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reasonEnabled = filter_var($data['reason_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $timeOutEnabled = filter_var($data['time_out_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($sameColumn) {
            if (blank($data['worktime_column'] ?? null)) {
                $validator->errors()->add('worktime_column', 'Time column is required when using biometric indicator mode.');
            }

            if (blank($data['indicator_column'] ?? null)) {
                $validator->errors()->add('indicator_column', 'Indicator column is required when using same column for time in/out.');
            }

            if (blank($data['time_in_identifier'] ?? null) || blank($data['time_out_identifier'] ?? null)) {
                $validator->errors()->add('time_in_identifier', 'Time in and time out identifiers are required when using same column.');
            }
        } else {
            if (blank($data['time_in_column'] ?? null)) {
                $validator->errors()->add('time_in_column', 'Time in column is required.');
            }

            if (blank($data['time_in_type'] ?? null)) {
                $validator->errors()->add('time_in_type', 'Time in type is required.');
            }

            if ($timeOutEnabled && blank($data['time_out_column'] ?? null)) {
                $validator->errors()->add('time_out_column', 'Time out column is required when time out is enabled.');
            }
        }

        if ($reasonEnabled && blank($data['reason_column'] ?? null)) {
            $validator->errors()->add('reason_column', 'Logout reason column is required when reason is enabled.');
        }

        $mapped = self::buildStandardMappings($data);
        $enabledCount = count($mapped);

        if ($enabledCount < 4) {
            $validator->errors()->add('mappings', 'At least four field mappings are required.');
        }

        $columns = array_column($mapped, 'column');
        if (count($columns) !== count(array_unique($columns))) {
            $validator->errors()->add('mappings', 'Column numbers must be unique across all mapped fields.');
        }

        foreach ($data['custom_fields'] ?? [] as $index => $field) {
            if (blank($field['field_name'] ?? null) && blank($field['column'] ?? null)) {
                continue;
            }

            if (blank($field['field_name'] ?? null)) {
                $validator->errors()->add("custom_fields.$index.field_name", 'Field name is required.');

                continue;
            }

            if (blank($field['column'] ?? null)) {
                $validator->errors()->add("custom_fields.$index.column", 'Column is required.');

                continue;
            }

            if (blank($field['description'] ?? null)) {
                $validator->errors()->add("custom_fields.$index.description", 'Description is required.');
            }

            $name = strtolower(str_replace(' ', '_', trim($field['field_name'])));

            if (in_array($name, config('time_capturing_settings.reserved_custom_field_names', []), true)) {
                $validator->errors()->add("custom_fields.$index.field_name", 'This field name is reserved for standard mappings.');
            }

            if (in_array((int) $field['column'], $columns, true)) {
                $validator->errors()->add("custom_fields.$index.column", 'Column number must be unique.');
            }

            $columns[] = (int) $field['column'];
        }
    }

    public static function headerPayload(array $validated): array
    {
        $sameColumn = filter_var($validated['same_column_indicator'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'device_name' => strtoupper(trim($validated['device_name'])),
            'description' => trim($validated['description']),
            'time_in_identifier' => $sameColumn ? strtoupper(trim($validated['time_in_identifier'] ?? '')) : null,
            'time_out_identifier' => $sameColumn ? strtoupper(trim($validated['time_out_identifier'] ?? '')) : null,
        ];
    }

    /**
     * @return array<int, array{field_name: string, column: int}>
     */
    public static function buildStandardMappings(array $validated): array
    {
        $sameColumn = filter_var($validated['same_column_indicator'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reasonEnabled = filter_var($validated['reason_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $timeOutEnabled = filter_var($validated['time_out_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $mappings = [
            [
                'field_name' => $validated['employee_id_type'],
                'column' => (int) $validated['employee_id_column'],
            ],
            [
                'field_name' => $validated['date_type'],
                'column' => (int) $validated['date_column'],
            ],
            [
                'field_name' => $sameColumn ? 'worktime' : $validated['time_in_type'],
                'column' => $sameColumn ? (int) $validated['worktime_column'] : (int) $validated['time_in_column'],
            ],
        ];

        if ($reasonEnabled) {
            $mappings[] = [
                'field_name' => 'reason',
                'column' => (int) $validated['reason_column'],
            ];
        }

        if ($sameColumn) {
            $mappings[] = [
                'field_name' => 'indicator',
                'column' => (int) $validated['indicator_column'],
            ];
        } elseif ($timeOutEnabled) {
            $mappings[] = [
                'field_name' => 'time_out',
                'column' => (int) $validated['time_out_column'],
            ];
        }

        return $mappings;
    }

    /**
     * @return array<int, array{field_name: string, column: int, description: string, timecapture_field_id?: int|null}>
     */
    public static function customFieldsPayload(array $validated): array
    {
        $rows = [];

        foreach ($validated['custom_fields'] ?? [] as $field) {
            if (blank($field['field_name'] ?? null) || blank($field['column'] ?? null)) {
                continue;
            }

            $rows[] = [
                'timecapture_field_id' => filled($field['timecapture_field_id'] ?? null) ? (int) $field['timecapture_field_id'] : null,
                'field_name' => strtolower(str_replace(' ', '_', trim($field['field_name']))),
                'column' => (int) $field['column'],
                'description' => trim($field['description'] ?? ''),
            ];
        }

        return $rows;
    }

    public static function syncFields(TimeCaptureFormatModel $format, array $validated): void
    {
        $standardMappings = self::buildStandardMappings($validated);
        $customRows = self::customFieldsPayload($validated);
        $keepIds = [];

        foreach ($standardMappings as $mapping) {
            $field = $format->fields()
                ->where('new_field', false)
                ->where('field_name', $mapping['field_name'])
                ->first();

            if ($field) {
                $field->update(['column' => $mapping['column']]);
                $keepIds[] = $field->timecapture_field_id;
            } else {
                $created = $format->fields()->create([
                    'field_name' => $mapping['field_name'],
                    'column' => $mapping['column'],
                    'new_field' => false,
                ]);
                $keepIds[] = $created->timecapture_field_id;
            }
        }

        $format->fields()->where('new_field', false)->whereNotIn('timecapture_field_id', $keepIds)->forceDelete();

        $customKeepIds = [];

        foreach ($customRows as $row) {
            $fieldId = $row['timecapture_field_id'] ?? null;
            unset($row['timecapture_field_id']);

            if ($fieldId) {
                $field = $format->fields()->where('new_field', true)->find($fieldId);

                if ($field) {
                    $field->update(array_merge($row, ['new_field' => true]));
                    $customKeepIds[] = $field->timecapture_field_id;

                    continue;
                }
            }

            $created = $format->fields()->create(array_merge($row, ['new_field' => true]));
            $customKeepIds[] = $created->timecapture_field_id;
        }

        $format->fields()->where('new_field', true)->whereNotIn('timecapture_field_id', $customKeepIds)->forceDelete();
    }

    public static function mappingStateForForm(?TimeCaptureFormatModel $format = null): array
    {
        $defaults = array_merge([
            'employee_id_type' => 'employee_number',
            'employee_id_column' => '',
            'date_type' => 'actual_date',
            'date_column' => '',
            'reason_enabled' => false,
            'reason_column' => '',
            'time_in_type' => 'time_in',
            'time_in_column' => '',
            'worktime_column' => '',
            'time_out_enabled' => true,
            'time_out_column' => '',
            'same_column_indicator' => false,
            'indicator_column' => '',
            'time_in_identifier' => '',
            'time_out_identifier' => '',
            'custom_fields' => [],
        ], config('time_capturing_settings.biometric_defaults', []), [
            'time_out_enabled' => false,
            'custom_fields' => [],
        ]);

        if (! $format) {
            return $defaults;
        }

        $state = $defaults;
        $byName = $format->fields->keyBy('field_name');

        if ($byName->has('employee_number')) {
            $state['employee_id_type'] = 'employee_number';
            $state['employee_id_column'] = $byName['employee_number']->column;
        } elseif ($byName->has('biometric_id')) {
            $state['employee_id_type'] = 'biometric_id';
            $state['employee_id_column'] = $byName['biometric_id']->column;
        }

        foreach (['actual_date', 'date_out', 'workdate'] as $dateType) {
            if ($byName->has($dateType)) {
                $state['date_type'] = $dateType;
                $state['date_column'] = $byName[$dateType]->column;

                break;
            }
        }

        if ($byName->has('reason')) {
            $state['reason_enabled'] = true;
            $state['reason_column'] = $byName['reason']->column;
        }

        if ($byName->has('indicator')) {
            $state['same_column_indicator'] = true;
            $state['indicator_column'] = $byName['indicator']->column;
            $state['time_out_enabled'] = false;
            $state['time_in_type'] = '';
            $state['time_in_column'] = '';
            $state['worktime_column'] = $byName->has('worktime') ? $byName['worktime']->column : '';
            $state['time_in_identifier'] = $format->time_in_identifier ?? '';
            $state['time_out_identifier'] = $format->time_out_identifier ?? '';
        } else {
            if ($byName->has('time_in')) {
                $state['time_in_type'] = 'time_in';
                $state['time_in_column'] = $byName['time_in']->column;
            } elseif ($byName->has('worktime')) {
                $state['time_in_type'] = 'worktime';
                $state['time_in_column'] = $byName['worktime']->column;
            }

            if ($byName->has('time_out')) {
                $state['time_out_enabled'] = true;
                $state['time_out_column'] = $byName['time_out']->column;
            } else {
                $state['time_out_enabled'] = false;
            }
        }

        $state['custom_fields'] = $format->customFields
            ->map(fn (TimeCaptureField $field) => [
                'timecapture_field_id' => $field->timecapture_field_id,
                'field_name' => $field->field_name,
                'column' => $field->column,
                'description' => $field->description,
            ])
            ->values()
            ->all();

        return $state;
    }

    public static function isInUse(TimeCaptureFormatModel $record): bool
    {
        if (Schema::hasTable('raw_timekeeping_transactions')
            && Schema::hasColumn('raw_timekeeping_transactions', 'timecapture_format_id')) {
            return DB::table('raw_timekeeping_transactions')
                ->where('timecapture_format_id', $record->timecapture_format_id)
                ->exists();
        }

        return false;
    }

    /**
     * Resolve standard field roles from the format's saved column mappings.
     *
     * @return array{
     *     date: string|null,
     *     employee: string|null,
     *     worktime: string|null,
     *     time_in: string|null,
     *     time_out: string|null,
     *     indicator: string|null,
     *     reason: string|null
     * }
     */
    public static function fieldRoles(TimeCaptureFormatModel $format): array
    {
        $format->loadMissing('fields');
        $byName = $format->fields->keyBy('field_name');

        $dateField = null;
        foreach (array_keys(config('time_capturing_settings.date_types', [])) as $dateType) {
            if ($byName->has($dateType)) {
                $dateField = $dateType;
                break;
            }
        }

        $employeeField = null;
        foreach (array_keys(config('time_capturing_settings.employee_id_types', [])) as $employeeType) {
            if ($byName->has($employeeType)) {
                $employeeField = $employeeType;
                break;
            }
        }

        return [
            'date' => $dateField,
            'employee' => $employeeField,
            'worktime' => $byName->has('worktime') ? 'worktime' : null,
            'time_in' => $byName->has('time_in') ? 'time_in' : null,
            'time_out' => $byName->has('time_out') ? 'time_out' : null,
            'indicator' => $byName->has('indicator') ? 'indicator' : null,
            'reason' => $byName->has('reason') ? 'reason' : null,
        ];
    }

    public static function requireFieldRole(TimeCaptureFormatModel $format, string $role): string
    {
        $field = self::fieldRoles($format)[$role] ?? null;

        if ($field === null || $field === '') {
            throw new RuntimeException("The selected format has no mapped {$role} field.");
        }

        return $field;
    }
}
