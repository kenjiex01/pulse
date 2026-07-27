<?php

namespace App\Services;

use App\Models\RawTimekeepingInandout;
use Carbon\CarbonInterface;

class EmployeeAttendanceLogService
{
    /**
     * @return array{dt_datetime: string, is_in: bool}
     */
    public function snapshot(RawTimekeepingInandout $log): array
    {
        return [
            'dt_datetime' => $log->dt_datetime?->format('Y-m-d H:i:s') ?? '',
            'is_in' => (bool) $log->is_in,
        ];
    }

    public function update(
        RawTimekeepingInandout $log,
        CarbonInterface $dateTime,
        bool $isIn,
        ?int $userId = null,
    ): RawTimekeepingInandout {
        $oldValues = $this->snapshot($log);

        if (! $log->is_edited) {
            $log->original_dt_datetime = $log->dt_datetime;
            $log->original_is_in = $log->is_in;
        }

        $log->dt_datetime = $dateTime;
        $log->is_in = $isIn;
        $log->is_edited = true;
        $log->edited_at = now();
        $log->edited_by_id = $userId;
        $log->save();

        SysLogService::record(
            action: 'edit',
            table: 'raw_timekeeping_inandout',
            recordId: (int) $log->timekeeping_inandout_id,
            oldValues: $oldValues,
            newValues: $this->snapshot($log),
            description: 'Edited attendance log #'.$log->timekeeping_inandout_id.' for employee #'.$log->employee_id,
            userId: $userId,
        );

        return $log->fresh();
    }
}
