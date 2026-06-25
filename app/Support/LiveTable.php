<?php

namespace App\Support;

use Illuminate\Http\Request;

class LiveTable
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public static function perPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : $default;
    }
}
