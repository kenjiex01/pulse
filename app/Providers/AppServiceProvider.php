<?php

namespace App\Providers;

use App\Models\Module;
use App\Models\User;
use App\Policies\HrLookupPolicy;
use App\Policies\GovernmentTablesPolicy;
use App\Policies\PayrollCalendarPolicy;
use App\Policies\PayrollMaintenancePolicy;
use App\Policies\PayrollTransactionPolicy;
use App\Policies\RateDefinitionPolicy;
use App\Policies\TimekeepingEmployeeLoadPolicy;
use App\Policies\TimekeepingEmployeeProfilePolicy;
use App\Policies\TimekeepingPolicyPolicy;
use App\Policies\TimeLogsPolicy;
use App\Support\HrLookup;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $hrLookupPolicy = new HrLookupPolicy;
        $payrollMaintenancePolicy = new PayrollMaintenancePolicy;
        $payrollCalendarPolicy = new PayrollCalendarPolicy;
        $payrollTransactionPolicy = new PayrollTransactionPolicy;
        $rateDefinitionPolicy = new RateDefinitionPolicy;
        $governmentTablesPolicy = new GovernmentTablesPolicy;
        $timekeepingPolicyPolicy = new TimekeepingPolicyPolicy;
        $timeLogsPolicy = new TimeLogsPolicy;
        $employeeProfilePolicy = new TimekeepingEmployeeProfilePolicy;
        $employeeLoadPolicy = new TimekeepingEmployeeLoadPolicy;

        Gate::define('hr-lookup.viewAny', fn (User $user, string $lookup) => $hrLookupPolicy->viewAny($user, $lookup));
        Gate::define('hr-lookup.create', fn (User $user, string $lookup) => $hrLookupPolicy->create($user, $lookup));
        Gate::define('hr-lookup.update', fn (User $user, string $lookup, $record) => $hrLookupPolicy->update($user, $lookup, $record));
        Gate::define('hr-lookup.delete', fn (User $user, string $lookup, $record) => $hrLookupPolicy->delete($user, $lookup, $record));

        Gate::define('payroll-maintenance.viewAny', fn (User $user) => $payrollMaintenancePolicy->viewAny($user));
        Gate::define('payroll-maintenance.create', fn (User $user) => $payrollMaintenancePolicy->create($user));
        Gate::define('payroll-maintenance.update', fn (User $user, $record) => $payrollMaintenancePolicy->update($user, $record));
        Gate::define('payroll-maintenance.delete', fn (User $user, $record) => $payrollMaintenancePolicy->delete($user, $record));

        Gate::define('payroll-calendar.viewAny', fn (User $user) => $payrollCalendarPolicy->viewAny($user));
        Gate::define('payroll-calendar.create', fn (User $user) => $payrollCalendarPolicy->create($user));
        Gate::define('payroll-calendar.update', fn (User $user) => $payrollCalendarPolicy->update($user));
        Gate::define('payroll-calendar.delete', fn (User $user) => $payrollCalendarPolicy->delete($user));

        Gate::define('payroll-transaction.viewAny', fn (User $user) => $payrollTransactionPolicy->viewAny($user));
        Gate::define('payroll-transaction.create', fn (User $user) => $payrollTransactionPolicy->create($user));
        Gate::define('payroll-transaction.update', fn (User $user) => $payrollTransactionPolicy->update($user));
        Gate::define('payroll-transaction.delete', fn (User $user) => $payrollTransactionPolicy->delete($user));

        Gate::define('rate-definition.viewAny', fn (User $user) => $rateDefinitionPolicy->viewAny($user));
        Gate::define('rate-definition.create', fn (User $user) => $rateDefinitionPolicy->create($user));
        Gate::define('rate-definition.update', fn (User $user, $record) => $rateDefinitionPolicy->update($user, $record));
        Gate::define('rate-definition.delete', fn (User $user, $record) => $rateDefinitionPolicy->delete($user, $record));

        Gate::define('government-tables.viewAny', fn (User $user) => $governmentTablesPolicy->viewAny($user));
        Gate::define('government-tables.create', fn (User $user) => $governmentTablesPolicy->create($user));
        Gate::define('government-tables.update', fn (User $user, $record = null) => $governmentTablesPolicy->update($user, $record));
        Gate::define('government-tables.delete', fn (User $user, $record = null) => $governmentTablesPolicy->delete($user, $record));

        Gate::define('timekeeping-policy.viewAny', fn (User $user) => $timekeepingPolicyPolicy->viewAny($user));
        Gate::define('timekeeping-policy.create', fn (User $user) => $timekeepingPolicyPolicy->create($user));
        Gate::define('timekeeping-policy.update', fn (User $user, $record = null) => $timekeepingPolicyPolicy->update($user, $record));
        Gate::define('timekeeping-policy.delete', fn (User $user, $record = null) => $timekeepingPolicyPolicy->delete($user, $record));

        Gate::define('time-logs.viewAny', fn (User $user) => $timeLogsPolicy->viewAny($user));
        Gate::define('time-logs.create', fn (User $user) => $timeLogsPolicy->create($user));
        Gate::define('time-logs.update', fn (User $user, $record = null) => $timeLogsPolicy->update($user, $record));
        Gate::define('time-logs.delete', fn (User $user, $record = null) => $timeLogsPolicy->delete($user, $record));

        Gate::define('employee-profile.viewAny', fn (User $user) => $employeeProfilePolicy->viewAny($user));
        Gate::define('employee-profile.create', fn (User $user) => $employeeProfilePolicy->create($user));
        Gate::define('employee-profile.update', fn (User $user, $record = null) => $employeeProfilePolicy->update($user, $record));
        Gate::define('employee-profile.delete', fn (User $user, $record = null) => $employeeProfilePolicy->delete($user, $record));

        Gate::define('employee-load.viewAny', fn (User $user) => $employeeLoadPolicy->viewAny($user));
        Gate::define('employee-load.create', fn (User $user) => $employeeLoadPolicy->create($user));
        Gate::define('employee-load.update', fn (User $user, $record = null) => $employeeLoadPolicy->update($user, $record));
        Gate::define('employee-load.delete', fn (User $user, $record = null) => $employeeLoadPolicy->delete($user, $record));

        Paginator::defaultView('vendor.pagination.skolaris');
        Paginator::defaultSimpleView('vendor.pagination.skolaris');

        View::composer('partials.sidebar', function ($view): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();
            $userRoleIds = $user->roles()->pluck('roles.id');

            $sidebarModules = Module::query()
                ->with(['subModules' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)
                ->where(function ($moduleQuery) use ($userRoleIds) {
                    $moduleQuery
                        ->where(function ($directModuleQuery) use ($userRoleIds) {
                            $directModuleQuery
                                ->whereNotNull('route_name')
                                ->whereHas('roles', function ($roleQuery) use ($userRoleIds) {
                                    $roleQuery
                                        ->whereIn('roles.id', $userRoleIds)
                                        ->where(function ($permissionQuery) {
                                            $permissionQuery->where('tbl_role_modules.full_control', true)
                                                ->orWhere('tbl_role_modules.can_add', true)
                                                ->orWhere('tbl_role_modules.can_edit', true)
                                                ->orWhere('tbl_role_modules.can_update', true)
                                                ->orWhere('tbl_role_modules.can_delete', true);
                                        });
                                });
                        })
                        ->orWhereHas('subModules', function ($subModuleQuery) use ($userRoleIds) {
                            $subModuleQuery
                                ->where('is_active', true)
                                ->whereHas('roles', function ($roleQuery) use ($userRoleIds) {
                                    $roleQuery
                                        ->whereIn('roles.id', $userRoleIds)
                                        ->where(function ($permissionQuery) {
                                            $permissionQuery->where('tbl_role_sub_modules.full_control', true)
                                                ->orWhere('tbl_role_sub_modules.can_add', true)
                                                ->orWhere('tbl_role_sub_modules.can_edit', true)
                                                ->orWhere('tbl_role_sub_modules.can_update', true)
                                                ->orWhere('tbl_role_sub_modules.can_delete', true);
                                        });
                                });
                        });
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(function (Module $module) use ($user) {
                    if ($module->subModules->isNotEmpty()) {
                        $module->setRelation(
                            'subModules',
                            $module->subModules->filter(fn ($subModule) => $user->hasSubModuleAccess($subModule)),
                        );
                    }

                    return $module;
                })
                ->filter(function (Module $module) {
                    if ($module->subModules->isNotEmpty()) {
                        return $module->subModules->isNotEmpty();
                    }

                    return filled($module->route_name);
                })
                ->groupBy('section');

            $view->with('sidebarModules', $sidebarModules);
        });

        if ($this->app->runningInConsole() && ! $this->isNativeDesktop()) {
            return;
        }

        $this->ensureDesktopDatabase();
    }

    private function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    private function ensureDesktopDatabase(): void
    {
        if (! $this->isNativeDesktop()) {
            return;
        }

        $databasePath = storage_path('app/pulse.sqlite');
        $isFirstLaunch = ! File::exists($databasePath);

        if ($isFirstLaunch) {
            File::ensureDirectoryExists(dirname($databasePath));
            File::put($databasePath, '');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);

        Artisan::call('migrate', ['--force' => true]);

        if ($isFirstLaunch || ! User::query()->exists()) {
            Artisan::call('db:seed', ['--force' => true]);
        }
    }
}
