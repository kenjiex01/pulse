<?php

namespace Tests\Unit;

use App\Support\ShiftCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftCodeValidationTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function it_allows_shift_codes_without_break_in_or_break_out(): void
    {
        $validated = ShiftCode::validate([
            'shift_code' => 'TST1',
            'description' => 'Test shift',
            'time_in' => '',
            'time_out' => '',
            'breaks' => [],
        ]);

        $payload = ShiftCode::headerPayload($validated);

        $this->assertSame('00:00', $payload['time_in']);
        $this->assertSame('00:00', $payload['time_out']);
    }

    #[Test]
    public function it_stores_break_in_and_break_out_when_provided(): void
    {
        $validated = ShiftCode::validate([
            'shift_code' => 'TST2',
            'description' => 'Test shift with break window',
            'time_in' => '12:00',
            'time_out' => '13:00',
            'breaks' => [],
        ]);

        $payload = ShiftCode::headerPayload($validated);

        $this->assertSame('12:00', $payload['time_in']);
        $this->assertSame('13:00', $payload['time_out']);
    }
}
