<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Parse Skolaris day codes (MTH, TF, M/W, etc.) into ISO weekday numbers.
 */
final class SkolarisScheduleWeekdays
{
    /** @var array<string, int> */
    private const LETTER_DAYS = ['M' => 1, 'T' => 2, 'W' => 3, 'H' => 4, 'F' => 5, 'S' => 6, 'U' => 7];

    /** @var array<string, int> */
    private const ABBR_DAYS = ['MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6, 'SUN' => 7];

    /** @var array<string, int> */
    private const FULL_DAYS = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
        'friday' => 5, 'saturday' => 6, 'sunday' => 7,
    ];

    /**
     * @return array<int, int> ISO weekday numbers (Mon=1 … Sun=7)
     */
    public static function fromDayField(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $days = [];

        foreach (preg_split('/\s*[\/,]\s*/', trim($raw)) ?: [] as $piece) {
            $piece = trim($piece);

            if ($piece === '') {
                continue;
            }

            $days = array_merge($days, self::tokenToWeekdays($piece));
        }

        return array_values(array_unique(array_filter($days)));
    }

    /**
     * @param  array<int, int>  $weekdays
     * @return array<int, string> Y-m-d dates
     */
    public static function datesInRange(string $dateFrom, string $dateTo, array $weekdays): array
    {
        if ($weekdays === []) {
            return [];
        }

        try {
            $from = CarbonImmutable::parse($dateFrom)->startOfDay();
            $to = CarbonImmutable::parse($dateTo)->startOfDay();
        } catch (\Throwable) {
            return [];
        }

        if ($to->lt($from)) {
            return [];
        }

        $dates = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $immutable = CarbonImmutable::instance($date);

            if (in_array($immutable->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $immutable->toDateString();
            }
        }

        return $dates;
    }

    /**
     * @return array<int, int>
     */
    private static function tokenToWeekdays(string $piece): array
    {
        $lower = strtolower($piece);

        foreach (self::FULL_DAYS as $name => $iso) {
            if (str_starts_with($lower, $name)) {
                return [$iso];
            }
        }

        $compact = strtoupper(preg_replace('/\s+/', '', $piece) ?? '');

        if (strlen($compact) % 3 === 0 && preg_match('/^(MON|TUE|WED|THU|FRI|SAT|SUN)+$/', $compact)) {
            $out = [];

            foreach (str_split($compact, 3) as $abbr) {
                if (isset(self::ABBR_DAYS[$abbr])) {
                    $out[] = self::ABBR_DAYS[$abbr];
                }
            }

            return $out;
        }

        if (preg_match('/^[MTWHFSU]+$/', $compact)) {
            $out = [];

            foreach (str_split($compact) as $letter) {
                if (isset(self::LETTER_DAYS[$letter])) {
                    $out[] = self::LETTER_DAYS[$letter];
                }
            }

            return $out;
        }

        $abbr = substr($compact, 0, 3);

        return isset(self::ABBR_DAYS[$abbr]) ? [self::ABBR_DAYS[$abbr]] : [];
    }
}
