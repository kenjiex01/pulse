<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollBatchDetail;
use Carbon\CarbonImmutable;

/**
 * Hours and amount for manually added Late (LTDE) / Undertime (UTDE) deduction lines.
 * The entered time is that day's log for the line only; raw biometric logs are not changed.
 */
class ManualAttendanceDeductionService
{
    public const LATE = 'LTDE';

    public const UNDERTIME = 'UTDE';

    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
        private readonly EmployeeShiftResolver $shiftResolver,
        private readonly EmployeeSalaryResolverService $salaryResolver,
    ) {}

    public static function isAttendanceCode(?string $code): bool
    {
        return in_array($code, [self::LATE, self::UNDERTIME], true);
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string|null,
     *     shift_label: string|null,
     *     computed_hours: float|null,
     *     hours: float|null,
     *     minutes: int|null,
     *     hourly_rate: float|null,
     *     amount: float|null
     * }
     */
    public function preview(PayrollBatchDetail $detail, string $code, ?string $referenceDate, ?string $time, ?float $hours = null): array
    {
        $detail->loadMissing(['payrollBatch.payrollCalendar', 'employee']);

        return $this->previewFor(
            $detail->employee,
            (int) ($detail->payrollBatch?->payrollCalendar?->pay_type_id ?? 0),
            $code,
            $referenceDate,
            $time,
            $hours,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function previewFor(?Employee $employee, int $payTypeId, string $code, ?string $referenceDate, ?string $time, ?float $hours = null): array
    {
        $result = [
            'ok' => false,
            'message' => null,
            'shift_label' => null,
            'computed_hours' => null,
            'hours' => null,
            'minutes' => null,
            'hourly_rate' => null,
            'amount' => null,
        ];

        if (! self::isAttendanceCode($code)) {
            $result['message'] = 'Only Late (LTDE) and Undertime (UTDE) are computed automatically.';

            return $result;
        }

        if ($referenceDate === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate) !== 1) {
            $result['message'] = 'Select the reference (work) date first.';

            return $result;
        }

        $employee?->loadMissing(['timekeepingSetup.policy', 'timekeepingSetup.shiftCode.breaks']);
        $date = CarbonImmutable::parse($referenceDate)->startOfDay();

        if ($employee === null) {
            $result['message'] = 'Employee not found.';

            return $result;
        }

        $employeeId = (int) $employee->employee_id;
        $this->shiftResolver->loadOverridesForRange($employeeId, $date, $date);
        $shift = $this->shiftResolver->forDate($employeeId, $date, $employee->timekeepingSetup?->shiftCode);

        if ($shift !== null) {
            $result['shift_label'] = trim($shift->shift_code.' ('.substr((string) $shift->time_in, 0, 5).'–'.substr((string) $shift->time_out, 0, 5).')');
        }

        $time = $this->normalizeTime($time);

        if ($time !== null) {
            if ($shift === null || blank($shift->time_in) || blank($shift->time_out)) {
                $result['message'] = 'The employee has no shift on this date, so hours cannot be computed from the time. Enter the hours manually.';
            } else {
                $metrics = $code === self::LATE
                    ? $this->attendanceView->metricsForTimes($employeeId, $date, $time, substr((string) $shift->time_out, 0, 5), $employee->timekeepingSetup?->policy, $shift)
                    : $this->attendanceView->metricsForTimes($employeeId, $date, substr((string) $shift->time_in, 0, 5), $time, $employee->timekeepingSetup?->policy, $shift);

                $result['computed_hours'] = round((float) ($code === self::LATE ? $metrics['late'] : $metrics['undertime']), 4);
            }
        }

        $usedHours = $hours !== null && $hours > 0 ? round($hours, 4) : $result['computed_hours'];
        $result['hours'] = $usedHours;
        $result['minutes'] = $usedHours !== null ? (int) round($usedHours * 60) : null;

        $salaries = $payTypeId > 0
            ? $this->salaryResolver->salariesForPeriod($employeeId, $payTypeId, $date, $date)
            : collect();
        $salary = $salaries->isNotEmpty()
            ? ($this->salaryResolver->salaryEffectiveOnDate($salaries, $date) ?? $salaries->last())
            : null;
        $rate = $salary?->hourlyRate();

        if ($rate === null || $rate <= 0) {
            $result['message'] = 'The employee has no salary rate on this date, so the amount cannot be computed.';

            return $result;
        }

        $result['hourly_rate'] = round($rate, 4);
        $result['amount'] = $usedHours !== null ? round($usedHours * $rate, 2) : null;
        $result['ok'] = true;

        if ($result['message'] === null && $time !== null && $result['computed_hours'] !== null && $result['computed_hours'] <= 0) {
            $result['message'] = $code === self::LATE
                ? 'No late minutes for this time in (within the shift start / grace period).'
                : 'No undertime for this time out (at or after the shift end).';
        }

        return $result;
    }

    public function normalizeTime(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
