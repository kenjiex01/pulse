<?php

namespace Database\Seeders;

use App\Models\PayType;
use Illuminate\Database\Seeder;

class PayTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            PayType::DAILY => 'Daily',
            PayType::WEEKLY => 'Weekly',
            PayType::SEMI_MONTHLY => 'Semi-Monthly',
            PayType::MONTHLY => 'Monthly',
        ] as $id => $payType) {
            PayType::query()->updateOrCreate(
                ['pay_type_id' => $id],
                ['pay_type' => $payType],
            );
        }
    }
}
