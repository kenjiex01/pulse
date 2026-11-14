<?php

namespace App\Support;

use App\Models\IncomeType;
use App\Models\PayrollCalendar;

final class PayrollPeriodComputation
{
    public const INCOME_CLASS_BASIC = 1;

    public const INCOME_CLASS_ALLOWANCE = 2;

    public static function computesBasic(PayrollCalendar $calendar): bool
    {
        return (bool) $calendar->is_regular_period;
    }

    public static function computesAllowance(PayrollCalendar $calendar): bool
    {
        if ($calendar->is_allowance_period === true) {
            return true;
        }

        if ($calendar->is_allowance_period === false) {
            return false;
        }

        return ! self::computesBasic($calendar);
    }

    public static function isBasicIncomeType(?IncomeType $type): bool
    {
        if ($type === null) {
            return false;
        }

        $classId = (int) ($type->income_class_id ?? 0);

        return $classId === self::INCOME_CLASS_BASIC
            || (bool) $type->is_default_basic
            || $type->income_type_code === 'BASC';
    }

    public static function isAllowanceIncomeType(?IncomeType $type): bool
    {
        if ($type === null) {
            return false;
        }

        return (int) ($type->income_class_id ?? 0) === self::INCOME_CLASS_ALLOWANCE;
    }

    public static function includesIncomeType(?IncomeType $type, PayrollCalendar $calendar): bool
    {
        if ($type === null) {
            return false;
        }

        if (self::isBasicIncomeType($type)) {
            return self::computesBasic($calendar);
        }

        if (self::isAllowanceIncomeType($type)) {
            return self::computesAllowance($calendar);
        }

        return self::computesBasic($calendar) || self::computesAllowance($calendar);
    }
}
