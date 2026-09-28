<?php

namespace App\Services;

use App\Models\PayrollBatch;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TimekeepingMemoPayrollPostService
{
    public function __construct(
        private readonly TimekeepingMemoAttendanceService $attendanceService,
        private readonly TimekeepingMemoSendService $sendService,
    ) {}

    /**
     * @return array{sent: int, errors: list<string>}
     */
    public function sendForPostedBatch(PayrollBatch $batch, User $sender): array
    {
        @set_time_limit(300);

        $batch->loadMissing(['payrollCalendar', 'details.employee']);

        $calendar = $batch->payrollCalendar;
        if ($calendar === null || $calendar->dt_from === null || $calendar->dt_to === null) {
            return ['sent' => 0, 'errors' => []];
        }

        $dateFrom = $calendar->dt_from->format('Y-m-d');
        $dateTo = $calendar->dt_to->format('Y-m-d');

        $setups = TimekeepingMemoSetup::query()->get()->keyBy('violation_type');

        $sent = 0;
        $errors = [];

        foreach ($batch->details as $detail) {
            $employee = $detail->employee;
            if ($employee === null) {
                continue;
            }

            foreach (TimekeepingMemoSetup::TYPES as $violationType) {
                /** @var TimekeepingMemoSetup|null $setup */
                $setup = $setups->get($violationType);
                if ($setup === null || $setup->company_document_form_id === null) {
                    continue;
                }

                $requiredCount = max(1, (int) ($setup->occurrence_count ?? 1));
                $violationCount = $this->attendanceService->violationCountForEmployee(
                    $employee,
                    $dateFrom,
                    $dateTo,
                    $violationType,
                );

                if ($violationCount < $requiredCount) {
                    continue;
                }

                try {
                    $this->sendService->sendForEmployee(
                        $employee,
                        $dateFrom,
                        $dateTo,
                        $violationType,
                        null,
                        $sender,
                    );
                    $sent++;
                } catch (RuntimeException $exception) {
                    $errors[] = trim($employee->full_name).' ('.TimekeepingMemoSetup::labelForType($violationType).'): '.$exception->getMessage();
                }
            }
        }

        if ($errors !== []) {
            Log::warning('Payroll post auto-memo finished with errors.', [
                'payroll_batch_id' => $batch->payroll_batch_id,
                'sent' => $sent,
                'errors' => $errors,
            ]);
        }

        return [
            'sent' => $sent,
            'errors' => $errors,
        ];
    }
}
