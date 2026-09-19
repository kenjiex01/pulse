<?php

namespace Database\Seeders;

use App\Models\LuIcctOffense;
use App\Models\LuIcctOffenseCategory;
use Illuminate\Database\Seeder;

/**
 * ICCT Colleges Code of Offenses — Nature of Offense catalog (effective February 16, 2011).
 */
class IcctOffenseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(IcctOffenseCategorySeeder::class);

        /** @var list<array{0: string, 1: int, 2: string, 3: string}> $rows */
        $rows = require database_path('data/icct_offenses.php');

        foreach ($rows as $index => [$headingRoman, $sectionNumber, $nature, $categoryCode]) {
            $sectionCode = $headingRoman.'.'.$sectionNumber;
            $categoryId = LuIcctOffenseCategory::idForCode($categoryCode);

            LuIcctOffense::withTrashed()->firstOrCreate(
                ['section_code' => $sectionCode],
                [
                    'heading_roman' => $headingRoman,
                    'heading_label' => LuIcctOffense::headingLabel($headingRoman),
                    'section_number' => $sectionNumber,
                    'nature_of_offense' => $nature,
                    'icct_offense_category_id' => $categoryId,
                    'category' => $categoryCode,
                    'sort_order' => $index + 1,
                    'effective_date' => LuIcctOffense::EFFECTIVE_DATE,
                    'is_active' => true,
                ],
            );
        }
    }
}
