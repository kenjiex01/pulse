<?php

namespace App\Support;

use App\Models\ShiftCode as ShiftCodeModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShiftCode
{
    public const LOG_TABLE = 'tbl_shift_codes';

    public const TIME_PATTERN = '/^(2[0-3]|[01][0-9]):[0-5][0-9]$/';

    public static function authorize(?User $user, string $permission): void
    {
        TimekeepingPolicy::authorize($user, $permission);
    }

    public static function routeName(string $action): string
    {
        return "timekeeping.shift-codes.$action";
    }

    public static function listQuery(): Builder
    {
        return ShiftCodeModel::query()->orderBy('shift_code_id');
    }

    public static function findOrFail(int $id): ShiftCodeModel
    {
        return ShiftCodeModel::query()->with('breaks')->findOrFail($id);
    }

    public static function recordLabel(ShiftCodeModel $record): string
    {
        return $record->shift_code.' — '.$record->description;
    }

    public static function validationRules(?int $ignoreId = null): array
    {
        return [
            'shift_code' => [
                'required',
                'string',
                'max:4',
                'alpha_num',
                Rule::unique('tbl_shift_codes', 'shift_code')
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId, 'shift_code_id'),
            ],
            'description' => ['required', 'string', 'max:45'],
            'time_in' => ['required', 'string', 'max:5', 'regex:'.self::TIME_PATTERN],
            'time_out' => ['required', 'string', 'max:5', 'regex:'.self::TIME_PATTERN],
            'breaks' => ['nullable', 'array'],
            'breaks.*.break_minute' => ['required', 'integer', 'min:1', 'max:999'],
            'breaks.*.is_paid_break' => ['nullable', 'boolean'],
        ];
    }

    public static function validate(array $data, ?int $ignoreId = null): array
    {
        return Validator::make($data, self::validationRules($ignoreId))->validate();
    }

    public static function headerPayload(array $validated): array
    {
        return [
            'shift_code' => strtoupper(trim($validated['shift_code'])),
            'description' => trim($validated['description']),
            'time_in' => trim($validated['time_in']),
            'time_out' => trim($validated['time_out']),
        ];
    }

    /**
     * @return array<int, array{shift_code_break_no: int, shift_code_break_minute: int, shift_code_is_paid_break: bool}>
     */
    public static function breaksPayload(array $validated): array
    {
        $rows = [];

        foreach ($validated['breaks'] ?? [] as $index => $break) {
            if (! filled($break['break_minute'] ?? null)) {
                continue;
            }

            $rows[] = [
                'shift_code_break_no' => $index + 1,
                'shift_code_break_minute' => (int) $break['break_minute'],
                'shift_code_is_paid_break' => filter_var($break['is_paid_break'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $rows;
    }

    public static function syncBreaks(ShiftCodeModel $shiftCode, array $breakRows): void
    {
        $shiftCode->breaks()->forceDelete();

        foreach ($breakRows as $row) {
            $shiftCode->breaks()->create($row);
        }
    }

    public static function isInUse(ShiftCodeModel $shiftCode): bool
    {
        if (Schema::hasTable('tbl_timekeeping_employee_setup')
            && Schema::hasColumn('tbl_timekeeping_employee_setup', 'shift_code_id')) {
            return DB::table('tbl_timekeeping_employee_setup')
                ->where('shift_code_id', $shiftCode->shift_code_id)
                ->exists();
        }

        if (Schema::hasTable('trn_timekeeping_inandout')
            && Schema::hasColumn('trn_timekeeping_inandout', 'shift_code_id')) {
            return DB::table('trn_timekeeping_inandout')
                ->where('shift_code_id', $shiftCode->shift_code_id)
                ->exists();
        }

        return false;
    }

    public static function breakRowsForForm(?ShiftCodeModel $record = null): array
    {
        if ($record) {
            return $record->breaks
                ->map(fn ($break) => [
                    'break_no' => $break->shift_code_break_no,
                    'break_minute' => $break->shift_code_break_minute,
                    'is_paid_break' => $break->shift_code_is_paid_break,
                ])
                ->values()
                ->all();
        }

        return [];
    }
}
