<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['country_name' => 'Philippines', 'country_code' => 'PHL'],
            ['country_name' => 'United States', 'country_code' => 'USA'],
            ['country_name' => 'United Kingdom', 'country_code' => 'GBR'],
            ['country_name' => 'Canada', 'country_code' => 'CAN'],
            ['country_name' => 'Australia', 'country_code' => 'AUS'],
            ['country_name' => 'Japan', 'country_code' => 'JPN'],
            ['country_name' => 'South Korea', 'country_code' => 'KOR'],
            ['country_name' => 'China', 'country_code' => 'CHN'],
            ['country_name' => 'Singapore', 'country_code' => 'SGP'],
            ['country_name' => 'Malaysia', 'country_code' => 'MYS'],
            ['country_name' => 'Thailand', 'country_code' => 'THA'],
            ['country_name' => 'Indonesia', 'country_code' => 'IDN'],
            ['country_name' => 'Vietnam', 'country_code' => 'VNM'],
            ['country_name' => 'India', 'country_code' => 'IND'],
            ['country_name' => 'Saudi Arabia', 'country_code' => 'SAU'],
            ['country_name' => 'United Arab Emirates', 'country_code' => 'ARE'],
            ['country_name' => 'Qatar', 'country_code' => 'QAT'],
            ['country_name' => 'Kuwait', 'country_code' => 'KWT'],
            ['country_name' => 'Other', 'country_code' => 'OTH'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['country_name' => $country['country_name']],
                ['country_code' => $country['country_code'], 'is_active' => true],
            );
        }
    }
}
