<?php

namespace App\Support;

class ShiftCodeDescriptionParser
{
    /**
     * @return array{time_in: string, time_out: string}|null
     */
    public static function parseDutySchedule(string $description): ?array
    {
        $text = strtolower(trim($description));

        if ($text === '' || str_contains($text, 'flexi')) {
            return null;
        }

        $text = preg_replace('/(\d{1,2}):(\d{2})\s*nn\b/', '$1:$2 pm', $text) ?? $text;

        if (! preg_match(
            '/(\d{1,2}):(\d{2})\s*(am|pm)?\s*(?:to|-)\s*(\d{1,2}):(\d{2})\s*(am|pm)?/i',
            $text,
            $matches,
        )) {
            return null;
        }

        $timeIn = self::to24HourTime((int) $matches[1], (int) $matches[2], $matches[3] ?? '');
        $timeOut = self::to24HourTime((int) $matches[4], (int) $matches[5], $matches[6] ?? '');

        if ($timeIn === null || $timeOut === null) {
            return null;
        }

        return [
            'time_in' => $timeIn,
            'time_out' => $timeOut,
        ];
    }

    private static function to24HourTime(int $hour, int $minute, string $meridiem): ?string
    {
        if ($hour < 0 || $hour > 12 || $minute < 0 || $minute > 59) {
            return null;
        }

        $meridiem = strtolower(trim($meridiem));

        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        }

        if ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        if ($meridiem === '' && $hour <= 7) {
            // Descriptions like "7:00 to 4:00 pm" — first time without meridiem is AM.
        } elseif ($meridiem === '' && $hour >= 8 && $hour <= 11) {
            // Assume AM for morning start times when meridiem omitted.
        } elseif ($meridiem === '' && $hour >= 1 && $hour <= 7) {
            // End times like "4:00" with pm on second match only — first stays AM.
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
