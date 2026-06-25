<?php

namespace App\Support;

use App\Models\TimekeepingHoliday;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingHolidayYear;
use App\Models\TimekeepingYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HolidaySettings
{
    public const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    public static function authorize(?User $user, string $permission): void
    {
        TimekeepingPolicy::authorize($user, $permission);
    }

    public static function routeName(string $action): string
    {
        return "timekeeping.holiday-settings.$action";
    }

    public static function subTabs(): array
    {
        return collect(config('holiday_settings.sub_tabs', []))
            ->mapWithKeys(fn (array $tab, string $key) => [$key => $tab['label']])
            ->all();
    }

    public static function resolveSubTab(?string $subTab): string
    {
        $subTab = $subTab ?: config('holiday_settings.default_sub_tab', 'holidays');

        if (! array_key_exists($subTab, config('holiday_settings.sub_tabs', []))) {
            abort(404);
        }

        return $subTab;
    }

    public static function subTabConfig(string $subTab): array
    {
        return config('holiday_settings.sub_tabs.'.$subTab, []);
    }

    public static function moduleIndexRoute(string $subTab, array $query = []): string
    {
        return route(TimekeepingPolicy::routeName('module'), array_merge([
            'tab' => 'holiday-settings',
            'sub' => $subTab,
        ], $query));
    }

    public static function holidayListQuery(): Builder
    {
        return TimekeepingHoliday::query()->orderBy('timekeeping_holiday_code');
    }

    public static function groupListQuery(): Builder
    {
        return TimekeepingHolidayGroup::query()
            ->withCount('holidays')
            ->orderBy('timekeeping_holiday_group_code');
    }

    public static function yearListQuery(): Builder
    {
        return TimekeepingYear::query()
            ->withCount('holidayYears')
            ->orderByDesc('timekeeping_year');
    }

    public static function findHolidayOrFail(int $id): TimekeepingHoliday
    {
        return TimekeepingHoliday::query()->findOrFail($id);
    }

    public static function findGroupOrFail(int $id): TimekeepingHolidayGroup
    {
        return TimekeepingHolidayGroup::query()->with('holidays')->findOrFail($id);
    }

    public static function findYearOrFail(int $id): TimekeepingYear
    {
        return TimekeepingYear::query()->with('holidayYears.holiday')->findOrFail($id);
    }

    public static function findYearEntryOrFail(int $yearId, int $entryId): TimekeepingHolidayYear
    {
        return TimekeepingHolidayYear::query()
            ->where('timekeeping_year_id', $yearId)
            ->findOrFail($entryId);
    }

    public static function validateHoliday(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'timekeeping_holiday_code' => [
                'required',
                'string',
                'max:4',
                'alpha_num',
                Rule::unique('tbl_timekeeping_holidays', 'timekeeping_holiday_code')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId, 'timekeeping_holiday_id'),
            ],
            'description' => ['required', 'string', 'max:75'],
            'short_description' => ['nullable', 'string', 'max:25'],
            'dt_datestamp' => ['required', 'date', 'date_format:Y-m-d'],
            'is_legal' => ['required', 'boolean'],
            'recurring' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($data) {
            foreach (['description', 'short_description', 'timekeeping_holiday_code'] as $field) {
                $value = (string) ($data[$field] ?? '');

                if ($value !== '' && $value !== strip_tags($value)) {
                    $validator->errors()->add($field, 'HTML tags are not allowed.');
                }
            }
        });

        return $validator->validate();
    }

    public static function holidayPayload(array $validated): array
    {
        return [
            'timekeeping_holiday_code' => strtoupper(trim($validated['timekeeping_holiday_code'])),
            'description' => trim($validated['description']),
            'short_description' => filled($validated['short_description'] ?? null) ? trim($validated['short_description']) : null,
            'dt_datestamp' => $validated['dt_datestamp'],
            'is_legal' => filter_var($validated['is_legal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'recurring' => filter_var($validated['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function holidayInUse(TimekeepingHoliday $holiday): bool
    {
        return DB::table('tbl_timekeeping_holiday_group_list')
            ->where('timekeeping_holiday_id', $holiday->timekeeping_holiday_id)
            ->exists();
    }

    public static function validateGroup(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'timekeeping_holiday_group_code' => [
                'required',
                'string',
                'max:4',
                'alpha_num',
                Rule::unique('tbl_timekeeping_holiday_groups', 'timekeeping_holiday_group_code')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId, 'timekeeping_holiday_group_id'),
            ],
            'description' => ['required', 'string', 'max:75'],
            'holiday_ids' => ['required', 'array', 'min:1'],
            'holiday_ids.*' => ['integer', Rule::exists('tbl_timekeeping_holidays', 'timekeeping_holiday_id')->whereNull('deleted_at')],
        ]);

        $validator->after(function ($validator) use ($data) {
            foreach (['description', 'timekeeping_holiday_group_code'] as $field) {
                $value = (string) ($data[$field] ?? '');

                if ($value !== strip_tags($value)) {
                    $validator->errors()->add($field, 'HTML tags are not allowed.');
                }
            }
        });

        return $validator->validate();
    }

    public static function groupPayload(array $validated): array
    {
        return [
            'timekeeping_holiday_group_code' => strtoupper(trim($validated['timekeeping_holiday_group_code'])),
            'description' => trim($validated['description']),
        ];
    }

    /**
     * @return list<int>
     */
    public static function groupHolidayIds(array $validated): array
    {
        return collect($validated['holiday_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function syncGroupHolidays(TimekeepingHolidayGroup $group, array $holidayIds): void
    {
        $group->groupList()->delete();

        foreach ($holidayIds as $holidayId) {
            $group->groupList()->create([
                'timekeeping_holiday_id' => $holidayId,
            ]);
        }
    }

    public static function groupInUse(TimekeepingHolidayGroup $group): bool
    {
        if (Schema::hasTable('tbl_timekeeping_employee_setup')
            && Schema::hasColumn('tbl_timekeeping_employee_setup', 'timekeeping_holiday_group_id')) {
            return DB::table('tbl_timekeeping_employee_setup')
                ->where('timekeeping_holiday_group_id', $group->timekeeping_holiday_group_id)
                ->exists();
        }

        return false;
    }

    public static function validateYear(array $data, ?int $ignoreId = null): array
    {
        return Validator::make($data, [
            'timekeeping_year' => [
                'required',
                'integer',
                'min:1900',
                'max:9999',
                Rule::unique('tbl_timekeeping_years', 'timekeeping_year')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId, 'timekeeping_year_id'),
            ],
        ])->validate();
    }

    public static function yearPayload(array $validated): array
    {
        return [
            'timekeeping_year' => (int) $validated['timekeeping_year'],
        ];
    }

    public static function seedRecurringHolidaysForYear(TimekeepingYear $year): void
    {
        $recurring = TimekeepingHoliday::query()->where('recurring', true)->get();

        foreach ($recurring as $holiday) {
            TimekeepingHolidayYear::query()->firstOrCreate(
                [
                    'timekeeping_year_id' => $year->timekeeping_year_id,
                    'timekeeping_holiday_id' => $holiday->timekeeping_holiday_id,
                ],
                self::yearEntryPayloadFromHoliday($holiday, $year->timekeeping_year_id),
            );
        }
    }

    public static function createYearEntryFromHoliday(TimekeepingHoliday $holiday, int $yearId): TimekeepingHolidayYear
    {
        return TimekeepingHolidayYear::query()->create(
            self::yearEntryPayloadFromHoliday($holiday, $yearId),
        );
    }

    public static function yearEntryExistsForYear(int $yearId, int $holidayId): bool
    {
        return TimekeepingHolidayYear::query()
            ->where('timekeeping_year_id', $yearId)
            ->where('timekeeping_holiday_id', $holidayId)
            ->exists();
    }

    public static function yearInUse(TimekeepingYear $year): bool
    {
        return (int) $year->timekeeping_year <= (int) date('Y');
    }

    public static function validateYearEntryAdd(array $data, int $yearId): array
    {
        return Validator::make($data, [
            'timekeeping_holiday_id' => [
                'required',
                'integer',
                Rule::exists('tbl_timekeeping_holidays', 'timekeeping_holiday_id')->whereNull('deleted_at'),
                Rule::unique('tbl_timekeeping_holiday_years', 'timekeeping_holiday_id')
                    ->where('timekeeping_year_id', $yearId),
            ],
        ])->validate();
    }

    public static function validateYearEntryEdit(array $data, int $yearId, int $entryId): array
    {
        $validator = Validator::make($data, [
            'timekeeping_holiday_code' => [
                'required',
                'string',
                'max:4',
                'alpha_num',
                Rule::unique('tbl_timekeeping_holiday_years', 'timekeeping_holiday_code')
                    ->where('timekeeping_year_id', $yearId)
                    ->ignore($entryId, 'timekeeping_holiday_year_id'),
            ],
            'dt_datestamp' => ['required', 'date', 'date_format:Y-m-d'],
            'is_legal' => ['required', 'boolean'],
            'recurring' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($data) {
            $value = (string) ($data['timekeeping_holiday_code'] ?? '');

            if ($value !== strip_tags($value)) {
                $validator->errors()->add('timekeeping_holiday_code', 'HTML tags are not allowed.');
            }
        });

        return $validator->validate();
    }

    public static function yearEntryPayloadFromHoliday(TimekeepingHoliday $holiday, int $yearId): array
    {
        return [
            'timekeeping_year_id' => $yearId,
            'timekeeping_holiday_id' => $holiday->timekeeping_holiday_id,
            'timekeeping_holiday_code' => $holiday->timekeeping_holiday_code,
            'dt_datestamp' => $holiday->dt_datestamp,
            'is_legal' => $holiday->is_legal,
            'recurring' => $holiday->recurring,
        ];
    }

    public static function yearEntryPayload(array $validated): array
    {
        return [
            'timekeeping_holiday_code' => strtoupper(trim($validated['timekeeping_holiday_code'])),
            'dt_datestamp' => $validated['dt_datestamp'],
            'is_legal' => filter_var($validated['is_legal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'recurring' => filter_var($validated['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function holidayOptions(?TimekeepingHolidayGroup $group = null): array
    {
        return TimekeepingHoliday::query()
            ->orderBy('timekeeping_holiday_code')
            ->get()
            ->mapWithKeys(fn (TimekeepingHoliday $holiday) => [
                $holiday->timekeeping_holiday_id => '['.$holiday->timekeeping_holiday_code.'] '.$holiday->description,
            ])
            ->all();
    }

    /**
     * Holidays not yet assigned to a year.
     *
     * @return array<int, string>
     */
    public static function availableYearHolidayOptions(int $yearId): array
    {
        $assigned = TimekeepingHolidayYear::query()
            ->where('timekeeping_year_id', $yearId)
            ->pluck('timekeeping_holiday_id');

        return TimekeepingHoliday::query()
            ->whereNotIn('timekeeping_holiday_id', $assigned)
            ->orderBy('timekeeping_holiday_code')
            ->get()
            ->mapWithKeys(fn (TimekeepingHoliday $holiday) => [
                $holiday->timekeeping_holiday_id => '['.$holiday->timekeeping_holiday_code.'] '.$holiday->description,
            ])
            ->all();
    }

    public static function legalLabel(bool $isLegal): string
    {
        return $isLegal ? 'Legal' : 'Special';
    }
}
