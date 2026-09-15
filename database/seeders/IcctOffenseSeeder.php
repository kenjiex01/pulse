<?php

namespace Database\Seeders;

use App\Models\LuIcctOffense;
use Illuminate\Database\Seeder;

/**
 * ICCT Colleges Code of Offenses — Nature of Offense catalog (effective February 16, 2011).
 */
class IcctOffenseSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{0: string, 1: int, 2: string, 3: string}> $rows */
        $rows = require database_path('data/icct_offenses.php');

        foreach ($rows as $index => [$headingRoman, $sectionNumber, $nature, $category]) {
            $sectionCode = $headingRoman.'.'.$sectionNumber;

            LuIcctOffense::withTrashed()->firstOrCreate(
                ['section_code' => $sectionCode],
                [
                    'heading_roman' => $headingRoman,
                    'heading_label' => LuIcctOffense::headingLabel($headingRoman),
                    'section_number' => $sectionNumber,
                    'nature_of_offense' => $nature,
                    'category' => $category,
                    'sort_order' => $index + 1,
                    'effective_date' => LuIcctOffense::EFFECTIVE_DATE,
                    'is_active' => true,
                ],
            );
        }
    }
}
