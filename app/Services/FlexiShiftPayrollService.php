<?php

namespace App\Services;

use App\Models\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FlexiShiftPayrollService
{
    public function __construct(
        private readonly PayrollBreakService $breakPayroll,
    ) {}

    public function isFlexiShift(?ShiftCode $shiftCode): bool
    {
        return $shiftCode !== null && (bool) $shiftCode->is_flexi_time;
    }

    public function expectedHoursPerDay(?ShiftCode $shiftCode): float
    {
        if (! $this->isFlexiShift($shiftCode)) {
            return 0.0;
        }

        $hours = (float) ($shiftCode->expected_hours_per_day ?? 8);

        return $hours > 0 ? $hours : 8.0;
    }

    /**
     * @return array{basic_hours: float, overtime_hours: float, actual_hours: float}
     */
    public function dailyHoursBreakdown(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        ?ShiftCode $shiftCode,
        Collection $dayPunches,
    ): array {
        $empty = ['basic_hours' => 0.0, 'overtime_hours' => 0.0, 'actual_hours' => 0.0];

        if (! $this->isFlexiShift($shiftCode) || $timeIn === null || $timeIn === '' || $timeOut === null || $timeOut === '') {
            return $empty;
        }

        $actualHours = $this->actualWorkedHours($sessionDate, $timeIn, $timeOut, $dayPunches);

        if ($actualHours <= 0) {
            return $empty;
        }

        $expected = $this->expectedHoursPerDay($shiftCode);
        $basicHours = min($actualHours, $expected);
        $overtimeHours = max(0.0, round($actualHours - $expected, 4));

        return [
            'basic_hours' => round($basicHours, 4),
            'overtime_hours' => $overtimeHours,
            'actual_hours' => round($actualHours, 4),
        ];
    }

    public function actualWorkedHours(
        CarbonImmutable $sessionDate,
        ?string $timeIn,
        ?string $timeOut,
        Collection $dayPunches,
    ): float {
        if ($timeIn === null || $timeIn === '' || $timeOut === null || $timeOut === '') {
            return 0.0;
        }

        try {
            $startAt = $sessionDate->setTimeFromTimeString($timeIn);
            $endAt = $sessionDate->setTimeFromTimeString($timeOut);

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt = $endAt->addDay();
            }

            $grossMinutes = (int) $startAt->diffInMinutes($endAt);
            $breakMinutes = $this->breakPayroll->consumedBreakMinutesFromPunches($dayPunches);

            return max(0.0, round(($grossMinutes - $breakMinutes) / 60, 4));
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
