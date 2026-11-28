<?php

namespace Tests\Unit;

use App\Support\LengthOfService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LengthOfServiceTest extends TestCase
{
    #[Test]
    public function test_counts_years_months_and_days_since_hire_date(): void
    {
        Carbon::setTestNow('2026-11-28');

        $this->assertSame(
            '2 years, 2 months, 13 days',
            LengthOfService::since(Carbon::parse('2024-09-15')),
        );
        $this->assertSame('1 year', LengthOfService::since(Carbon::parse('2025-11-28')));
        $this->assertSame('1 month', LengthOfService::since(Carbon::parse('2026-10-28')));
        $this->assertSame('1 day', LengthOfService::since(Carbon::parse('2026-11-27')));
        $this->assertSame('0 days', LengthOfService::since(Carbon::parse('2026-11-28')));
        $this->assertSame('', LengthOfService::since(null));
        $this->assertSame('', LengthOfService::since(Carbon::parse('2026-12-01')));

        Carbon::setTestNow();
    }
}
