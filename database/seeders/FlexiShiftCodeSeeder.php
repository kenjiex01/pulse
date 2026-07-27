<?php

namespace Database\Seeders;

use App\Models\ShiftCode;
use Illuminate\Database\Seeder;

class FlexiShiftCodeSeeder extends Seeder
{
    public function run(): void
    {
        ShiftCode::query()->updateOrCreate(
            ['shift_code' => 'FLXI'],
            [
                'description' => 'Flexi-time (8 hrs/day)',
                'time_in' => '00:00',
                'time_out' => '00:00',
                'is_flexi_time' => true,
                'expected_hours_per_day' => 8,
            ],
        );
    }
}
