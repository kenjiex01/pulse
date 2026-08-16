<?php

namespace Tests\Unit;

use App\Support\ShiftCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftCodeValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_requires_duty_schedule_for_fixed_shifts(): void
    {
        $this->expectException(ValidationException::class);

        ShiftCode::validate([
            'shift_code' => 'TST1',
            'description' => 'Test shift',
            'time_in' => '',
            'time_out' => '',
            'breaks' => [],
        ]);
    }

    #[Test]
    public function it_allows_flexi_shifts_without_duty_schedule(): void
    {
        $validated = ShiftCode::validate([
            'shift_code' => 'FLX1',
            'description' => 'Flexi shift',
            'is_flexi_time' => true,
            'expected_hours_per_day' => 8,
            'time_in' => '',
            'time_out' => '',
            'breaks' => [],
        ]);

        $payload = ShiftCode::headerPayload($validated);

        $this->assertSame('00:00', $payload['time_in']);
        $this->assertSame('00:00', $payload['time_out']);
        $this->assertTrue($payload['is_flexi_time']);
    }

    #[Test]
    public function it_stores_duty_schedule_when_provided(): void
    {
        $validated = ShiftCode::validate([
            'shift_code' => 'TST2',
            'description' => '7:00 am to 4:00 pm duty',
            'time_in' => '07:00',
            'time_out' => '16:00',
            'breaks' => [],
        ]);

        $payload = ShiftCode::headerPayload($validated);

        $this->assertSame('07:00', $payload['time_in']);
        $this->assertSame('16:00', $payload['time_out']);
    }

    #[Test]
    public function it_derives_break_minutes_from_break_out_and_break_in(): void
    {
        $minutes = ShiftCode::resolveBreakMinutes([
            'break_out' => '11:00',
            'break_in' => '12:00',
        ]);

        $this->assertSame(60, $minutes);

        $validated = ShiftCode::validate([
            'shift_code' => 'TST3',
            'description' => 'Shift with break window',
            'time_in' => '07:00',
            'time_out' => '16:00',
            'breaks' => [
                ['break_out' => '11:00', 'break_in' => '12:00'],
            ],
        ]);

        $breakRows = ShiftCode::breaksPayload($validated);

        $this->assertSame('11:00', $breakRows[0]['break_out']);
        $this->assertSame('12:00', $breakRows[0]['break_in']);
        $this->assertSame(60, $breakRows[0]['shift_code_break_minute']);
    }
}
