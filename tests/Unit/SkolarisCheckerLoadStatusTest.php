<?php

namespace Tests\Unit;

use App\Support\SkolarisCheckerLoadStatus;
use Tests\TestCase;

class SkolarisCheckerLoadStatusTest extends TestCase
{
    public function test_unmarked_checker_row_defaults_to_present_status_code(): void
    {
        $this->assertSame('P', SkolarisCheckerLoadStatus::statusCodeFromChecker('', ''));
        $this->assertSame('P', SkolarisCheckerLoadStatus::statusCodeFromChecker('scheduled', ''));
    }

    public function test_present_with_mode_is_preserved(): void
    {
        $this->assertSame('P (Biometric.)', SkolarisCheckerLoadStatus::statusCodeFromChecker('P', 'Biometric.'));
    }

    public function test_absent_mark_is_not_treated_as_present(): void
    {
        $this->assertFalse(SkolarisCheckerLoadStatus::countsAsPresent('A'));
        $this->assertFalse(SkolarisCheckerLoadStatus::countsAsPresent('Absent'));
    }

    public function test_unmarked_counts_as_present_for_attendance(): void
    {
        $this->assertTrue(SkolarisCheckerLoadStatus::countsAsPresent(''));
        $this->assertTrue(SkolarisCheckerLoadStatus::countsAsPresent('scheduled'));
        $this->assertTrue(SkolarisCheckerLoadStatus::countsAsPresent('P'));
    }
}
