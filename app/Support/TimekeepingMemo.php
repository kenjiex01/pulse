<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TimekeepingMemo
{
    public const SUB_MODULE_ROUTE = 'timekeeping.memo.index';

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'timekeeping.memo.index',
            'details' => 'timekeeping.memo.details',
            'send' => 'timekeeping.memo.send',
            'batch-send' => 'timekeeping.memo.batch-send',
            'preview' => 'timekeeping.memo.preview',
            'preview-html' => 'timekeeping.memo.preview-html',
            'preview-pdf' => 'timekeeping.memo.preview-pdf',
            default => "timekeeping.memo.$action",
        };
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(?User $user, string $permission = 'view'): void
    {
        abort_unless($user !== null, 403);

        $gate = match ($permission) {
            'view' => 'memo.viewAny',
            'update' => 'memo.update',
            default => 'memo.viewAny',
        };

        abort_unless($user->can($gate), 403);
    }

    /**
     * @return Builder<Employee>
     */
    public static function employeeQuery(): Builder
    {
        return Employee::query()
            ->with(['campus', 'campusAssignments.campus', 'timekeepingSetup'])
            ->whereNull('deleted_at')
            ->whereHas('timekeepingSetup');
    }

    /**
     * @return array<string, string>
     */
    public static function violationTypeOptions(): array
    {
        return [
            'late' => 'Late',
            'undertime' => 'Undertime',
            'absent' => 'Absent',
        ];
    }
}
