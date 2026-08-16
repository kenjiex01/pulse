<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    public function create(
        Employee $employee,
        CarbonInterface $dateTime,
        bool $isIn,
        ?int $userId = null,
    ): RawTimekeepingInandout {
        $transactionId = RawTimekeepingInandout::query()
            ->where('employee_id', $employee->employee_id)
            ->orderByDesc('timekeeping_inandout_id')
            ->value('timekeeping_transaction_id');

        if ($transactionId === null) {
            $transaction = RawTimekeepingTransaction::query()->create([
                'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
                'dt_from' => $dateTime->copy()->startOfDay(),
                'dt_to' => $dateTime->copy()->endOfDay(),
                'uploaded_by_id' => $userId,
                'dt_uploaded' => now(),
                'filename' => 'Manual Attendance Adjustment',
            ]);
            $transactionId = $transaction->timekeeping_transaction_id;
        }

        $log = RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => $dateTime,
            'is_in' => $isIn,
            'timekeeping_trantype' => 1,
            'reference_number' => null,
            'is_edited' => true,
            'edited_at' => now(),
            'edited_by_id' => $userId,
            'original_dt_datetime' => $dateTime,
            'original_is_in' => $isIn,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'raw_timekeeping_inandout',
            recordId: (int) $log->timekeeping_inandout_id,
            newValues: $this->snapshot($log),
            description: 'Added attendance log #'.$log->timekeeping_inandout_id.' for employee #'.$employee->employee_id,
            userId: $userId,
        );

        return $log;
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

    public function delete(RawTimekeepingInandout $log, ?int $userId = null): void
    {
        $oldValues = $this->snapshot($log);
        $logId = (int) $log->timekeeping_inandout_id;
        $employeeId = (int) $log->employee_id;

        $log->delete();

        SysLogService::record(
            action: 'delete',
            table: 'raw_timekeeping_inandout',
            recordId: $logId,
            oldValues: $oldValues,
            description: 'Deleted attendance log #'.$logId.' for employee #'.$employeeId,
            userId: $userId,
        );
    }

    /**
     * Month calendar of raw punches: first Time In and last Time Out per day.
     *
     * @return array{
     *     year: int,
     *     month: int,
     *     label: string,
     *     prev_year: int,
     *     prev_month: int,
     *     next_year: int,
     *     next_month: int,
     *     weeks: list<list<array{
     *         date: string,
     *         day: int,
     *         in_month: bool,
     *         is_today: bool,
     *         has_logs: bool,
     *         first_in: string|null,
     *         last_out: string|null,
     *         log_count: int
     *     }>>,
     *     days: array<string, array{
     *         date: string,
     *         label: string,
     *         first_in: string|null,
     *         last_out: string|null,
     *         logs: \Illuminate\Support\Collection<int, RawTimekeepingInandout>
     *     }>
     * }
     */
    public function calendarMonth(Employee $employee, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay();
        $today = now()->toDateString();

        $logs = RawTimekeepingInandout::query()
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('dt_datetime', [$gridStart, $gridEnd])
            ->orderBy('dt_datetime')
            ->orderBy('timekeeping_inandout_id')
            ->get();

        /** @var Collection<string, Collection<int, RawTimekeepingInandout>> $byDate */
        $byDate = $logs->groupBy(fn (RawTimekeepingInandout $log) => $log->dt_datetime?->format('Y-m-d') ?? '');

        $days = [];
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $dayLogs = $byDate->get($dateKey, collect());
                $firstIn = $dayLogs->first(fn (RawTimekeepingInandout $log) => (bool) $log->is_in);
                $lastOut = $dayLogs->reverse()->first(fn (RawTimekeepingInandout $log) => ! (bool) $log->is_in);

                $cell = [
                    'date' => $dateKey,
                    'day' => (int) $cursor->day,
                    'in_month' => $cursor->month === $month,
                    'is_today' => $dateKey === $today,
                    'has_logs' => $dayLogs->isNotEmpty(),
                    'first_in' => $firstIn?->dt_datetime?->format('g:i A'),
                    'last_out' => $lastOut?->dt_datetime?->format('g:i A'),
                    'log_count' => $dayLogs->count(),
                ];

                $week[] = $cell;

                if ($dayLogs->isNotEmpty()) {
                    $days[$dateKey] = [
                        'date' => $dateKey,
                        'label' => $cursor->format('l, M j, Y'),
                        'first_in' => $cell['first_in'],
                        'last_out' => $cell['last_out'],
                        'logs' => $dayLogs->values(),
                    ];
                }

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        $prev = $monthStart->copy()->subMonth();
        $next = $monthStart->copy()->addMonth();

        return [
            'year' => $year,
            'month' => $month,
            'label' => $monthStart->format('F Y'),
            'prev_year' => (int) $prev->year,
            'prev_month' => (int) $prev->month,
            'next_year' => (int) $next->year,
            'next_month' => (int) $next->month,
            'weeks' => $weeks,
            'days' => $days,
        ];
    }
}
