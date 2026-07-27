<?php

namespace App\Services;

use Database\Seeders\GovernmentTablesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GovernmentTablesBootstrapService
{
    /**
     * Re-apply bundled SSS / PhilHealth / Pag-IBIG / WHT schedules on desktop launch.
     *
     * Desktop reinstall keeps pulse.sqlite and the bootstrap version marker, so
     * DesktopBootstrapSeeder may not run — employees and payroll stay as-is, but
     * government reference grids must match the shipped app.
     */
    public function enforceOfficialSchedules(): void
    {
        if (! Schema::hasTable('tbl_govt_table_sss')) {
            return;
        }

        try {
            Artisan::call('db:seed', [
                '--class' => GovernmentTablesSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);
        } catch (Throwable $exception) {
            Log::error('Government tables bootstrap failed — payroll govt deductions may use stale grids.', [
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }
}
