<?php

namespace Database\Seeders;

use App\Models\LuIcctOffenseCategory;
use App\Models\LuIcctOffensePenalty;
use Illuminate\Database\Seeder;

/**
 * ICCT Colleges Code of Offenses — Table of Penalties (effective February 16, 2011).
 */
class IcctOffensePenaltySeeder extends Seeder
{
    /** @var array<int, array{A: string, B: string, C: string, D: string}> */
    private const MATRIX = [
        1 => [
            'A' => 'Verbal Reprimand (but recorded for reference)',
            'B' => 'Written Warning',
            'C' => '5 Working Days Suspension',
            'D' => 'Dismissal',
        ],
        2 => [
            'A' => 'Written Warning',
            'B' => '3 Working Days Suspension',
            'C' => 'Dismissal',
        ],
        3 => [
            'A' => '2 Working Days Suspension',
            'B' => '5 Working Days Suspension',
        ],
        4 => [
            'A' => '4 Working Days Suspension',
            'B' => 'Dismissal',
        ],
        5 => [
            'A' => '7 Working Days Suspension',
        ],
        6 => [
            'A' => 'Dismissal',
        ],
    ];

    public function run(): void
    {
        $this->call(IcctOffenseFrequencySeeder::class);
        $this->call(IcctOffenseCategorySeeder::class);

        $expectedKeys = [];

        foreach (self::MATRIX as $ordinal => $categories) {
            foreach ($categories as $categoryCode => $penalty) {
                $expectedKeys[] = $categoryCode.':'.$ordinal;
                $categoryId = LuIcctOffenseCategory::idForCode($categoryCode);

                LuIcctOffensePenalty::query()->updateOrCreate(
                    [
                        'category' => $categoryCode,
                        'frequency_ordinal' => $ordinal,
                    ],
                    [
                        'icct_offense_category_id' => $categoryId,
                        'frequency_label' => LuIcctOffensePenalty::frequencyLabel($ordinal),
                        'penalty' => $penalty,
                        'effective_date' => LuIcctOffensePenalty::EFFECTIVE_DATE,
                        'deleted_at' => null,
                    ],
                );
            }
        }

        LuIcctOffensePenalty::withTrashed()
            ->get()
            ->each(function (LuIcctOffensePenalty $row) use ($expectedKeys): void {
                $key = $row->category.':'.$row->frequency_ordinal;

                if (! in_array($key, $expectedKeys, true)) {
                    $row->forceDelete();
                }
            });
    }
}
