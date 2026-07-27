<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawTimekeepingInandout;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EmployeeLoadAttendanceMatcher
{
    public function __construct(private readonly EmployeeLoadPayrollService $payroll) {}

    /**
     * Match and persist Time In / Time Out from attendance logs for the given entries.
     *
     * @param  iterable<int, RawEmployeeLoadEntry>  $entries
     */
    public function applyToEntries(Employee $employee, iterable $entries): int
    {
        $collection = Collection::make($entries)
            ->filter(fn ($entry) => $entry instanceof RawEmployeeLoadEntry)
            ->values();

        if ($collection->isEmpty()) {
            return 0;
        }

        $updated = 0;

        foreach ($collection->groupBy(fn (RawEmployeeLoadEntry $entry) => optional($entry->session_date)->format('Y-m-d') ?: '') as $sessionDate => $dayEntries) {
            if ($sessionDate === '') {
                continue;
            }

            $logs = RawTimekeepingInandout::query()
                ->where('employee_id', $employee->employee_id)
                ->whereDate('dt_datetime', $sessionDate)
                ->orderBy('dt_datetime')
                ->orderBy('timekeeping_inandout_id')
                ->get();

            $usedLogIds = [];

            $ordered = $dayEntries
                ->sortBy(function (RawEmployeeLoadEntry $entry) {
                    return $this->payroll->parseScheduleStart($entry->class_schedule) ?? '99:99:99';
                })
                ->values();

            foreach ($ordered as $entry) {
                $matched = $this->matchSession(
                    $sessionDate,
                    $entry->class_schedule,
                    $logs,
                    $usedLogIds,
                );

                $nextIn = $matched['time_in'];
                $nextOut = $matched['time_out'];

                if ($entry->time_in === $nextIn && $entry->time_out === $nextOut) {
                    continue;
                }

                $entry->time_in = $nextIn;
                $entry->time_out = $nextOut;
                $entry->save();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $logs
     * @param  array<int, int>  $usedLogIds
     * @return array{time_in: ?string, time_out: ?string}
     */
    public function matchSession(
        string $sessionDate,
        ?string $classSchedule,
        Collection $logs,
        array &$usedLogIds = [],
    ): array {
        $scheduleStart = $this->payroll->parseScheduleStart($classSchedule);
        $scheduleEnd = $this->payroll->parseScheduleEnd($classSchedule);

        if ($scheduleStart === null || $scheduleEnd === null || $logs->isEmpty()) {
            return ['time_in' => null, 'time_out' => null];
        }

        $day = CarbonImmutable::parse($sessionDate);
        $scheduledInAt = $day->setTimeFromTimeString($scheduleStart);
        $scheduledOutAt = $day->setTimeFromTimeString($scheduleEnd);

        $inLog = $this->pickClosestLog(
            $logs,
            $usedLogIds,
            expectIn: true,
            target: $scheduledInAt,
            earliest: $scheduledInAt->subHours(3),
            latest: $scheduledOutAt,
        );

        if ($inLog !== null) {
            $usedLogIds[] = (int) $inLog->timekeeping_inandout_id;
        }

        $outEarliest = $inLog?->dt_datetime
            ? CarbonImmutable::parse($inLog->dt_datetime)->addMinute()
            : $scheduledInAt;

        $outLog = $this->pickClosestLog(
            $logs,
            $usedLogIds,
            expectIn: false,
            target: $scheduledOutAt,
            earliest: $outEarliest,
            latest: $scheduledOutAt->addHours(3),
        );

        if ($outLog !== null) {
            $usedLogIds[] = (int) $outLog->timekeeping_inandout_id;
        }

        return [
            'time_in' => $inLog?->dt_datetime?->format('H:i:s'),
            'time_out' => $outLog?->dt_datetime?->format('H:i:s'),
        ];
    }

    /**
     * @param  Collection<int, RawTimekeepingInandout>  $logs
     * @param  array<int, int>  $usedLogIds
     */
    private function pickClosestLog(
        Collection $logs,
        array $usedLogIds,
        bool $expectIn,
        CarbonImmutable $target,
        CarbonImmutable $earliest,
        CarbonImmutable $latest,
    ): ?RawTimekeepingInandout {
        $best = null;
        $bestDistance = null;

        foreach ($logs as $log) {
            $logId = (int) $log->timekeeping_inandout_id;

            if (in_array($logId, $usedLogIds, true)) {
                continue;
            }

            if ((bool) $log->is_in !== $expectIn || $log->dt_datetime === null) {
                continue;
            }

            $at = CarbonImmutable::parse($log->dt_datetime);

            if ($at->lt($earliest) || $at->gt($latest)) {
                continue;
            }

            $distance = abs($at->diffInSeconds($target));

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $log;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
