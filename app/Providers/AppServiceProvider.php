<?php

namespace App\Providers;

use App\Listeners\HandleDesktopUpdaterEvents;
use App\Models\User;
use App\Policies\HrLookupPolicy;
use App\Services\DatabaseBackupService;
use App\Services\DesktopBootstrapService;
use App\Services\DesktopCloudBackupService;
use App\Services\DesktopUpdaterService;
use App\Services\GovernmentTablesBootstrapService;
use App\Services\ReferenceDataBootstrapService;
use App\Services\SidebarNavigationService;
use App\Policies\BirFormSettingsPolicy;
use App\Policies\GovernmentTablesPolicy;
use App\Policies\PayrollCalendarPolicy;
use App\Policies\PayrollMaintenancePolicy;
use App\Policies\PayrollReportsPolicy;
use App\Policies\PayrollTransactionPolicy;
use App\Policies\RateDefinitionPolicy;
use App\Policies\TimekeepingEmployeeLoadPolicy;
use App\Policies\TimekeepingEmployeeProfilePolicy;
use App\Policies\TimekeepingPolicyPolicy;
use App\Policies\TimeLogsPolicy;
use App\Support\EncryptedEnv;
use App\Support\HrLookup;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Native\Laravel\Events\AutoUpdater\DownloadProgress;
use Native\Laravel\Events\AutoUpdater\Error;
use Native\Laravel\Events\AutoUpdater\UpdateAvailable;
use Native\Laravel\Events\AutoUpdater\UpdateDownloaded;
use Native\Laravel\Events\AutoUpdater\UpdateNotAvailable;

class AppServiceProvider extends ServiceProvider
{
    private static bool $desktopDatabaseEnsured = false;

    public function register(): void
    {
        $this->app->singleton(DesktopUpdaterService::class);
    }

    public function boot(): void
    {
        $this->registerDesktopUpdater();

        // Decrypt API/S3 secrets into memory before any HTTP or console work uses them.
        EncryptedEnv::revealConfiguredSecrets();

        $hrLookupPolicy = new HrLookupPolicy;
        $payrollMaintenancePolicy = new PayrollMaintenancePolicy;
        $payrollCalendarPolicy = new PayrollCalendarPolicy;
        $payrollTransactionPolicy = new PayrollTransactionPolicy;
        $payrollReportsPolicy = new PayrollReportsPolicy;
        $rateDefinitionPolicy = new RateDefinitionPolicy;
        $governmentTablesPolicy = new GovernmentTablesPolicy;
        $birFormSettingsPolicy = new BirFormSettingsPolicy;
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

        Gate::define('payroll-reports.viewAny', fn (User $user) => $payrollReportsPolicy->viewAny($user));
        Gate::define('payroll-reports.create', fn (User $user) => $payrollReportsPolicy->create($user));

        Gate::define('bir-forms.viewAny', fn (User $user) => $birFormSettingsPolicy->viewAny($user));
        Gate::define('bir-forms.update', fn (User $user) => $birFormSettingsPolicy->update($user));

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

            $view->with(
                'sidebarModules',
                app(SidebarNavigationService::class)->groupedModulesFor(auth()->user()),
            );
        });

        View::composer(['layouts.app', 'layouts.guest'], function ($view): void {
            try {
                $updater = app(DesktopUpdaterService::class)->status();
            } catch (\Throwable) {
                $updater = [
                    'enabled' => false,
                    'force_install' => false,
                    'version' => (string) config('nativephp.version', '0.0.0'),
                    'pending' => null,
                    'downloading' => null,
                    'installing' => null,
                ];
            }

            $view->with('desktopUpdater', $updater);
        });

        if ($this->app->runningInConsole() && ! $this->isNativeDesktop()) {
            return;
        }

        $this->discardViteDevHotFileInDesktopBundle();
        $this->disableDebugRenderingOnDesktop();

        $this->ensureDesktopDatabase();
    }

    /**
     * A leftover public/hot file (from local `npm run dev`) makes @vite load localhost — no CSS/JS in production desktop.
     */
    private function discardViteDevHotFileInDesktopBundle(): void
    {
        if (! $this->isNativeDesktop()) {
            return;
        }

        $hotPath = public_path('hot');

        if (File::exists($hotPath)) {
            File::delete($hotPath);
        }
    }

    /**
     * Bundled .env ships APP_DEBUG=true; debug error pages are slow and leak internals on client machines.
     */
    private function disableDebugRenderingOnDesktop(): void
    {
        if (! $this->isNativeDesktop() || env('NATIVEPHP_DEBUG', false)) {
            return;
        }

        config(['app.debug' => false]);
    }

    private function registerDesktopUpdater(): void
    {
        $listener = HandleDesktopUpdaterEvents::class;

        Event::listen(UpdateAvailable::class, [$listener, 'handleUpdateAvailable']);
        Event::listen(DownloadProgress::class, [$listener, 'handleDownloadProgress']);
        Event::listen(UpdateDownloaded::class, [$listener, 'handleUpdateDownloaded']);
        Event::listen(UpdateNotAvailable::class, [$listener, 'handleUpdateNotAvailable']);
        Event::listen(Error::class, [$listener, 'handleError']);
    }

    private function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    private function ensureDesktopDatabase(): void
    {
        if (! $this->isNativeDesktop() || self::$desktopDatabaseEnsured) {
            return;
        }

        self::$desktopDatabaseEnsured = true;

        $databasePath = storage_path('app/pulse.sqlite');
        $versionMarkerPath = storage_path('app/.desktop-bootstrap-version');
        $isFirstLaunch = ! File::exists($databasePath) || File::size($databasePath) === 0;

        if ($isFirstLaunch) {
            File::ensureDirectoryExists(dirname($databasePath));
            File::put($databasePath, '');

            if (File::exists($versionMarkerPath)) {
                File::delete($versionMarkerPath);
            }
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // The desktop server is `php -S`, so every request re-boots Laravel and would otherwise
        // re-run migrate + healing seeders. Do that work once per app version instead.
        if (! $isFirstLaunch && $this->desktopSchemaAlreadyPrepared()) {
            return;
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            app(DatabaseBackupService::class)->repairModuleCatalogIfMissing();
            app(DatabaseBackupService::class)->ensureReportCatalogIfMissing();

            if ($isFirstLaunch || ! User::query()->exists()) {
                Artisan::call('db:seed', ['--force' => true]);
            }

            app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();
            app(DesktopBootstrapService::class)->syncIfNeeded();
            app(GovernmentTablesBootstrapService::class)->enforceOfficialSchedules();

            if (! $isFirstLaunch) {
                app(DesktopCloudBackupService::class)->backupIfNeeded();
            }

            File::put($this->desktopSchemaMarkerPath(), $this->desktopSchemaSignature());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * True when migrations and healing seeders already ran for this app version + migration set.
     */
    private function desktopSchemaAlreadyPrepared(): bool
    {
        $markerPath = $this->desktopSchemaMarkerPath();

        if (! File::exists($markerPath)) {
            return false;
        }

        return trim((string) File::get($markerPath)) === $this->desktopSchemaSignature();
    }

    private function desktopSchemaMarkerPath(): string
    {
        return storage_path('app/.desktop-schema-state');
    }

    private function desktopSchemaSignature(): string
    {
        $version = (string) config('nativephp.version', env('NATIVEPHP_APP_VERSION', '0.0.0'));
        $migrationCount = count(glob(database_path('migrations/*.php')) ?: []);

        return $version.':'.$migrationCount;
    }
}
