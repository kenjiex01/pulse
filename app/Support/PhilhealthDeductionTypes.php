<?php

namespace App\Support;

class PhilhealthDeductionTypes
{
    public const PREMIUM = 'PHIL';

    public const MINIMUM = 'PHIM';

    /** @var array<int, string> */
    public const EXCLUSIVE_CODES = [self::PREMIUM, self::MINIMUM];

    public static function payrollBatchLabel(?string $code, ?string $description): ?string
    {
        if ($code === self::MINIMUM) {
            return 'Philhealth Premium';
        }

        return $description;
    }
}
