<?php

namespace App\Services\Reports;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportBatchOptionsService
{
    /**
     * @return Collection<int, PayrollBatch>
     */
    public function processedBatchesForUser(User $user): Collection
    {
        return PayrollBatch::query()
            ->with(['payrollCalendar.payType', 'status'])
            ->where('payroll_batch_status_id', PayrollBatchStatus::PROCESSED)
            ->where(function (Builder $query) use ($user) {
                $query->whereNull('locked_for_id')
                    ->orWhere('locked_for_id', $user->id);
            })
            ->when(! $user->isAdmin(), function (Builder $query) {
                $query->whereDoesntHave('details.employee', fn (Builder $employeeQuery) => $employeeQuery->where('is_confidential', true));
            })
            ->orderByDesc('batch_no')
            ->get()
            ->filter(fn (PayrollBatch $batch) => $batch->payrollCalendar !== null);
    }

    public function batchLabel(PayrollBatch $batch): string
    {
        $calendar = $batch->payrollCalendar;
        $payType = $calendar?->payType?->pay_type ?? 'Pay Type';
        $period = str_pad((string) ($calendar?->pay_period ?? 0), 3, '0', STR_PAD_LEFT);
        $from = optional($calendar?->dt_from)->format('m/d/Y') ?? '—';
        $to = optional($calendar?->dt_to)->format('m/d/Y') ?? '—';

        return sprintf(
            'Batch No. %s : %s - %s (%s - %s)',
            $batch->formattedBatchNo(),
            $payType,
            $period,
            $from,
            $to,
        );
    }
}
