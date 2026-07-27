<?php

namespace App\Support;

class SssDeductionTypes
{
    public const PREMIUM = 'SSSP';

    public const MPF = 'SSMP';

    /** @var array<int, string> */
    public const CODES = [self::PREMIUM, self::MPF];
}
