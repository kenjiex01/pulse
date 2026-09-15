<?php

namespace Database\Seeders;

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
        foreach (self::MATRIX as $ordinal => $categories) {
            foreach ($categories as $category => $penalty) {
                LuIcctOffensePenalty::query()->updateOrCreate(
                    [
                        'category' => $category,
                        'frequency_ordinal' => $ordinal,
                    ],
                    [
                        'frequency_label' => LuIcctOffensePenalty::frequencyLabel($ordinal),
                        'penalty' => $penalty,
                        'effective_date' => LuIcctOffensePenalty::EFFECTIVE_DATE,
                    ],
                );
            }
        }
    }
}
