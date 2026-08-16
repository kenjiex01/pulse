<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LuDay;
use App\Models\RawEmployeeLoadEntry;
use App\Models\ShiftCode;
use App\Models\SubModule;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
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
        $tabs['calendar'] = 'Calendar View';
        $tabs['employee-load'] = 'Employee Load';

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
            'attendance-pdf' => 'timekeeping.employee-profile.attendance-pdf',
            'calendar' => 'timekeeping.employee-profile.calendar',
            'attendance-store' => 'timekeeping.employee-profile.attendance-store',
            'attendance-update' => 'timekeeping.employee-profile.attendance-update',
            'attendance-destroy' => 'timekeeping.employee-profile.attendance-destroy',
            'employee-load' => 'timekeeping.employee-profile.employee-load',
            'upload.template' => 'timekeeping.employee-profile.upload.template',
            'upload.process' => 'timekeeping.employee-profile.upload.process',
            'upload.commit' => 'timekeeping.employee-profile.upload.commit',
            'upload.discard' => 'timekeeping.employee-profile.upload.discard',
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
     *     policies: \Illuminate\Support\Collection
     * }
     */
    public static function formOptions(): array
    {
        return [
            'days' => LuDay::query()->orderBy('day_id')->get(),
            'holidayGroups' => TimekeepingHolidayGroup::query()->orderBy('description')->get(),
            'shiftCodes' => ShiftCode::query()->orderBy('description')->get(),
            'policies' => TimekeepingPolicy::query()->orderBy('policy_name')->get(),
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

    /**
     * @return Builder<RawEmployeeLoadEntry>
     */
    public static function employeeLoadEntriesQuery(Employee $employee): Builder
    {
        return RawEmployeeLoadEntry::query()
            ->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->employee_id);

                if ($employee->employee_number) {
                    $query->orWhere(function ($fallback) use ($employee) {
                        $fallback
                            ->whereNull('employee_id')
                            ->where('employee_number', $employee->employee_number);
                    });
                }
            })
            ->orderBy('session_date')
            ->orderBy('class_schedule')
            ->orderBy('employee_load_entry_id');
    }

    /**
     * @return array{total_sessions: int, worked_days: int, sessions_with_time_in: int}
     */
    public static function employeeLoadSummary(Employee $employee): array
    {
        $employee->loadMissing('timekeepingSetup.policy');
        $policy = $employee->timekeepingSetup?->policy;

        $base = self::employeeLoadEntriesQuery($employee);
        $entriesWithTimeIn = (clone $base)
            ->whereNotNull('time_in')
            ->where('time_in', '!=', '')
            ->get();

        /** @var \App\Services\EmployeeLoadPayrollService $payroll */
        $payroll = app(\App\Services\EmployeeLoadPayrollService::class);

        return [
            'total_sessions' => (clone $base)->count(),
            'sessions_with_time_in' => $entriesWithTimeIn->count(),
            'worked_days' => $payroll->countWorkedDays($entriesWithTimeIn, $policy),
        ];
    }
}
