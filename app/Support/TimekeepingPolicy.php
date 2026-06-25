<?php

namespace App\Support;

use App\Models\LeaveType;
use App\Models\LuExcessHour;
use App\Models\LuNonRegularOt;
use App\Models\LuRounding;
use App\Models\LeaveProcessingMode;
use App\Models\SubModule;
use App\Models\TimekeepingPolicy as TimekeepingPolicyModel;
use App\Models\TimekeepingPolicyDayCode;
use App\Models\TimekeepingPolicyTeamSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimekeepingPolicy
{
    public const SUB_MODULE_ROUTE = 'timekeeping.policy.index';

    public const EXCESS_HOUR_DISREGARD = 1;

    public static function moduleTabs(): array
    {
        return collect(config('timekeeping_policy.module_tabs', []))
            ->mapWithKeys(fn (array|string $tab, string $key) => [
                $key => is_array($tab) ? ($tab['label'] ?? $key) : $tab,
            ])
            ->all();
    }

    public static function moduleTabConfig(string $tab): array
    {
        $config = config("timekeeping_policy.module_tabs.$tab");

        if (! is_array($config)) {
            abort(404);
        }

        return $config;
    }

    public static function tabs(): array
    {
        return self::moduleTabs();
    }

    public static function settingsTabs(): array
    {
        return config('timekeeping_policy.settings_tabs', []);
    }

    public static function defaultTab(): string
    {
        return 'policy';
    }

    public static function defaultSettingsTab(): string
    {
        return array_key_first(self::settingsTabs()) ?: 'tardiness-undertime';
    }

    public static function resolveModuleTab(?string $tab): string
    {
        $tab = $tab ?: self::defaultTab();

        if (! array_key_exists($tab, self::moduleTabs())) {
            abort(404);
        }

        return $tab;
    }

    public static function resolveTab(?string $tab): string
    {
        return self::resolveModuleTab($tab);
    }

    public static function resolveSettingsTab(?string $tab): string
    {
        $tab = $tab ?: self::defaultSettingsTab();

        if (! array_key_exists($tab, self::settingsTabs())) {
            abort(404);
        }

        return $tab;
    }

    public static function equivalentKeys(): array
    {
        return array_keys(config('timekeeping_policy.equivalents', []));
    }

    public static function equivalentConfig(string $type): array
    {
        $config = config("timekeeping_policy.equivalents.$type");

        if (! $config) {
            abort(404);
        }

        return $config;
    }

    public static function equivalentsForTab(string $tab): array
    {
        return collect(config('timekeeping_policy.equivalents', []))
            ->filter(fn (array $config) => ($config['tab'] ?? null) === $tab)
            ->all();
    }

    public static function routeName(string $action = 'index'): string
    {
        return "timekeeping.policy.$action";
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

    public static function findPolicyOrFail(int $policyId): TimekeepingPolicyModel
    {
        return TimekeepingPolicyModel::query()->findOrFail($policyId);
    }

    public static function policyListQuery(): Builder
    {
        return TimekeepingPolicyModel::query()
            ->orderBy('policy_name');
    }

    public static function policyHeaderRules(?TimekeepingPolicyModel $policy = null): array
    {
        return [
            'policy_code' => [
                'required',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('tbl_timekeeping_policies', 'policy_code')->ignore($policy?->timekeeping_policy_id, 'timekeeping_policy_id'),
            ],
            'policy_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:250'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public static function validatePolicyHeader(array $data, ?TimekeepingPolicyModel $policy = null): array
    {
        return Validator::make($data, self::policyHeaderRules($policy))->validate();
    }

    public static function policyHeaderPayload(array $validated): array
    {
        return [
            'policy_code' => strtoupper($validated['policy_code']),
            'policy_name' => $validated['policy_name'],
            'description' => filled($validated['description'] ?? null) ? $validated['description'] : null,
            'is_active' => filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function policyLabel(TimekeepingPolicyModel $policy): string
    {
        return $policy->policy_name.' ('.$policy->policy_code.')';
    }

    public static function defaultSettingsAttributes(): array
    {
        return [
            'leave_processing_mode' => 1,
            'validity_of_late_file' => 30,
            'is_ot_form_required' => 0,
            'excess_hour_id' => self::EXCESS_HOUR_DISREGARD,
            'is_allow_flexi_time' => false,
            'break_computation' => 1,
            'enable_attendance_approval' => true,
            'non_regular_hours_computation_basis' => 2,
            'buffer_time_in' => 4,
            'buffer_time_out' => 10,
            'enable_employee_validation_for_rest_days' => true,
            'max_rest_days_per_week' => 2,
            'min_hours_rendered_per_week' => 40,
        ];
    }

    public static function createPolicyWithDefaults(array $headerPayload): TimekeepingPolicyModel
    {
        $policy = TimekeepingPolicyModel::query()->create(array_merge(
            self::defaultSettingsAttributes(),
            $headerPayload,
        ));

        self::createDefaultDayCodes($policy);

        return $policy;
    }

    public static function createDefaultDayCodes(TimekeepingPolicyModel $policy): TimekeepingPolicyDayCode
    {
        return TimekeepingPolicyDayCode::query()->create([
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
            'sunday' => 'U',
            'monday' => 'M',
            'tuesday' => 'T',
            'wednesday' => 'W',
            'thursday' => 'H',
            'friday' => 'F',
            'saturday' => 'A',
        ]);
    }

    public static function dayCodes(TimekeepingPolicyModel $policy): TimekeepingPolicyDayCode
    {
        return $policy->dayCodes()->firstOrCreate(
            ['timekeeping_policy_id' => $policy->timekeeping_policy_id],
            [
                'sunday' => 'U',
                'monday' => 'M',
                'tuesday' => 'T',
                'wednesday' => 'W',
                'thursday' => 'H',
                'friday' => 'F',
                'saturday' => 'A',
            ],
        );
    }

    public static function selectOptions(): array
    {
        return [
            'leave_types' => LeaveType::query()
                ->orderBy('description')
                ->pluck('description', 'leave_type_id')
                ->all(),
            'rounding' => LuRounding::query()
                ->orderBy('rounding_id')
                ->pluck('rounding', 'rounding_id')
                ->all(),
            'excess_hours' => LuExcessHour::query()
                ->orderBy('excess_hour_id')
                ->pluck('excess_hour', 'excess_hour_id')
                ->all(),
            'non_regular_ot' => LuNonRegularOt::query()
                ->orderBy('non_regular_ot_id')
                ->pluck('description', 'non_regular_ot_id')
                ->all(),
            'leave_processing_modes' => LeaveProcessingMode::query()
                ->orderBy('leave_processing_mode_id')
                ->pluck('mode_label', 'leave_processing_mode_id')
                ->all(),
            'non_regular_hours_bases' => config('timekeeping_policy.non_regular_hours_bases', []),
            'team_settings' => TimekeepingPolicyTeamSetting::query()
                ->orderBy('limit')
                ->pluck('description', 'timekeeping_policy_team_setting_id')
                ->all(),
        ];
    }

    public static function reservedLeaveTypeIds(TimekeepingPolicyModel $policy): array
    {
        return array_values(array_filter([
            $policy->tardiness_leave_type_id,
            $policy->undertime_leave_type_id,
            $policy->break_tardiness_leave_type_id,
            $policy->awol_leave_type_id,
        ]));
    }

    public static function availableLeaveTypesForEquivalents(TimekeepingPolicyModel $policy): array
    {
        $reserved = self::reservedLeaveTypeIds($policy);

        return LeaveType::query()
            ->when($reserved !== [], fn ($query) => $query->whereNotIn('leave_type_id', $reserved))
            ->orderBy('description')
            ->pluck('description', 'leave_type_id')
            ->all();
    }

    public static function modelQuery(string $type, TimekeepingPolicyModel $policy)
    {
        $config = self::equivalentConfig($type);
        $query = $config['model']::query()
            ->where('timekeeping_policy_id', $policy->timekeeping_policy_id);

        foreach ($config['order'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    public static function findEquivalentOrFail(string $type, int|string $id, TimekeepingPolicyModel $policy): Model
    {
        $config = self::equivalentConfig($type);

        return $config['model']::query()
            ->where('timekeeping_policy_id', $policy->timekeeping_policy_id)
            ->findOrFail($id);
    }

    public static function equivalentValidationRules(string $type, ?Model $record = null): array
    {
        $config = self::equivalentConfig($type);
        $rules = [
            'time_from' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'time_to' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'equivalent' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
        ];

        if ($config['overlap_check'] ?? false) {
            $rules['time_to'][] = 'gte:time_from';
        }

        if ($config['requires_leave_type'] ?? false) {
            $rules['leave_type_id'] = ['required', 'integer', Rule::exists('tbl_leave_types', 'leave_type_id')];
        }

        return $rules;
    }

    public static function validateEquivalent(string $type, array $data, TimekeepingPolicyModel $policy, ?Model $record = null): array
    {
        $config = self::equivalentConfig($type);
        $validated = Validator::make($data, self::equivalentValidationRules($type, $record))->validate();

        if ($config['requires_leave_type'] ?? false) {
            $reserved = self::reservedLeaveTypeIds($policy);
            if (in_array((int) $validated['leave_type_id'], $reserved, true)) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'This leave type is already used in policy settings.',
                ]);
            }
        }

        if ($config['overlap_check'] ?? false) {
            self::assertNoOverlap($type, $policy, (float) $validated['time_from'], (float) $validated['time_to'], $record, $validated['leave_type_id'] ?? null);
        }

        return $validated;
    }

    public static function assertNoOverlap(string $type, TimekeepingPolicyModel $policy, float $from, float $to, ?Model $record = null, ?int $leaveTypeId = null): void
    {
        $config = self::equivalentConfig($type);
        $primaryKey = $config['primary_key'];

        $query = $config['model']::query()
            ->where('timekeeping_policy_id', $policy->timekeeping_policy_id)
            ->where(function ($overlapQuery) use ($from, $to) {
                $overlapQuery
                    ->where(function ($inner) use ($from) {
                        $inner->where('time_from', '<=', $from)->where('time_to', '>=', $from);
                    })
                    ->orWhere(function ($inner) use ($to) {
                        $inner->where('time_from', '<=', $to)->where('time_to', '>=', $to);
                    });
            });

        if (($config['requires_leave_type'] ?? false) && $leaveTypeId !== null) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        if ($record) {
            $query->where($primaryKey, '!=', $record->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'time_from' => 'The time range overlaps an existing equivalent.',
            ]);
        }
    }

    public static function equivalentPayload(string $type, array $validated, TimekeepingPolicyModel $policy): array
    {
        $config = self::equivalentConfig($type);
        $fields = ['time_from', 'time_to', 'equivalent'];

        if ($config['requires_leave_type'] ?? false) {
            $fields[] = 'leave_type_id';
        }

        return array_merge(
            ['timekeeping_policy_id' => $policy->timekeeping_policy_id],
            Arr::only($validated, $fields),
        );
    }

    public static function equivalentLabel(Model $record, string $type): string
    {
        return sprintf('>= %s and <= %s = %s', $record->time_from, $record->time_to, $record->equivalent);
    }

    public static function settingsValidationRules(string $tab): array
    {
        return match ($tab) {
            'general' => self::generalRules(),
            'tardiness-undertime' => self::tardinessUndertimeRules(),
            'overtime' => self::overtimeRules(),
            'breaks' => self::breaksRules(),
            'leaves-absences' => self::leavesAbsencesRules(),
            'night-differential' => self::nightDifferentialRules(),
            'team-settings' => self::teamSettingsRules(),
            'toil-settings' => self::toilSettingsRules(),
            'logs-tagging' => self::logsTaggingRules(),
            default => abort(404),
        };
    }

    public static function validateSettings(string $tab, array $data): array
    {
        $validator = Validator::make($data, self::settingsValidationRules($tab));

        if ($tab === 'general') {
            $validator->after(function ($validator) use ($data) {
                $validateRestDays = filter_var($data['enable_employee_validation_for_rest_days'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $validateRestDays) {
                    return;
                }

                if (! is_numeric($data['max_rest_days_per_week'] ?? null)) {
                    $validator->errors()->add('max_rest_days_per_week', 'Maximum rest days per week is required.');
                }

                if (! is_numeric($data['min_hours_rendered_per_week'] ?? null) || (float) ($data['min_hours_rendered_per_week'] ?? 0) <= 0) {
                    $validator->errors()->add('min_hours_rendered_per_week', 'Minimum hours rendered per week must be greater than zero.');
                }
            });
        }

        if ($tab === 'overtime') {
            $validator->after(function ($validator) use ($data) {
                $excessHourId = (int) ($data['excess_hour_id'] ?? 0);

                if ($excessHourId === self::EXCESS_HOUR_DISREGARD) {
                    return;
                }

                if ($excessHourId <= 0) {
                    return;
                }

                if (! filter_var($data['is_consider_before_time'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    && ! filter_var($data['is_consider_after_time'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $validator->errors()->add('is_consider_before_time', 'Please select at least one Consider as OT option.');
                }

                if (! is_numeric($data['min_minutes'] ?? null) || (float) ($data['min_minutes'] ?? 0) <= 0) {
                    $validator->errors()->add('min_minutes', 'Minimum minutes is required and must be greater than zero.');
                }

                $specialStart = trim((string) ($data['special_ot_start'] ?? ''));
                $specialMin = $data['special_ot_min_minutes'] ?? null;

                if ($specialStart !== '' || filled($specialMin)) {
                    if (! preg_match('/^\d{2}:\d{2}$/', $specialStart)) {
                        $validator->errors()->add('special_ot_start', 'Invalid Special OT start time.');
                    }

                    if (! is_numeric($specialMin) || (float) $specialMin <= 0) {
                        $validator->errors()->add('special_ot_min_minutes', 'Special OT minimum minutes must be greater than zero.');
                    }
                }
            });
        }

        if ($tab === 'breaks') {
            $validator->after(function ($validator) use ($data) {
                if (filter_var($data['break_deduct_tardiness'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    if (blank($data['break_tardiness_leave_type_id'] ?? null)) {
                        $validator->errors()->add('break_tardiness_leave_type_id', 'Break tardiness leave type is required.');
                    }

                    $gracePeriod = $data['break_grace_period'] ?? null;
                    $deductGrace = filter_var($data['is_break_deduct_grace_period'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (filled($gracePeriod) || $deductGrace) {
                        if (! is_numeric($gracePeriod) || (float) $gracePeriod <= 0) {
                            $validator->errors()->add('break_grace_period', 'Grace period cannot be negative or zero.');
                        }
                    }
                }
            });
        }

        if ($tab === 'night-differential') {
            $validator->after(function ($validator) use ($data) {
                if (! filter_var($data['compute_night_diff'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                $start = trim((string) ($data['night_diff_start'] ?? ''));
                $end = trim((string) ($data['night_diff_end'] ?? ''));

                if ($start === '') {
                    $validator->errors()->add('night_diff_start', 'Start time is required.');
                } elseif (! preg_match('/^\d{2}:\d{2}$/', $start)) {
                    $validator->errors()->add('night_diff_start', 'Invalid start time format.');
                }

                if ($end === '') {
                    $validator->errors()->add('night_diff_end', 'End time is required.');
                } elseif (! preg_match('/^\d{2}:\d{2}$/', $end)) {
                    $validator->errors()->add('night_diff_end', 'Invalid end time format.');
                }
            });
        }

        if ($tab === 'toil-settings') {
            $validator->after(function ($validator) use ($data) {
                if (! filter_var($data['enable_toil'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                if (! is_numeric($data['exp_days'] ?? null) || (int) ($data['exp_days'] ?? 0) <= 0) {
                    $validator->errors()->add('exp_days', 'Number of days before expiration must be greater than zero.');
                }

                if (! is_numeric($data['min_toil_hours'] ?? null) || (float) ($data['min_toil_hours'] ?? 0) <= 0) {
                    $validator->errors()->add('min_toil_hours', 'Minimum TOIL hours must be greater than zero.');
                }

                if (! is_numeric($data['max_toil_hours'] ?? null) || (float) ($data['max_toil_hours'] ?? 0) <= 0) {
                    $validator->errors()->add('max_toil_hours', 'Maximum TOIL hours must be greater than zero.');
                }
            });
        }

        if ($tab === 'logs-tagging') {
            $validator->after(function ($validator) use ($data) {
                if (! filter_var($data['enable_logs_tagging'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                foreach (config('timekeeping_policy.logs_tagging_fields', []) as $field => $label) {
                    $value = trim((string) ($data[$field] ?? ''));

                    if ($value === '') {
                        $validator->errors()->add($field, $label.' is required.');

                        continue;
                    }

                    if (str_ends_with($field, '_tag') && ! preg_match('/^[A-Za-z]$/', $value)) {
                        $validator->errors()->add($field, $label.' must be a single character.');
                    }
                }
            });
        }

        return $validator->validate();
    }

    public static function settingsPayload(string $tab, array $validated): array
    {
        return match ($tab) {
            'general' => self::generalPayload($validated),
            'tardiness-undertime' => self::tardinessUndertimePayload($validated),
            'overtime' => self::overtimePayload($validated),
            'breaks' => self::breaksPayload($validated),
            'leaves-absences' => self::leavesAbsencesPayload($validated),
            'night-differential' => self::nightDifferentialPayload($validated),
            'team-settings' => self::teamSettingsPayload($validated),
            'toil-settings' => self::toilSettingsPayload($validated),
            'logs-tagging' => self::logsTaggingPayload($validated),
            default => abort(404),
        };
    }

    private static function generalRules(): array
    {
        return [
            'enable_attendance_approval' => ['nullable', 'boolean'],
            'buffer_time_in' => ['required', 'numeric', 'gt:0'],
            'buffer_time_out' => ['required', 'numeric', 'gt:0'],
            'non_regular_hours_computation_basis' => ['required', 'integer', Rule::in(array_keys(config('timekeeping_policy.non_regular_hours_bases', [])))],
            'enable_employee_validation_for_rest_days' => ['nullable', 'boolean'],
            'max_rest_days_per_week' => ['nullable', 'integer', 'min:0', 'max:7'],
            'min_hours_rendered_per_week' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
        ];
    }

    private static function generalPayload(array $validated): array
    {
        $validateRestDays = filter_var($validated['enable_employee_validation_for_rest_days'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'enable_attendance_approval' => filter_var($validated['enable_attendance_approval'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'buffer_time_in' => $validated['buffer_time_in'],
            'buffer_time_out' => $validated['buffer_time_out'],
            'non_regular_hours_computation_basis' => $validated['non_regular_hours_computation_basis'],
            'enable_employee_validation_for_rest_days' => $validateRestDays ?: null,
            'max_rest_days_per_week' => $validateRestDays && filled($validated['max_rest_days_per_week'] ?? null)
                ? $validated['max_rest_days_per_week']
                : null,
            'min_hours_rendered_per_week' => $validateRestDays && filled($validated['min_hours_rendered_per_week'] ?? null)
                ? $validated['min_hours_rendered_per_week']
                : null,
        ];
    }

    private static function tardinessUndertimeRules(): array
    {
        return [
            'is_allow_flexi_time' => ['required', 'boolean'],
            'max_flexi_time' => ['nullable', 'required_if:is_allow_flexi_time,1,true', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'grace_period' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'is_deduct_grace_period' => ['nullable', 'boolean'],
            'tardiness_leave_type_id' => ['required', 'integer', Rule::exists('tbl_leave_types', 'leave_type_id')],
            'undertime_leave_type_id' => ['required', 'integer', Rule::exists('tbl_leave_types', 'leave_type_id')],
            'tardiness_rounding_id' => ['nullable', 'integer', Rule::exists('lu_rounding', 'rounding_id')],
            'undertime_rounding_id' => ['nullable', 'integer', Rule::exists('lu_rounding', 'rounding_id')],
        ];
    }

    private static function tardinessUndertimePayload(array $validated): array
    {
        $allowFlexi = filter_var($validated['is_allow_flexi_time'], FILTER_VALIDATE_BOOLEAN);

        return [
            'is_allow_flexi_time' => $allowFlexi ?: null,
            'max_flexi_time' => $allowFlexi ? $validated['max_flexi_time'] : null,
            'grace_period' => filled($validated['grace_period'] ?? null) ? $validated['grace_period'] : null,
            'is_deduct_grace_period' => filter_var($validated['is_deduct_grace_period'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'tardiness_leave_type_id' => $validated['tardiness_leave_type_id'],
            'undertime_leave_type_id' => $validated['undertime_leave_type_id'],
            'tardiness_rounding_id' => filled($validated['tardiness_rounding_id'] ?? null) ? $validated['tardiness_rounding_id'] : null,
            'undertime_rounding_id' => filled($validated['undertime_rounding_id'] ?? null) ? $validated['undertime_rounding_id'] : null,
        ];
    }

    private static function overtimeRules(): array
    {
        return [
            'excess_hour_id' => ['required', 'integer', Rule::exists('lu_excess_hours', 'excess_hour_id')],
            'is_ot_form_required' => ['nullable', 'integer', Rule::exists('lu_non_regular_ot', 'non_regular_ot_id')],
            'is_consider_before_time' => ['nullable', 'boolean'],
            'is_consider_after_time' => ['nullable', 'boolean'],
            'min_minutes' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'overtime_rounding_id' => ['nullable', 'integer', Rule::exists('lu_rounding', 'rounding_id')],
            'special_ot_start' => ['nullable', 'string', 'max:5'],
            'special_ot_min_minutes' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
        ];
    }

    private static function overtimePayload(array $validated): array
    {
        $disregard = (int) $validated['excess_hour_id'] === self::EXCESS_HOUR_DISREGARD;

        if ($disregard) {
            return [
                'excess_hour_id' => $validated['excess_hour_id'],
                'is_ot_form_required' => null,
                'is_consider_before_time' => null,
                'is_consider_after_time' => null,
                'min_minutes' => null,
                'overtime_rounding_id' => null,
                'special_ot_start' => null,
                'special_ot_min_minutes' => null,
            ];
        }

        $specialStart = trim((string) ($validated['special_ot_start'] ?? ''));

        return [
            'excess_hour_id' => $validated['excess_hour_id'],
            'is_ot_form_required' => filled($validated['is_ot_form_required'] ?? null) ? $validated['is_ot_form_required'] : null,
            'is_consider_before_time' => filter_var($validated['is_consider_before_time'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'is_consider_after_time' => filter_var($validated['is_consider_after_time'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'min_minutes' => filled($validated['min_minutes'] ?? null) ? $validated['min_minutes'] : null,
            'overtime_rounding_id' => filled($validated['overtime_rounding_id'] ?? null) ? $validated['overtime_rounding_id'] : null,
            'special_ot_start' => $specialStart !== '' ? $specialStart : null,
            'special_ot_min_minutes' => $specialStart !== '' && filled($validated['special_ot_min_minutes'] ?? null)
                ? $validated['special_ot_min_minutes']
                : null,
        ];
    }

    private static function breaksRules(): array
    {
        return [
            'break_computation' => ['required', 'integer', Rule::in([1, 2])],
            'is_fix_break' => ['nullable', 'boolean'],
            'break_deduct_tardiness' => ['nullable', 'boolean'],
            'break_grace_period' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'is_break_deduct_grace_period' => ['nullable', 'boolean'],
            'break_tardiness_leave_type_id' => ['nullable', 'integer', Rule::exists('tbl_leave_types', 'leave_type_id')],
            'break_tardiness_rounding_id' => ['nullable', 'integer', Rule::exists('lu_rounding', 'rounding_id')],
        ];
    }

    private static function breaksPayload(array $validated): array
    {
        $deductBreakTardiness = filter_var($validated['break_deduct_tardiness'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'break_computation' => $validated['break_computation'],
            'is_fix_break' => filter_var($validated['is_fix_break'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'break_deduct_tardiness' => $deductBreakTardiness ?: null,
            'break_grace_period' => $deductBreakTardiness && filled($validated['break_grace_period'] ?? null) ? $validated['break_grace_period'] : null,
            'is_break_deduct_grace_period' => $deductBreakTardiness && filter_var($validated['is_break_deduct_grace_period'] ?? false, FILTER_VALIDATE_BOOLEAN) ? true : null,
            'break_tardiness_leave_type_id' => $deductBreakTardiness && filled($validated['break_tardiness_leave_type_id'] ?? null) ? $validated['break_tardiness_leave_type_id'] : null,
            'break_tardiness_rounding_id' => $deductBreakTardiness && filled($validated['break_tardiness_rounding_id'] ?? null) ? $validated['break_tardiness_rounding_id'] : null,
        ];
    }

    private static function leavesAbsencesRules(): array
    {
        return [
            'hide_negative_leaves' => ['nullable', 'boolean'],
            'enable_notification' => ['nullable', 'boolean'],
            'notif_for_process' => ['nullable', 'string', 'max:500'],
            'awol_leave_type_id' => ['required', 'integer', Rule::exists('tbl_leave_types', 'leave_type_id')],
            'leave_processing_mode' => ['required', 'integer', Rule::exists('tbl_leave_processing_modes', 'leave_processing_mode_id')],
            'validity_of_late_file' => ['required', 'integer', 'min:0'],
        ];
    }

    private static function leavesAbsencesPayload(array $validated): array
    {
        return [
            'hide_negative_leaves' => filter_var($validated['hide_negative_leaves'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'enable_notification' => filter_var($validated['enable_notification'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'notif_for_process' => filled($validated['notif_for_process'] ?? null) ? $validated['notif_for_process'] : null,
            'awol_leave_type_id' => $validated['awol_leave_type_id'],
            'leave_processing_mode' => $validated['leave_processing_mode'],
            'validity_of_late_file' => $validated['validity_of_late_file'],
        ];
    }

    private static function nightDifferentialRules(): array
    {
        return [
            'compute_night_diff' => ['nullable', 'boolean'],
            'night_diff_start' => ['nullable', 'string', 'max:5'],
            'night_diff_end' => ['nullable', 'string', 'max:5'],
            'nd_deduct_break' => ['nullable', 'boolean'],
        ];
    }

    private static function nightDifferentialPayload(array $validated): array
    {
        $computeNightDiff = filter_var($validated['compute_night_diff'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'night_diff_start' => $computeNightDiff ? trim((string) ($validated['night_diff_start'] ?? '')) : null,
            'night_diff_end' => $computeNightDiff ? trim((string) ($validated['night_diff_end'] ?? '')) : null,
            'nd_deduct_break' => filter_var($validated['nd_deduct_break'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
        ];
    }

    private static function teamSettingsRules(): array
    {
        return [
            'timekeeping_policy_team_setting_id' => [
                'required',
                'integer',
                Rule::exists('tbl_timekeeping_policy_team_settings', 'timekeeping_policy_team_setting_id'),
            ],
        ];
    }

    private static function teamSettingsPayload(array $validated): array
    {
        return [
            'timekeeping_policy_team_setting_id' => $validated['timekeeping_policy_team_setting_id'],
        ];
    }

    private static function toilSettingsRules(): array
    {
        return [
            'enable_toil' => ['nullable', 'boolean'],
            'exp_days' => ['nullable', 'integer', 'min:0'],
            'min_toil_hours' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'max_toil_hours' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
        ];
    }

    private static function toilSettingsPayload(array $validated): array
    {
        $enabled = filter_var($validated['enable_toil'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            return [
                'enable_toil' => null,
                'exp_days' => null,
                'min_toil_hours' => null,
                'max_toil_hours' => null,
            ];
        }

        return [
            'enable_toil' => true,
            'exp_days' => $validated['exp_days'],
            'min_toil_hours' => $validated['min_toil_hours'],
            'max_toil_hours' => $validated['max_toil_hours'],
        ];
    }

    private static function logsTaggingRules(): array
    {
        $rules = [
            'enable_logs_tagging' => ['nullable', 'boolean'],
        ];

        foreach (array_keys(config('timekeeping_policy.logs_tagging_fields', [])) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:45'];
        }

        return $rules;
    }

    private static function logsTaggingPayload(array $validated): array
    {
        $enabled = filter_var($validated['enable_logs_tagging'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payload = ['enable_logs_tagging' => $enabled ?: null];

        foreach (array_keys(config('timekeeping_policy.logs_tagging_fields', [])) as $field) {
            if (! $enabled) {
                $payload[$field] = null;

                continue;
            }

            $value = trim((string) ($validated[$field] ?? ''));
            $payload[$field] = str_ends_with($field, '_tag') ? strtoupper($value) : $value;
        }

        return $payload;
    }

    public static function dayCodesRules(): array
    {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $rules = [];

        foreach ($days as $day) {
            $rules[$day] = ['required', 'string', 'size:1', 'alpha'];
        }

        return $rules;
    }

    private static function dayCodesPayload(array $validated): array
    {
        $payload = [];

        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
            $payload[$day] = strtoupper($validated[$day]);
        }

        $unique = array_unique(array_values($payload));
        if (count($unique) !== 7) {
            throw ValidationException::withMessages([
                'sunday' => 'Day codes must be 7 unique single characters.',
            ]);
        }

        return $payload;
    }

    public static function formatMinutes(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 4, '.', ',');
    }
}
