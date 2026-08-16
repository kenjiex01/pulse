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
     * Processed and posted batches available for register / contribution reports.
     *
     * @return Collection<int, PayrollBatch>
     */
    public function processedBatchesForUser(User $user): Collection
    {
        return $this->batchesForUser($user, [
            PayrollBatchStatus::PROCESSED,
            PayrollBatchStatus::POSTED,
        ]);
    }

    /**
     * @return Collection<int, PayrollBatch>
     */
    public function postedBatchesForUser(User $user): Collection
    {
        return $this->batchesForUser($user, [PayrollBatchStatus::POSTED]);
    }

    /**
     * @param  list<int>  $statusIds
     * @return Collection<int, PayrollBatch>
     */
    private function batchesForUser(User $user, array $statusIds): Collection
    {
        $query = PayrollBatch::query()
            ->select([
                'payroll_batch_id',
                'batch_no',
                'payroll_calendar_id',
                'payroll_batch_status_id',
                'locked_for_id',
            ])
            ->with([
                'payrollCalendar:payroll_calendar_id,pay_type_id,pay_period,dt_from,dt_to,pay_year,calendar_month',
                'payrollCalendar.payType:pay_type_id,pay_type',
                'status:payroll_batch_status_id,payroll_batch_status',
            ])
            ->whereIn('payroll_batch_status_id', $statusIds)
            ->where(function (Builder $query) use ($user) {
                $query->whereNull('locked_for_id')
                    ->orWhere('locked_for_id', $user->id);
            });

        if (! $user->isAdmin()) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('trn_payroll_batch_details as report_batch_details')
                    ->join('tbl_employees as report_batch_employees', 'report_batch_employees.employee_id', '=', 'report_batch_details.employee_id')
                    ->whereColumn('report_batch_details.payroll_batch_id', 'trn_payroll_batches.payroll_batch_id')
                    ->where('report_batch_employees.is_confidential', true);
            });
        }

        return $query
            ->orderByDesc('batch_no')
            ->get()
            ->filter(fn (PayrollBatch $batch) => $batch->payrollCalendar !== null);
    }

    /**
     * Distinct pay years that have at least one posted batch available to the user.
     *
     * @return list<int>
     */
    public function postedPayYearsForUser(User $user): array
    {
        return $this->postedBatchesForUser($user)
            ->map(fn (PayrollBatch $batch) => (int) ($batch->payrollCalendar?->pay_year ?? 0))
            ->filter(fn (int $year) => $year > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function batchLabel(PayrollBatch $batch): string
    {
        $calendar = $batch->payrollCalendar;
        $payType = $calendar?->payType?->pay_type ?? 'Pay Type';
        $period = str_pad((string) ($calendar?->pay_period ?? 0), 3, '0', STR_PAD_LEFT);
        $from = optional($calendar?->dt_from)->format('m/d/Y') ?? '—';
        $to = optional($calendar?->dt_to)->format('m/d/Y') ?? '—';

        $label = sprintf(
            'Batch No. %s : %s - %s (%s - %s)',
            $batch->formattedBatchNo(),
            $payType,
            $period,
            $from,
            $to,
        );

        if ((int) $batch->payroll_batch_status_id === PayrollBatchStatus::POSTED) {
            $label .= ' — Posted';
        }

        return $label;
    }
}
