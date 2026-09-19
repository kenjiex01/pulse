<?php

namespace Database\Seeders;

use App\Models\LuIcctOffenseFrequency;
use Illuminate\Database\Seeder;

class IcctOffenseFrequencySeeder extends Seeder
{
    /** @var array<int, string> */
    private const DEFAULTS = [
        1 => 'First Offense',
        2 => 'Second Offense',
        3 => 'Third Offense',
        4 => 'Fourth Offense',
        5 => 'Fifth Offense',
        6 => 'Sixth Offense',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $ordinal => $label) {
            LuIcctOffenseFrequency::withTrashed()->updateOrCreate(
                ['frequency_ordinal' => $ordinal],
                [
                    'label' => $label,
                    'sort_order' => $ordinal,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
        }
    }
}
