<?php

namespace Tests\Unit;

use App\Support\SkolarisLoadSessionTimes;
use Tests\TestCase;

class SkolarisLoadSessionTimesTest extends TestCase
{
    public function test_uses_schedule_when_no_actual_or_log_times(): void
    {
        $times = SkolarisLoadSessionTimes::forTemplateCsv([
            'schedule' => '8:00 AM - 9:30 AM',
            'status' => 'P',
        ]);

        $this->assertSame('8:00 AM', $times['time_in']);
        $this->assertSame('9:30 AM', $times['time_out']);
    }

    public function test_prefers_actual_times_when_present(): void
    {
        $times = SkolarisLoadSessionTimes::forTemplateCsv([
            'schedule' => '8:00 AM - 9:30 AM',
            'actual_time_in' => '08:05',
            'actual_time_out' => '09:28',
        ]);

        $this->assertSame('8:05 AM', $times['time_in']);
        $this->assertSame('9:28 AM', $times['time_out']);
    }

    public function test_storage_format_returns_h_i_s(): void
    {
        $times = SkolarisLoadSessionTimes::forEmployeeLoadEntry([
            'time_in' => '08:00:00',
            'time_out' => '09:30:00',
        ]);

        $this->assertSame('08:00:00', $times['time_in']);
        $this->assertSame('09:30:00', $times['time_out']);
    }
}
