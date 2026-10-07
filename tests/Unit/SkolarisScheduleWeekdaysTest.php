<?php

namespace Tests\Unit;

use App\Support\SkolarisScheduleWeekdays;
use Tests\TestCase;

class SkolarisScheduleWeekdaysTest extends TestCase
{
    public function test_parses_compact_day_codes(): void
    {
        $this->assertSame([1, 2, 4], SkolarisScheduleWeekdays::fromDayField('MTH'));
        $this->assertSame([2, 5], SkolarisScheduleWeekdays::fromDayField('TF'));
    }

    public function test_dates_in_range_respects_weekdays(): void
    {
        $dates = SkolarisScheduleWeekdays::datesInRange('2026-09-01', '2026-09-07', [1]);

        $this->assertSame(['2026-09-07'], $dates);
    }
}
