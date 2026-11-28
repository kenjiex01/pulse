<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeOvertimeApproval;
use App\Models\EmployeeRequest;
use App\Models\RawTimekeepingInandout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class EmployeeRequestPullService
{
    public const TYPE_JOB_ORDER = 'job_order';

    public const TYPE_AMEND_ATTENDANCE = 'amend_attendance';

    public const TYPE_LEAVE_OF_ABSENCE = 'leave_of_absence';

    public const TYPE_RETURN_TO_WORK = 'return_to_work';

    public function __construct(
        private readonly SkolarisApiService $skolaris,
        private readonly EmployeeAttendanceLogService $attendanceLogs,
    ) {}

    /**
     * @return array{pulled: int, created: int, updated: int, applied: int, skipped: int}
     */
    public function pull(?User $user = null): array
    {
        return $this->importRows($this->skolaris->approvedEmployeeRequests(), $user?->id);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{pulled: int, created: int, updated: int, applied: int, skipped: int}
     */
    public function importRows(array $rows, ?int $userId = null): array
    {
        $summary = [
            'pulled' => 0,
            'created' => 0,
            'updated' => 0,
            'applied' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $summary['skipped']++;

                continue;
            }

            $status = (string) ($row['status']['status_code'] ?? $row['status_code'] ?? '');
            $requestId = (int) ($row['request_id'] ?? 0);

            if ($requestId <= 0 || $status !== 'approved') {
                $summary['skipped']++;

                continue;
            }

            $summary['pulled']++;
            $existing = EmployeeRequest::query()->where('skolaris_request_id', $requestId)->first();
            $record = $existing ?? new EmployeeRequest(['skolaris_request_id' => $requestId]);
            $wasNew = ! $record->exists;

            $record->fill($this->attributesFromRow($row));
            $record->pulled_at = now();
            $record->save();

            $applied = $this->apply($record, $userId);
            $record->employee_id = $applied['employee_id'];
            $record->apply_summary = $applied['summary'];
            $record->applied_at = $applied['applied'] ? now() : null;
            $record->save();

            if ($wasNew) {
                $summary['created']++;
            } else {
                $summary['updated']++;
            }

            if ($applied['applied']) {
                $summary['applied']++;
            }

            SysLogService::record(
                action: $wasNew ? 'create' : 'edit',
                table: 'tbl_employee_requests',
                recordId: (int) $record->employee_request_id,
                newValues: [
                    'request_number' => $record->request_number,
                    'request_type_code' => $record->request_type_code,
                    'apply_summary' => $record->apply_summary,
                ],
                description: 'Pulled approved employee request '.$record->request_number,
                userId: $userId,
            );
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $row): array
    {
        $type = is_array($row['request_type'] ?? null) ? $row['request_type'] : [];

        return [
            'request_number' => (string) ($row['request_number'] ?? ''),
            'request_type_code' => (string) ($type['type_code'] ?? ''),
            'request_type_name' => (string) ($type['type_name'] ?? ''),
            'status_code' => 'approved',
            'prepared_by_employee_number' => $this->blankToNull($row['prepared_by_employee_id'] ?? null),
            'prepared_by_name' => $this->blankToNull($row['prepared_by_name'] ?? null),
            'department' => $this->blankToNull($row['department'] ?? null),
            'campus_name' => $this->blankToNull($row['campus_name'] ?? null),
            'request_date' => $this->blankToNull($row['request_date'] ?? null),
            'approved_at' => $this->blankToNull($row['approved_at'] ?? null),
            'source_updated_at' => $this->blankToNull($row['updated_at'] ?? null),
            'approvers' => $this->approversForStorage($row['approvers'] ?? null),
            'form_payload' => is_array($row['form_payload'] ?? null) ? $row['form_payload'] : [],
            'items' => is_array($row['items'] ?? null) ? $row['items'] : [],
        ];
    }

    /**
     * @return array{applied: bool, employee_id: int|null, summary: string}
     */
    private function apply(EmployeeRequest $request, ?int $userId): array
    {
        return match ($request->request_type_code) {
            self::TYPE_AMEND_ATTENDANCE => $this->applyAmendment($request, $userId),
            self::TYPE_JOB_ORDER => $this->applyJobOrder($request, $userId),
            self::TYPE_LEAVE_OF_ABSENCE => $this->applyLeave($request),
            self::TYPE_RETURN_TO_WORK => $this->applyReturnToWork($request),
            default => [
                'applied' => false,
                'employee_id' => $this->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name)?->employee_id,
                'summary' => 'Saved. This form type has no People360 posting yet.',
            ],
        };
    }

    /**
     * @return array{applied: bool, employee_id: int|null, summary: string}
     */
    private function applyAmendment(EmployeeRequest $request, ?int $userId): array
    {
        $employee = $this->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);
        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $date = $this->dateOnly($payload['attendance_date'] ?? null);

        if ($employee === null) {
            return ['applied' => false, 'employee_id' => null, 'summary' => 'Employee was not found, so the time logs were not changed.'];
        }

        if ($date === null) {
            return ['applied' => false, 'employee_id' => (int) $employee->employee_id, 'summary' => 'The amendment has no attendance date.'];
        }

        $punches = $this->amendmentPunches($date, $payload);

        if ($punches === []) {
            return ['applied' => false, 'employee_id' => (int) $employee->employee_id, 'summary' => 'The amendment has no clock times.'];
        }

        DB::transaction(function () use ($employee, $date, $punches, $userId): void {
            $existing = RawTimekeepingInandout::query()
                ->where('employee_id', $employee->employee_id)
                ->whereDate('dt_datetime', $date)
                ->orderBy('timekeeping_inandout_id')
                ->get();

            foreach ($existing as $log) {
                $this->attendanceLogs->delete($log, $userId);
            }

            foreach ($punches as $punch) {
                $this->attendanceLogs->create($employee, $punch['at'], $punch['is_in'], $userId);
            }
        });

        return [
            'applied' => true,
            'employee_id' => (int) $employee->employee_id,
            'summary' => 'Replaced time logs on '.$date.' with '.count($punches).' amended punch'.(count($punches) === 1 ? '' : 'es').'.',
        ];
    }

    /**
     * @return array{applied: bool, employee_id: int|null, summary: string}
     */
    private function applyJobOrder(EmployeeRequest $request, ?int $userId): array
    {
        $items = is_array($request->items) ? $request->items : [];
        $filed = 0;
        $skipped = 0;
        $primaryEmployeeId = null;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $employee = $this->matchEmployee(
                $item['employee_id'] ?? null,
                $item['employee_name'] ?? null,
            ) ?? $this->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);

            if ($employee === null) {
                $skipped++;

                continue;
            }

            $primaryEmployeeId ??= (int) $employee->employee_id;
            $schedules = is_array($item['work_schedules'] ?? null) ? $item['work_schedules'] : [];

            foreach ($schedules as $schedule) {
                if (! is_array($schedule)) {
                    continue;
                }

                $from = $this->dateOnly($schedule['date_from'] ?? $schedule['date'] ?? null);
                $to = $this->dateOnly($schedule['date_to'] ?? null) ?? $from;

                if ($from === null || $to === null || $to < $from) {
                    $skipped++;

                    continue;
                }

                $cursor = CarbonImmutable::parse($from);
                $end = CarbonImmutable::parse($to);
                $days = 0;

                while ($cursor->lte($end) && $days < 62) {
                    $start = $this->clockOnDate($cursor->toDateString(), (string) ($schedule['time_from'] ?? ''));
                    $finish = $this->clockOnDate($cursor->toDateString(), (string) ($schedule['time_to'] ?? ''));

                    if ($start !== null && $finish !== null) {
                        if ($finish->lessThanOrEqualTo($start)) {
                            $finish = $finish->addDay();
                        }

                        $exists = EmployeeOvertimeApproval::query()
                            ->where('employee_id', $employee->employee_id)
                            ->whereDate('work_date', $cursor->toDateString())
                            ->where('ot_start', $start->format('Y-m-d H:i:s'))
                            ->where('ot_end', $finish->format('Y-m-d H:i:s'))
                            ->exists();

                        if (! $exists) {
                            $approval = EmployeeOvertimeApproval::query()->create([
                                'employee_id' => $employee->employee_id,
                                'work_date' => $cursor->toDateString(),
                                'ot_start' => $start,
                                'ot_end' => $finish,
                            ]);

                            SysLogService::record(
                                action: 'create',
                                table: 'tbl_employee_overtime_approvals',
                                recordId: (int) $approval->employee_overtime_approval_id,
                                newValues: [
                                    'employee_id' => $employee->employee_id,
                                    'work_date' => $cursor->toDateString(),
                                    'ot_start' => $start->format('Y-m-d H:i:s'),
                                    'ot_end' => $finish->format('Y-m-d H:i:s'),
                                    'source' => $request->request_number,
                                ],
                                description: 'Filed overtime from job order '.$request->request_number,
                                userId: $userId,
                            );
                        }

                        $filed++;
                    } else {
                        $skipped++;
                    }

                    $cursor = $cursor->addDay();
                    $days++;
                }
            }
        }

        if ($filed === 0) {
            return [
                'applied' => false,
                'employee_id' => $primaryEmployeeId,
                'summary' => 'No overtime was filed. Match the employee and include a work schedule with start and end times.',
            ];
        }

        return [
            'applied' => true,
            'employee_id' => $primaryEmployeeId,
            'summary' => 'Filed overtime for '.$filed.' day'.($filed === 1 ? '' : 's').($skipped > 0 ? '. '.$skipped.' schedule row'.($skipped === 1 ? '' : 's').' skipped.' : '.'),
        ];
    }

    /**
     * @return array{applied: bool, employee_id: int|null, summary: string}
     */
    private function applyLeave(EmployeeRequest $request): array
    {
        $employee = $this->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);
        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $rows = is_array($payload['leave_rows'] ?? null) ? $payload['leave_rows'] : [];
        $dates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = $this->dateOnly($row['date_of_leave'] ?? null);
            if ($date !== null) {
                $dates[] = $date;
            }
        }

        $type = trim((string) ($payload['leave_type_label'] ?? $payload['leave_type'] ?? 'Leave'));
        $when = $dates !== [] ? implode(', ', $dates) : 'the dates on the form';

        return [
            'applied' => $employee !== null && $dates !== [],
            'employee_id' => $employee?->employee_id,
            'summary' => $type.' recorded for '.$when.'. Leave does not replace time logs.',
        ];
    }

    /**
     * @return array{applied: bool, employee_id: int|null, summary: string}
     */
    private function applyReturnToWork(EmployeeRequest $request): array
    {
        $employee = $this->matchEmployee($request->prepared_by_employee_number, $request->prepared_by_name);
        $payload = is_array($request->form_payload) ? $request->form_payload : [];
        $rows = is_array($payload['absence_rows'] ?? null) ? $payload['absence_rows'] : [];
        $returns = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = $this->dateOnly($row['date_reported_to_work'] ?? null);
            if ($date !== null) {
                $returns[] = $date;
            }
        }

        $when = $returns !== [] ? implode(', ', $returns) : 'the date on the form';

        return [
            'applied' => $employee !== null && $returns !== [],
            'employee_id' => $employee?->employee_id,
            'summary' => 'Return to work recorded for '.$when.'.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{at: CarbonImmutable, is_in: bool}>
     */
    private function amendmentPunches(string $date, array $payload): array
    {
        $slots = [
            ['field' => 'time_in_am', 'is_in' => true],
            ['field' => 'time_out_nn', 'is_in' => false],
            ['field' => 'time_in_nn', 'is_in' => true],
            ['field' => 'time_out_pm', 'is_in' => false],
        ];
        $punches = [];

        foreach ($slots as $slot) {
            $at = $this->clockOnDate($date, (string) ($payload[$slot['field']] ?? ''));
            if ($at === null) {
                continue;
            }
            $punches[] = ['at' => $at, 'is_in' => $slot['is_in']];
        }

        return $punches;
    }

    public function matchEmployee(mixed $number, mixed $name): ?Employee
    {
        $number = trim((string) $number);

        if ($number !== '') {
            $match = Employee::query()
                ->whereRaw('LOWER(employee_number) = ?', [strtolower($number)])
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $name) ?? ''));

        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $last = (string) end($parts);

        if ($last === '') {
            return null;
        }

        return Employee::query()
            ->whereRaw('LOWER(last_name) = ?', [$last])
            ->get()
            ->first(function (Employee $employee) use ($normalized): bool {
                $full = strtolower(trim(preg_replace('/\s+/', ' ', $employee->full_name) ?? ''));

                return $full === $normalized;
            });
    }

    private function clockOnDate(string $date, string $clock): ?CarbonImmutable
    {
        $clock = trim($clock);

        if ($clock === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date.' '.$clock);
        } catch (Throwable) {
            return null;
        }
    }

    private function dateOnly(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
            return $matches[1];
        }

        try {
            return CarbonImmutable::parse($text)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Signature images are base64 and make the list query run out of sort memory.
     *
     * @return list<array<string, mixed>>
     */
    private function approversForStorage(mixed $approvers): array
    {
        if (! is_array($approvers)) {
            return [];
        }

        $stored = [];

        foreach ($approvers as $approver) {
            if (! is_array($approver)) {
                continue;
            }

            unset($approver['signature_data_uri'], $approver['signature_path']);
            $stored[] = $approver;
        }

        return $stored;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
