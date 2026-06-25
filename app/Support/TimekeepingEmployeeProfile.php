<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LuDay;
use App\Models\ShiftCode;
use App\Models\SubModule;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Models\TimekeepingPolicyTeamSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TimekeepingEmployeeProfile
{
    public const SUB_MODULE_ROUTE = 'timekeeping.employee-profile.index';

    public static function showApprovalMatrix(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function setupTabs(): array
    {
        $tabs = [
            'timekeeping' => 'Timekeeping Settings',
        ];

        if (self::showApprovalMatrix()) {
            $tabs['approval'] = 'Approval Settings';
        }

        $tabs['attendance'] = 'Attendance View';

        return $tabs;
    }

    public static function normalizeSetupTab(?string $tab): string
    {
        $tab = $tab ?: 'timekeeping';

        if (! array_key_exists($tab, self::setupTabs())) {
            return 'timekeeping';
        }

        return $tab;
    }

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'timekeeping.employee-profile.index',
            'show' => 'timekeeping.employee-profile.show',
            'store' => 'timekeeping.employee-profile.store',
            'approval' => 'timekeeping.employee-profile.approval',
            'approval-routes' => 'timekeeping.employee-profile.approval-routes',
            'attendance' => 'timekeeping.employee-profile.attendance',
            default => "timekeeping.employee-profile.$action",
        };
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

        $gatePermission = match ($permission) {
            'add' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            default => $permission,
        };

        if (! $user->can("employee-profile.$gatePermission")) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    public static function query(): Builder
    {
        return Employee::query()
            ->with([
                'timekeepingSetup.holidayGroup',
                'timekeepingSetup.shiftCode',
                'timekeepingSetup.policy',
                'timekeepingSetup.teamSetting',
                'timekeepingRestDays',
            ])
            ->orderBy('employee_number')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @return array{
     *     days: \Illuminate\Support\Collection<int, LuDay>,
     *     holidayGroups: \Illuminate\Support\Collection,
     *     shiftCodes: \Illuminate\Support\Collection,
     *     policies: \Illuminate\Support\Collection,
     *     teamSettings: \Illuminate\Support\Collection
     * }
     */
    public static function formOptions(): array
    {
        return [
            'days' => LuDay::query()->orderBy('day_id')->get(),
            'holidayGroups' => TimekeepingHolidayGroup::query()->orderBy('description')->get(),
            'shiftCodes' => ShiftCode::query()->orderBy('description')->get(),
            'policies' => TimekeepingPolicy::query()->orderBy('policy_name')->get(),
            'teamSettings' => TimekeepingPolicyTeamSetting::query()->orderBy('description')->get(),
        ];
    }

    public static function setupStatusLabel(Employee $employee): string
    {
        return $employee->hasTimekeepingSetup() ? 'Setup Complete' : 'Needs Setup';
    }

    public static function isSetupComplete(Employee $employee): bool
    {
        return $employee->hasTimekeepingSetup();
    }
}
