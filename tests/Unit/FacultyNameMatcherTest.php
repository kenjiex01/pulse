<?php

namespace Tests\Unit;

use App\Support\FacultyNameMatcher;
use Tests\TestCase;

class FacultyNameMatcherTest extends TestCase
{
    public function test_matches_married_name_variants(): void
    {
        $this->assertTrue(FacultyNameMatcher::namesLikelySame(
            'Livine Joy Aboguin-Estoya',
            'Livine Joy Plagata Aboguin',
        ));
    }

    public function test_rejects_unrelated_names(): void
    {
        $this->assertFalse(FacultyNameMatcher::namesLikelySame(
            'Jane Doe',
            'Livine Joy Plagata Aboguin',
        ));
    }
}
