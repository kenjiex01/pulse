<?php

namespace App\Services;

use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Support\PayrollCalendarModule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollCalendarGeneratorService
{
    public function __construct(
        private readonly PayrollCalendarScheduleService $scheduleService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    public function generate(int $payTypeId, array $input): Collection
    {
        return match ($payTypeId) {
            PayType::DAILY => $this->generateDaily($input),
            PayType::WEEKLY => $this->generateWeekly($input),
            PayType::SEMI_MONTHLY => $this->generateSemiMonthly($input),
            PayType::MONTHLY => $this->generateMonthly($input),
            default => throw new InvalidArgumentException('Unsupported pay type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    private function generateDaily(array $input): Collection
    {
        $payYear = (int) $input['pay_year'];
        $dateFrom = Carbon::parse($input['date_from'])->startOfDay();
        $isRegular = (bool) ($input['is_regular_period'] ?? false);
        $occurrences = isset($input['occurrences']) && $input['occurrences'] !== '' ? (int) $input['occurrences'] : null;
        $dateTo = filled($input['date_to'] ?? null) ? Carbon::parse($input['date_to'])->startOfDay() : null;

        $created = collect();
        $lastPayPeriod = PayrollCalendarModule::nextPayPeriod(PayType::DAILY, $payYear) - 1;
        $currentFrom = $dateFrom->copy();

        if ($dateTo !== null) {
            $totalDays = $dateFrom->diffInDays($dateTo) + 1;
            $interval = 0;

            if ($occurrences !== null && $occurrences > 0) {
                $dateDiff = $totalDays;
                $interval = (int) floor(($dateDiff - ($dateDiff % $occurrences)) / $occurrences) - 1;
                $loopCount = $occurrences;

                if ($dateDiff + 1 === $loopCount) {
                    $interval = 0;
                }

                if ($loopCount > $dateDiff + 1) {
                    $interval = 0;
                    $loopCount = 0;
                }

                if ($interval < 0) {
                    $interval = 0;
                }
            } else {
                $loopCount = $totalDays;
            }

            for ($i = 0; $i < $loopCount; $i++) {
                $currentTo = $currentFrom->copy()->addDays($interval);
                $lastPayPeriod++;
                $period = $this->createPeriod(
                    PayType::DAILY,
                    $payYear,
                    $lastPayPeriod,
                    $currentFrom,
                    $currentTo,
                    $isRegular
                );
                $created->push($period);
                $currentFrom = $currentTo->copy()->addDay();
            }

            return $created;
        }

        if ($occurrences === null || $occurrences < 1) {
            return $created;
        }

        for ($i = 0; $i < $occurrences; $i++) {
            $currentTo = $currentFrom->copy();
            $lastPayPeriod++;
            $period = $this->createPeriod(
                PayType::DAILY,
                $payYear,
                $lastPayPeriod,
                $currentFrom,
                $currentTo,
                $isRegular
            );
            $created->push($period);
            $currentFrom = $currentTo->copy()->addDay();
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    private function generateWeekly(array $input): Collection
    {
        $payYear = (int) $input['pay_year'];
        $dateFrom = Carbon::parse($input['date_from'])->startOfDay();
        $weekDay = (int) $input['week_day'];
        $isRegular = (bool) ($input['is_regular_period'] ?? false);
        $mode = $input['range_mode'] ?? 'date_to';
        $dateTo = filled($input['date_to'] ?? null) ? Carbon::parse($input['date_to'])->startOfDay() : null;
        $occurrences = isset($input['occurrences']) && $input['occurrences'] !== '' ? (int) $input['occurrences'] : null;

        $loopDays = 0;

        if ($mode === 'date_to' && $dateTo !== null) {
            $loopDays = $dateFrom->diffInDays($dateTo) + 1;
        } elseif ($mode === 'occurrences' && $occurrences !== null) {
            $loopDays = $occurrences * 7;
        }

        $created = collect();
        $lastPayPeriod = PayrollCalendarModule::nextPayPeriod(PayType::WEEKLY, $payYear) - 1;
        $currentFrom = $dateFrom->copy();
        $currentTo = $currentFrom->copy();

        for ($i = 0; $i < $loopDays; $i++) {
            if (((int) $currentTo->format('w')) + 1 === $weekDay) {
                $lastPayPeriod++;
                $period = $this->createPeriod(
                    PayType::WEEKLY,
                    $payYear,
                    $lastPayPeriod,
                    $currentFrom,
                    $currentTo,
                    $isRegular
                );
                $created->push($period);
                $currentFrom = $currentTo->copy()->addDay();
            }

            $currentTo = $currentTo->copy()->addDay();
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    private function generateSemiMonthly(array $input): Collection
    {
        return $this->generateFrequencyBased(
            PayType::SEMI_MONTHLY,
            $input,
            (int) ($input['frequency_day_1'] ?? 15),
            $input['frequency_day_2'] ?? null,
            true,
            30
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    private function generateMonthly(array $input): Collection
    {
        $frequencyDay = $input['frequency_day'] ?? null;

        return $this->generateFrequencyBased(
            PayType::MONTHLY,
            $input,
            $frequencyDay !== null && $frequencyDay !== '' ? (int) $frequencyDay : null,
            null,
            false,
            31
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, PayrollCalendar>
     */
    private function generateFrequencyBased(
        int $payTypeId,
        array $input,
        ?int $frequencyOne,
        int|string|null $frequencyTwo,
        bool $requireSecondFrequency,
        int $occurrenceMultiplier
    ): Collection {
        $payYear = (int) $input['pay_year'];
        $dateFrom = Carbon::parse($input['date_from'])->startOfDay();
        $isRegular = (bool) ($input['is_regular_period'] ?? false);
        $mode = $input['range_mode'] ?? 'date_to';
        $dateTo = filled($input['date_to'] ?? null) ? Carbon::parse($input['date_to'])->startOfDay() : null;
        $occurrences = isset($input['occurrences']) && $input['occurrences'] !== '' ? (int) $input['occurrences'] : null;

        if ($requireSecondFrequency) {
            $frequencyTwo = $frequencyTwo === null || $frequencyTwo === '' ? null : (int) $frequencyTwo;
        } else {
            $frequencyTwo = null;
        }

        $loopDays = 0;
        $limitOccurrences = false;
        $maxOccurrences = 0;

        if ($mode === 'date_to' && $dateTo !== null) {
            $loopDays = $dateFrom->diffInDays($dateTo) + 1;
        } elseif ($mode === 'occurrences' && $occurrences !== null) {
            $loopDays = $occurrences * $occurrenceMultiplier;
            $limitOccurrences = true;
            $maxOccurrences = $occurrences;
        }

        $created = collect();
        $lastPayPeriod = PayrollCalendarModule::nextPayPeriod($payTypeId, $payYear) - 1;
        $currentFrom = $dateFrom->copy();
        $currentTo = $currentFrom->copy();
        $saved = 0;

        for ($i = 0; $i < $loopDays; $i++) {
            if (! $limitOccurrences || $saved < $maxOccurrences) {
                $dayOne = $frequencyOne ?? (int) $currentTo->copy()->endOfMonth()->format('j');
                $dayTwo = $frequencyTwo ?? (int) $currentTo->copy()->endOfMonth()->format('j');
                $currentDay = (int) $currentTo->format('j');

                if ($currentDay === $dayOne || ($frequencyTwo !== null && $currentDay === $dayTwo)) {
                    $lastPayPeriod++;
                    $period = $this->createPeriod(
                        $payTypeId,
                        $payYear,
                        $lastPayPeriod,
                        $currentFrom,
                        $currentTo,
                        $isRegular
                    );
                    $created->push($period);
                    $saved++;
                    $currentFrom = $currentTo->copy()->addDay();
                }
            }

            $currentTo = $currentTo->copy()->addDay();
        }

        return $created;
    }

    private function createPeriod(
        int $payTypeId,
        int $payYear,
        int $payPeriod,
        Carbon $dateFrom,
        Carbon $dateTo,
        bool $isRegular
    ): PayrollCalendar {
        return DB::transaction(function () use ($payTypeId, $payYear, $payPeriod, $dateFrom, $dateTo, $isRegular): PayrollCalendar {
            $period = PayrollCalendar::query()->create([
                'pay_type_id' => $payTypeId,
                'pay_year' => $payYear,
                'pay_period' => $payPeriod,
                'dt_from' => $dateFrom,
                'dt_to' => $dateTo,
                'calendar_month' => (int) $dateFrom->format('n'),
                'is_regular_period' => $isRegular ? true : null,
            ]);

            $this->scheduleService->attachDefaultSchedule($period);

            return $period;
        });
    }
}
