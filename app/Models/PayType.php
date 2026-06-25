<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayType extends Model
{
    public const DAILY = 1;

    public const WEEKLY = 2;

    public const SEMI_MONTHLY = 3;

    public const MONTHLY = 4;

    public $timestamps = false;

    protected $table = 'lu_pay_types';

    protected $primaryKey = 'pay_type_id';

    protected $fillable = [
        'pay_type',
    ];

    public static function autoDaysPerPeriod(int $payTypeId): ?float
    {
        return match ($payTypeId) {
            self::DAILY => 1.0,
            default => null,
        };
    }

    public static function requiresDaysPerPeriodInput(int $payTypeId): bool
    {
        return in_array($payTypeId, [self::WEEKLY, self::SEMI_MONTHLY, self::MONTHLY], true);
    }
}
