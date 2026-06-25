<?php

namespace Database\Seeders;

use App\Models\WithholdingTaxComputation;
use Illuminate\Database\Seeder;

class WithholdingTaxComputationSeeder extends Seeder
{
    public function run(): void
    {
        $computations = [
            1 => 'Regular',
            2 => 'Year End Tax',
            3 => 'Annualized Tax',
        ];

        foreach ($computations as $id => $label) {
            WithholdingTaxComputation::query()->updateOrCreate(
                ['withholding_tax_computation_id' => $id],
                ['withholding_tax_computation' => $label],
            );
        }
    }
}
