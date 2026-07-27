<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Idempotent navigation + reference data for desktop installs.
 *
 * Safe to run on upgrade. Uses updateOrCreate / firstOrCreate only —
 * never wipes employees, payroll batches, or user edits.
 */
class DesktopBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            SubModuleSeeder::class,
            DesktopReferenceDataSeeder::class,
        ]);
    }
}
