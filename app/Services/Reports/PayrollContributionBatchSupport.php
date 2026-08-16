<?php

namespace App\Services\Reports;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollContributionBatchSupport
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
    ) {}

    /**
     * @param  list<int>  $batchIds
     * @param  list<string>  $with
     * @return Collection<int, PayrollBatch>
     */
    public function loadProcessedBatches(array $batchIds, array $with = []): Collection
    {
        $defaultWith = [
            'payrollCalendar.payType',
            'details.employee',
            'details.deductions.deductionType',
        ];

        return PayrollBatch::query()
            ->with(array_values(array_unique(array_merge($defaultWith, $with))))
            ->whereIn('payroll_batch_id', $batchIds)
            ->whereIn('payroll_batch_status_id', [
                PayrollBatchStatus::PROCESSED,
                PayrollBatchStatus::POSTED,
            ])
            ->get();
    }

    /**
     * @param  Collection<int, PayrollBatch>  $batches
     */
    public function assertSamePayMonthAndYear(Collection $batches): void
    {
        $keys = $batches
            ->map(function (PayrollBatch $batch) {
                $calendar = $batch->payrollCalendar;

                return sprintf('%s-%s', $calendar?->pay_year, $calendar?->calendar_month);
            })
            ->unique()
            ->values();

        if ($keys->count() > 1) {
            throw ValidationException::withMessages([
                'payroll_batch_ids' => 'Selected payroll batches must share the same pay month and pay year.',
            ]);
        }
    }

    /**
     * @param  Collection<int, PayrollBatch>  $batches
     * @return array{
     *     period_label: string,
     *     pay_year: int,
     *     calendar_month: int,
     *     batch_labels: array<int, string>,
     *     batch_count: int
     * }
     */
    public function batchMeta(Collection $batches): array
    {
        $calendar = $batches->first()?->payrollCalendar;
        $payYear = (int) ($calendar?->pay_year ?? 0);
        $calendarMonth = (int) ($calendar?->calendar_month ?? 0);

        $periodLabel = $payYear > 0 && $calendarMonth > 0
            ? date('F Y', mktime(0, 0, 0, $calendarMonth, 1, $payYear))
            : '';

        return [
            'period_label' => $periodLabel,
            'pay_year' => $payYear,
            'calendar_month' => $calendarMonth,
            'batch_labels' => $batches
                ->map(fn (PayrollBatch $batch) => $this->batchOptions->batchLabel($batch))
                ->values()
                ->all(),
            'batch_count' => $batches->count(),
        ];
    }

    public function detailIsVisible(PayrollBatchDetail $detail, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! (bool) ($detail->employee?->is_confidential ?? false);
    }

    /**
     * @return array{company_name: string, company_address: string}
     */
    public function companyMeta(): array
    {
        return [
            'company_name' => (string) config('government_contribution_reports.company_name', config('app.name')),
            'company_address' => (string) config('government_contribution_reports.company_address', ''),
        ];
    }
}
