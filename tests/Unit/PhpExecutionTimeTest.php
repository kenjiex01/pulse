<?php

namespace Tests\Unit;

use App\Support\PhpExecutionTime;
use Tests\TestCase;

class PhpExecutionTimeTest extends TestCase
{
    public function test_ensure_at_least_raises_limit_when_current_is_zero(): void
    {
        @ini_set('max_execution_time', '0');

        PhpExecutionTime::ensureAtLeast(300);

        $this->assertGreaterThanOrEqual(300, (int) ini_get('max_execution_time'));
    }
}
