<?php

namespace Tests\Unit;

use App\Models\LuIcctOffensePenalty;
use Database\Seeders\IcctOffensePenaltySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IcctOffensePenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IcctOffensePenaltySeeder::class);
    }

    #[Test]
    public function seeds_full_icct_penalty_matrix(): void
    {
        $this->assertSame(13, LuIcctOffensePenalty::query()->count());

        $this->assertSame(
            'Verbal Reprimand (but recorded for reference)',
            LuIcctOffensePenalty::penaltyFor('A', 1),
        );

        $this->assertSame(
            'Dismissal',
            LuIcctOffensePenalty::penaltyFor('D', 1),
        );

        $this->assertSame(
            '7 Working Days Suspension',
            LuIcctOffensePenalty::penaltyFor('A', 5),
        );

        $this->assertSame('Dismissal', LuIcctOffensePenalty::penaltyFor('D', 2));
        $this->assertSame('Dismissal', LuIcctOffensePenalty::penaltyFor('C', 3));
        $this->assertSame('Dismissal', LuIcctOffensePenalty::penaltyFor('B', 6));
    }

    #[Test]
    public function distinct_penalties_match_dropdown_options(): void
    {
        $penalties = LuIcctOffensePenalty::distinctPenalties();

        $this->assertContains('Written Warning', $penalties);
        $this->assertContains('Dismissal', $penalties);
        $this->assertCount(8, $penalties);
    }
}
