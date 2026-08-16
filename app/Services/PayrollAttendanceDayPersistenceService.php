<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatchDetail;
use Carbon\CarbonImmutable;

/**
 * Snapshot daily attendance metrics onto a payroll batch detail when Process runs.
 */
class PayrollAttendanceDayPersistenceService
{
    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
    ) {}

    public function replaceForDetail(PayrollBatchDetail $detail): void
    {
        $detail->loadMissing(['payrollBatch.payrollCalendar', 'employee']);

        $calendar = $detail->payrollBatch?->payrollCalendar;
        $employee = $detail->employee;
        $from = $calendar?->dt_from;
        $to = $calendar?->dt_to;

        $this->clearForDetail($detail);

        if ($employee === null || $from === null || $to === null) {
            return;
        }

        $days = $this->attendanceView->computeDaysForPersistence(
            $employee,
            CarbonImmutable::parse($from)->toDateString(),
            CarbonImmutable::parse($to)->toDateString(),
        );

        $employeeId = (int) $detail->employee_id;
        $detailId = (int) $detail->payroll_batch_detail_id;
        $now = now();

        $rows = [];

        foreach ($days as $day) {
            $rows[] = [
                'payroll_batch_detail_id' => $detailId,
                'employee_id' => $employeeId,
                'work_date' => $day['date'],
                'day_type' => (string) ($day['day_type'] ?? 'Regular'),
                'shift_code_id' => $day['shift_code_id'] ?? null,
                'time_in' => $day['time_in_raw'] ?? null,
                'time_out' => $day['time_out_raw'] ?? null,
                'basic' => $day['basic'],
                'excess_hours' => $day['excess_hours'],
                'ot' => $day['ot'],
                'sot' => $day['sot'],
                'ndiff' => $day['ndiff'],
                'ndot' => $day['ndot'],
                'ndsot' => $day['ndsot'],
                'late' => $day['late'],
                'undertime' => $day['undertime'],
                'break_late' => $day['break_late'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            PayrollAttendanceDay::query()->insert($chunk);
        }
    }

    public function clearForDetail(PayrollBatchDetail $detail): void
    {
        PayrollAttendanceDay::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->withTrashed()
            ->forceDelete();
    }
}
