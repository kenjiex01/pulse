<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = [
            ['region_code' => 'NCR', 'region_name' => 'National Capital Region'],
            ['region_code' => '01', 'region_name' => 'Ilocos Region'],
            ['region_code' => '02', 'region_name' => 'Cagayan Valley'],
            ['region_code' => '03', 'region_name' => 'Central Luzon'],
            ['region_code' => '04A', 'region_name' => 'CALABARZON'],
            ['region_code' => '04B', 'region_name' => 'MIMAROPA'],
            ['region_code' => '05', 'region_name' => 'Bicol Region'],
            ['region_code' => '06', 'region_name' => 'Western Visayas'],
            ['region_code' => '07', 'region_name' => 'Central Visayas'],
            ['region_code' => '08', 'region_name' => 'Eastern Visayas'],
            ['region_code' => '09', 'region_name' => 'Zamboanga Peninsula'],
            ['region_code' => '10', 'region_name' => 'Northern Mindanao'],
            ['region_code' => '11', 'region_name' => 'Davao Region'],
            ['region_code' => '12', 'region_name' => 'SOCCSKSARGEN'],
            ['region_code' => '13', 'region_name' => 'Caraga'],
            ['region_code' => 'BARMM', 'region_name' => 'Bangsamoro Autonomous Region in Muslim Mindanao'],
            ['region_code' => 'CAR', 'region_name' => 'Cordillera Administrative Region'],
        ];

        foreach ($regions as $region) {
            Region::firstOrCreate(
                ['region_code' => $region['region_code']],
                [
                    'region_name' => $region['region_name'],
                    'is_active' => true
                ]
            );
        }
    }
}
