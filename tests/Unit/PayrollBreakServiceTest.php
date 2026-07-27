<?php

namespace Tests\Unit;

use App\Models\RawTimekeepingInandout;
use App\Services\PayrollBreakService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollBreakServiceTest extends TestCase
{
    #[Test]
    public function payroll_session_uses_first_in_and_last_out(): void
    {
        $service = new PayrollBreakService;
        $punches = $this->punchesForDay('2026-04-17', [
            ['07:24:00', true],
            ['11:51:00', false],
            ['13:00:00', true],
            ['16:33:00', false],
        ]);

        $session = $service->payrollSessionFromPunches($punches);

        $this->assertSame('07:24:00', $session['time_in']);
        $this->assertSame('16:33:00', $session['time_out']);
    }

    #[Test]
    public function break_segments_use_middle_out_to_in_pairs_between_first_in_and_last_out(): void
    {
        $service = new PayrollBreakService;
        $punches = $this->punchesForDay('2026-04-17', [
            ['07:24:00', true],
            ['11:51:00', false],
            ['13:00:00', true],
            ['16:33:00', false],
        ]);

        $segments = $service->breakSegmentsFromPunches($punches);

        $this->assertCount(1, $segments);
        $this->assertSame('11:51:00', $segments[0]['break_out']->format('H:i:s'));
        $this->assertSame('13:00:00', $segments[0]['break_in']->format('H:i:s'));
        $this->assertSame(69, $segments[0]['minutes']);
    }

    #[Test]
    public function duplicate_in_punches_within_five_minutes_do_not_create_break_segments(): void
    {
        $service = new PayrollBreakService;
        $punches = $this->punchesForDay('2026-04-17', [
            ['08:02:00', true],
            ['08:04:00', true],
            ['12:00:00', false],
            ['12:02:00', false],
            ['13:00:00', true],
            ['17:00:00', false],
        ]);

        $session = $service->payrollSessionFromPunches($punches);

        $this->assertSame('08:02:00', $session['time_in']);
        $this->assertSame('17:00:00', $session['time_out']);

        $segments = $service->breakSegmentsFromPunches($punches);

        $this->assertCount(1, $segments);
        $this->assertSame('12:02:00', $segments[0]['break_out']->format('H:i:s'));
        $this->assertSame('13:00:00', $segments[0]['break_in']->format('H:i:s'));
    }

    #[Test]
    public function break_late_billable_minutes_stay_in_minutes_not_whole_hours(): void
    {
        $service = new PayrollBreakService;
        $punches = $this->punchesForDay('2026-04-17', [
            ['07:24:00', true],
            ['11:51:00', false],
            ['13:00:00', true],
            ['16:33:00', false],
        ]);

        $actualBreakMinutes = $service->actualBreakMinutesFromPunches($punches);
        $scheduledBreakMinutes = 60;
        $rawLateMinutes = $actualBreakMinutes - $scheduledBreakMinutes;

        $this->assertSame(69, $actualBreakMinutes);
        $this->assertSame(9, $rawLateMinutes);
        $this->assertSame(
            9,
            TimekeepingPolicySupport::applyBreakTardinessRoundingMinutes($rawLateMinutes, 1),
            'Break late should round in minutes, not zero out sub-hour penalties.',
        );
        $this->assertSame(
            0,
            TimekeepingPolicySupport::applyRoundingMinutes($rawLateMinutes, 1),
            'Hour-based rounding is for OT only and must not be used for break late.',
        );
    }

    #[Test]
    public function hour_rounding_still_applies_to_overtime_minutes(): void
    {
        $overtimeMinutes = 69;

        $this->assertSame(60, TimekeepingPolicySupport::applyRoundingMinutes($overtimeMinutes, 1));
        $this->assertSame(69, TimekeepingPolicySupport::applyBreakTardinessRoundingMinutes($overtimeMinutes, 1));
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $rows
     * @return Collection<int, RawTimekeepingInandout>
     */
    private function punchesForDay(string $date, array $rows): Collection
    {
        $punches = collect();

        foreach ($rows as $index => [$time, $isIn]) {
            $punch = new RawTimekeepingInandout;
            $punch->timekeeping_inandout_id = $index + 1;
            $punch->dt_datetime = Carbon::parse($date.' '.$time);
            $punch->is_in = $isIn;
            $punches->push($punch);
        }

        return $punches;
    }
}
