<?php

namespace Database\Seeders;

use App\Models\GovtTablePhilhealthMinimum;
use Illuminate\Database\Seeder;

class PhilhealthMinimumSeeder extends Seeder
{
    public function run(): void
    {
        GovtTablePhilhealthMinimum::query()->updateOrCreate(
            ['govt_table_philhealth_minimum_id' => 1],
            [
                'employee_amount' => 250.00,
                'employer_amount' => 250.00,
            ],
        );
    }
}
