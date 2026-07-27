<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Role;
use App\Models\SubModule;
use Database\Seeders\DesktopBootstrapSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DesktopBootstrapService
{
    private const VERSION_MARKER = 'app/.desktop-bootstrap-version';

    /**
     * Sync navigation + reference data when the desktop app version changes.
     *
     * Never blocks app launch — failures are logged and retried on next version bump.
     */
    public function syncIfNeeded(): void
    {
        if (! Schema::hasTable('tbl_modules')) {
            return;
        }

        $appVersion = (string) config('nativephp.version', env('NATIVEPHP_APP_VERSION', '0.0.0'));
        $markerPath = storage_path(self::VERSION_MARKER);
        $lastSyncedVersion = File::exists($markerPath) ? trim((string) File::get($markerPath)) : null;

        if ($lastSyncedVersion === $appVersion) {
            return;
        }

        try {
            Artisan::call('db:seed', [
                '--class' => DesktopBootstrapSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $this->ensureAdminMissingModulePermissions();
            $this->ensureAdminMissingSubModulePermissions();

            File::ensureDirectoryExists(dirname($markerPath));
            File::put($markerPath, $appVersion);
        } catch (Throwable $exception) {
            Log::error('Desktop bootstrap failed — app will continue without blocking launch.', [
                'version' => $appVersion,
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    private function ensureAdminMissingModulePermissions(): void
    {
        if (! Schema::hasTable('tbl_role_modules')) {
            return;
        }

        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->first();

        if (! $adminRole) {
            return;
        }

        $parentModuleIds = Module::query()
            ->whereHas('subModules', fn ($query) => $query->where('is_active', true))
            ->pluck('id')
            ->all();

        $missingModuleIds = Module::query()
            ->where('is_active', true)
            ->whereNotNull('route_name')
            ->whereNotIn('id', $parentModuleIds)
            ->whereDoesntHave('roles', fn ($query) => $query->where('roles.id', $adminRole->id))
            ->pluck('id')
            ->all();

        if ($missingModuleIds === []) {
            return;
        }

        $permissions = collect($missingModuleIds)->mapWithKeys(fn (int $moduleId) => [
            $moduleId => $this->fullControlPivot(),
        ])->all();

        $adminRole->modules()->syncWithoutDetaching($permissions);
    }

    private function ensureAdminMissingSubModulePermissions(): void
    {
        if (! Schema::hasTable('tbl_role_sub_modules')) {
            return;
        }

        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->first();

        if (! $adminRole) {
            return;
        }

        $missingSubModuleIds = SubModule::query()
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn ($query) => $query->where('roles.id', $adminRole->id))
            ->pluck('id')
            ->all();

        if ($missingSubModuleIds === []) {
            return;
        }

        $permissions = collect($missingSubModuleIds)->mapWithKeys(fn (int $subModuleId) => [
            $subModuleId => $this->fullControlPivot(),
        ])->all();

        $adminRole->subModules()->syncWithoutDetaching($permissions);
    }

    /** @return array<string, bool> */
    private function fullControlPivot(): array
    {
        return [
            'can_add' => true,
            'can_edit' => true,
            'can_update' => true,
            'can_delete' => true,
            'full_control' => true,
        ];
    }
}
