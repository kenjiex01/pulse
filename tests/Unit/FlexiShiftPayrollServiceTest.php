<?php

namespace Tests\Unit;

use App\Models\RawTimekeepingInandout;
use App\Models\ShiftCode;
use App\Services\FlexiShiftPayrollService;
use App\Services\PayrollBreakService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlexiShiftPayrollServiceTest extends TestCase
{
    private FlexiShiftPayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FlexiShiftPayrollService(new PayrollBreakService);
    }

    #[Test]
    public function flexi_shift_pays_actual_hours_without_late_penalty_window(): void
    {
        $shift = new ShiftCode([
            'is_flexi_time' => true,
            'expected_hours_per_day' => 8,
        ]);

        $punches = $this->punchesForDay('2026-06-17', [
            ['10:00:00', true],
            ['18:00:00', false],
        ]);

        $breakdown = $this->service->dailyHoursBreakdown(
            Carbon::parse('2026-06-17')->toImmutable(),
            '10:00:00',
            '18:00:00',
            $shift,
            $punches,
        );

        $this->assertSame(8.0, $breakdown['basic_hours']);
        $this->assertSame(0.0, $breakdown['overtime_hours']);
        $this->assertSame(8.0, $breakdown['actual_hours']);
    }

    #[Test]
    public function flexi_shift_counts_overtime_beyond_expected_hours_per_day(): void
    {
        $shift = new ShiftCode([
            'is_flexi_time' => true,
            'expected_hours_per_day' => 8,
        ]);

        $punches = $this->punchesForDay('2026-06-17', [
            ['09:00:00', true],
            ['18:00:00', false],
        ]);

        $breakdown = $this->service->dailyHoursBreakdown(
            Carbon::parse('2026-06-17')->toImmutable(),
            '09:00:00',
            '18:00:00',
            $shift,
            $punches,
        );

        $this->assertSame(8.0, $breakdown['basic_hours']);
        $this->assertSame(1.0, $breakdown['overtime_hours']);
        $this->assertSame(9.0, $breakdown['actual_hours']);
    }

    #[Test]
    public function flexi_shift_deducts_middle_break_punches_from_rendered_hours(): void
    {
        $shift = new ShiftCode([
            'is_flexi_time' => true,
            'expected_hours_per_day' => 8,
        ]);

        $punches = $this->punchesForDay('2026-06-17', [
            ['09:00:00', true],
            ['12:00:00', false],
            ['13:00:00', true],
            ['18:00:00', false],
        ]);

        $breakdown = $this->service->dailyHoursBreakdown(
            Carbon::parse('2026-06-17')->toImmutable(),
            '09:00:00',
            '18:00:00',
            $shift,
            $punches,
        );

        $this->assertSame(8.0, $breakdown['basic_hours']);
        $this->assertSame(0.0, $breakdown['overtime_hours']);
        $this->assertSame(8.0, $breakdown['actual_hours']);
    }

    #[Test]
    public function flexi_shift_under_expected_hours_pays_only_actual_rendered(): void
    {
        $shift = new ShiftCode([
            'is_flexi_time' => true,
            'expected_hours_per_day' => 8,
        ]);

        $punches = $this->punchesForDay('2026-06-17', [
            ['10:00:00', true],
            ['15:00:00', false],
        ]);

        $breakdown = $this->service->dailyHoursBreakdown(
            Carbon::parse('2026-06-17')->toImmutable(),
            '10:00:00',
            '15:00:00',
            $shift,
            $punches,
        );

        $this->assertSame(5.0, $breakdown['basic_hours']);
        $this->assertSame(0.0, $breakdown['overtime_hours']);
        $this->assertSame(5.0, $breakdown['actual_hours']);
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $rows
     * @return Collection<int, RawTimekeepingInandout>
     */
    private function punchesForDay(string $date, array $rows): Collection
    {
        return collect($rows)->map(function (array $row, int $index) use ($date) {
            $punch = new RawTimekeepingInandout;
            $punch->timekeeping_inandout_id = $index + 1;
            $punch->dt_datetime = Carbon::parse($date.' '.$row[0]);
            $punch->is_in = $row[1];

            return $punch;
        });
    }
}
