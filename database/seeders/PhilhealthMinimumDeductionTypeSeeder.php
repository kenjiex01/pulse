<?php

namespace Database\Seeders;

use App\Models\DeductionType;
use Illuminate\Database\Seeder;

class PhilhealthMinimumDeductionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DeductionType::query()->updateOrCreate(
            ['deduction_type_code' => 'PHIM'],
            [
                'description' => 'Philhealth Minimum',
                'employer_amount' => 100,
                'is_amount_percentage' => true,
                'is_valid_govt_deduction' => true,
                'govt_table_id' => 2,
                'is_active' => true,
            ],
        );
    }
}
