<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class EmployeePayrollPeriod
{
    /**
     * Last calendar date included when computing this employee's payroll.
     * Returns null when Last Payroll Date is before the pay period, so no days in the period are payable.
     */
    public static function effectiveEnd(
        Employee $employee,
        CarbonInterface $periodFrom,
        CarbonInterface $periodTo,
    ): ?CarbonInterface {
        $employee->loadMissing('employmentInformations');
        $current = $employee->employmentInformations;

        if ($current->isEmpty()) {
            return $periodTo;
        }

        $cutoffs = $current
            ->map(fn ($info) => $info->last_payroll_date)
            ->filter();

        if ($cutoffs->isEmpty() || $cutoffs->count() !== $current->count()) {
            return $periodTo;
        }

        $cutoff = CarbonImmutable::parse(
            $cutoffs->min(fn ($date) => $date->toDateString()),
        )->startOfDay();
        $from = CarbonImmutable::parse($periodFrom->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($periodTo->toDateString())->startOfDay();

        if ($cutoff->lt($from)) {
            return null;
        }

        return $cutoff->lt($to) ? $cutoff : $periodTo;
    }
}
