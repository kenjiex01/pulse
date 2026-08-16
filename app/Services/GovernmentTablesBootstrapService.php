<?php

namespace App\Services;

use Database\Seeders\GovernmentTablesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GovernmentTablesBootstrapService
{
    private const VERSION_MARKER = 'app/.desktop-govt-tables-version';

    /**
     * Re-apply bundled SSS / PhilHealth / Pag-IBIG / WHT schedules when the app version changes.
     *
     * Running the full seeder on every launch was noticeably slow on Windows (Defender + SQLite).
     * Desktop reinstall keeps pulse.sqlite — bump NATIVEPHP_APP_VERSION or delete the marker to re-sync.
     */
    public function enforceOfficialSchedules(): void
    {
        if (! Schema::hasTable('tbl_govt_table_sss')) {
            return;
        }

        $appVersion = (string) config('nativephp.version', env('NATIVEPHP_APP_VERSION', '0.0.0'));
        $markerPath = storage_path(self::VERSION_MARKER);
        $lastSyncedVersion = File::exists($markerPath) ? trim((string) File::get($markerPath)) : null;

        if ($lastSyncedVersion === $appVersion) {
            return;
        }

        $sync = function () use ($appVersion, $markerPath): void {
            try {
                Artisan::call('db:seed', [
                    '--class' => GovernmentTablesSeeder::class,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                File::ensureDirectoryExists(dirname($markerPath));
                File::put($markerPath, $appVersion);
            } catch (Throwable $exception) {
                Log::error('Government tables bootstrap failed — payroll govt deductions may use stale grids.', [
                    'message' => $exception->getMessage(),
                ]);

                report($exception);
            }
        };

        if (app()->runningInConsole()) {
            $sync();

            return;
        }

        dispatch($sync)->afterResponse();
    }
}
