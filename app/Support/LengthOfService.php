<?php

namespace App\Support;

use Carbon\CarbonInterface;

class LengthOfService
{
    public static function since(?CarbonInterface $hireDate, ?CarbonInterface $asOf = null): string
    {
        if ($hireDate === null) {
            return '';
        }

        $start = $hireDate->copy()->startOfDay();
        $end = ($asOf ?? now())->copy()->startOfDay();

        if ($start->greaterThan($end)) {
            return '';
        }

        $diff = $start->diff($end);
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' '.($diff->y === 1 ? 'year' : 'years');
        }

        if ($diff->m > 0) {
            $parts[] = $diff->m.' '.($diff->m === 1 ? 'month' : 'months');
        }

        if ($diff->d > 0 || $parts === []) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'day' : 'days');
        }

        return implode(', ', $parts);
    }
}
