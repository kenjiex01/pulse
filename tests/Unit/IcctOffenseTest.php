<?php

namespace Tests\Unit;

use App\Models\LuIcctOffense;
use Database\Seeders\IcctOffenseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcctOffenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_all_code_of_offenses_entries(): void
    {
        $this->seed(IcctOffenseSeeder::class);

        $this->assertSame(93, LuIcctOffense::query()->count());
        $this->assertDatabaseHas('lu_icct_offenses', [
            'section_code' => 'I.1',
            'category' => 'D',
        ]);
        $this->assertDatabaseHas('lu_icct_offenses', [
            'section_code' => 'II.23',
            'category' => 'A',
        ]);
        $this->assertDatabaseHas('lu_icct_offenses', [
            'section_code' => 'VIII.3',
            'category' => 'D',
        ]);
    }

    public function test_dropdown_label_includes_section_code_and_nature(): void
    {
        $this->seed(IcctOffenseSeeder::class);

        $offense = LuIcctOffense::query()->where('section_code', 'II.8')->firstOrFail();

        $this->assertStringStartsWith('II.8 — ', $offense->dropdownLabel());
        $this->assertStringContainsString('AWOL', $offense->dropdownLabel());
        $this->assertSame(93, count(LuIcctOffense::natureDropdownLabels()));
        $this->assertStringStartsWith('II.8 — ', $offense->compactDropdownLabel());
        $this->assertLessThan(strlen($offense->dropdownLabel()), strlen($offense->compactDropdownLabel()));
    }
}
