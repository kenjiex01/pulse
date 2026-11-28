<?php

namespace Tests\Unit;

use App\Support\SkolarisAttendancePageLoads;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkolarisAttendancePageLoadsTest extends TestCase
{
    #[Test]
    public function test_same_schedule_window_is_one_load_with_hours_counted_once(): void
    {
        $rows = SkolarisAttendancePageLoads::rows([
            [
                'attendance_date' => '2026-11-16',
                'schedule_day' => 'MON',
                'time_in' => '08:00',
                'time_out' => '09:30',
                'actual_time_in' => '08:20',
                'actual_time_out' => '09:40',
                'subject_code' => 'OLHUM001',
                'subject_name' => 'Humanities',
                'section' => 'A1',
                'room' => 'R101',
                'total_hours' => 1.5,
                'schedule' => 'MON 08:00 - 09:30',
            ],
            [
                'attendance_date' => '2026-11-16',
                'schedule_day' => 'MON',
                'time_in' => '08:00',
                'time_out' => '09:30',
                'subject_code' => 'OLENG002',
                'subject_name' => 'English',
                'section' => 'A2',
                'room' => 'R102',
                'total_hours' => 1.5,
                'schedule' => 'MON 08:00 - 09:30',
            ],
            [
                'attendance_date' => '2026-11-16',
                'schedule_day' => 'MON',
                'time_in' => '10:00',
                'time_out' => '11:30',
                'subject_code' => 'OLMATH3',
                'subject_name' => 'Math',
                'section' => 'B1',
                'total_hours' => 1.5,
            ],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('OLHUM001, OLENG002', $rows[0]['subject_code']);
        $this->assertSame('Humanities, English', $rows[0]['subject_name']);
        $this->assertSame('A1, A2', $rows[0]['section']);
        $this->assertSame('R101, R102', $rows[0]['room']);
        $this->assertSame(1.5, $rows[0]['total_hours']);
        $this->assertSame('08:00', $rows[0]['time_in']);
        $this->assertArrayNotHasKey('actual_time_in', $rows[0]);
        $this->assertSame('OLMATH3', $rows[1]['subject_code']);
        $this->assertSame(1.5, $rows[1]['total_hours']);
    }
}
