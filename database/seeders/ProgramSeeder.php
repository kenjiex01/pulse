<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Program;
use Database\Seeders\Concerns\LoadsSkolarisLookupData;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    use LoadsSkolarisLookupData;

    public function run(): void
    {
        $programs = $this->skolarisLookupData()['programs'] ?? [];
        $campusesByCode = Campus::query()->pluck('campus_id', 'campus_code');

        if ($campusesByCode->isEmpty()) {
            $this->command?->error('No campuses found. Please run CampusSeeder first.');

            return;
        }

        if ($programs === []) {
            $this->command?->error('No program records found in Skolaris lookup data.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($programs as $program) {
            $campusId = $campusesByCode->get($program['campus_code']);

            if (! $campusId) {
                $skipped++;

                continue;
            }

            $record = Program::withTrashed()->updateOrCreate(
                [
                    'campus_id' => $campusId,
                    'program_code' => $program['program_code'],
                ],
                [
                    'program_name' => $program['program_name'],
                    'is_active' => (bool) ($program['is_active'] ?? true),
                ],
            );

            if ($record->trashed()) {
                $record->restore();
            }

            $created++;
        }

        $this->command?->info("Skolaris programs seeded: {$created} records.");

        if ($skipped > 0) {
            $this->command?->warn("Skipped {$skipped} programs with unknown campus codes.");
        }
    }
}
