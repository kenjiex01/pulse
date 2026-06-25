<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Program;
use Database\Seeders\Concerns\LoadsSkolarisLookupData;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    use LoadsSkolarisLookupData;

    public function run(): void
    {
        $campuses = $this->skolarisLookupData()['campuses'] ?? [];

        if ($campuses === []) {
            $this->command?->error('No campus records found in Skolaris lookup data.');

            return;
        }

        Program::query()->delete();
        Campus::query()->delete();

        foreach ($campuses as $campus) {
            Campus::query()->create([
                'campus_code' => $campus['campus_code'],
                'campus_name' => $campus['campus_name'],
                'address' => $campus['address'] ?? null,
                'phone' => $campus['phone'] ?? null,
                'email' => $campus['email'] ?? null,
                'website' => $campus['website'] ?? 'https://www.icct.edu.ph',
                'is_active' => (bool) ($campus['is_active'] ?? true),
            ]);
        }

        $this->command?->info('Skolaris campuses seeded: '.count($campuses).' records.');
    }
}
