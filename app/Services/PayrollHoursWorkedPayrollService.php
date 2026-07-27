<?php

namespace App\Services;

use App\Models\EmployeeSalary;
use App\Models\NdRateGroup;
use App\Models\NdRateGroupDayType;
use App\Models\PayrollBatchDetail;
use App\Models\RateBasis;
use App\Models\RateGroup;
use App\Models\RateGroupDayType;
use App\Models\RawPayrollHoursWorked;
use App\Models\TimeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayrollHoursWorkedPayrollService
{
    public function __construct(
        private readonly PayrollAttendanceHoursWorkedService $attendanceHoursWorked,
    ) {}

    /**
     * @return Collection<int, RawPayrollHoursWorked>
     */
    public function uploadedRowsForDetail(PayrollBatchDetail $detail): Collection
    {
        $batch = $detail->payrollBatch ?? $detail->loadMissing('payrollBatch')->payrollBatch;
        $calendarId = $batch?->payroll_calendar_id;

        if ($calendarId === null) {
            return collect();
        }

        return RawPayrollHoursWorked::query()
            ->with(['dayType', 'timeType'])
            ->where('employee_id', $detail->employee_id)
            ->whereHas(
                'payrollTransaction',
                fn (Builder $query) => $query->where('payroll_calendar_id', $calendarId),
            )
            ->orderBy('payroll_hours_worked_id')
            ->get();
    }

    /**
     * Merge attendance-derived hours with uploaded hours per day/time type.
     *
     * @return Collection<int, object{
     *     day_type_id: int,
     *     time_type_id: int,
     *     hours: float,
     *     time_type_code: string|null,
     *     timeType: TimeType|null
     * }>
     */
    public function mergedRowsForDetail(PayrollBatchDetail $detail, EmployeeSalary $salary): Collection
    {
        $attendanceRows = $this->attendanceHoursWorked->rowsForDetail($detail, $salary);
        $uploadedRows = $this->uploadedRowsForDetail($detail);

        return $this->mergeHourRows($attendanceRows, $uploadedRows);
    }

    /**
     * @return array{
     *     by_income_type: array<int, array{taxable: float, non_taxable: float, hours: float}>,
     *     has_overtime: bool
     * }|null
     */
    public function computeIncomeTotalsForDetail(PayrollBatchDetail $detail, EmployeeSalary $salary): ?array
    {
        $rows = $this->mergedRowsForDetail($detail, $salary);

        if ($rows->isEmpty()) {
            return null;
        }

        return $this->computeIncomeTotals($salary, $rows);
    }

    /**
     * @param  Collection<int, RawPayrollHoursWorked|object{
     *     day_type_id: int,
     *     time_type_id: int,
     *     hours: float,
     *     time_type_code?: string|null,
     *     timeType?: TimeType|null
     * }>  $rows
     * @return array{
     *     by_income_type: array<int, array{taxable: float, non_taxable: float}>,
     *     has_overtime: bool
     * }
     */
    public function computeIncomeTotals(EmployeeSalary $salary, Collection $rows): array
    {
        $byIncomeType = [];
        $hasOvertime = false;
        $hourlyRate = $salary->hourlyRate();

        foreach ($rows as $row) {
            $line = $this->computeLineAmount($salary, $row, $hourlyRate);

            if ($line === null) {
                continue;
            }

            $incomeTypeId = $line['income_type_id'];
            $amount = round($line['amount'], 2);

            if ($amount <= 0) {
                continue;
            }

            if ($this->timeTypeCodeForRow($row) !== 'BP') {
                $hasOvertime = true;
            }

            if (! isset($byIncomeType[$incomeTypeId])) {
                $byIncomeType[$incomeTypeId] = ['taxable' => 0.0, 'non_taxable' => 0.0, 'hours' => 0.0];
            }

            if ($line['is_taxable']) {
                $byIncomeType[$incomeTypeId]['taxable'] += $amount;
            } else {
                $byIncomeType[$incomeTypeId]['non_taxable'] += $amount;
            }

            $byIncomeType[$incomeTypeId]['hours'] += (float) ($row->hours ?? 0);
        }

        foreach ($byIncomeType as $incomeTypeId => $amounts) {
            $byIncomeType[$incomeTypeId] = [
                'taxable' => round($amounts['taxable'], 2),
                'non_taxable' => round($amounts['non_taxable'], 2),
                'hours' => round($amounts['hours'], 4),
            ];
        }

        return [
            'by_income_type' => $byIncomeType,
            'has_overtime' => $hasOvertime,
        ];
    }

    /**
     * @param  Collection<int, object|RawPayrollHoursWorked>  ...$sources
     * @return Collection<int, object{
     *     day_type_id: int,
     *     time_type_id: int,
     *     hours: float,
     *     time_type_code: string|null,
     *     timeType: TimeType|null
     * }>
     */
    private function mergeHourRows(Collection ...$sources): Collection
    {
        $merged = [];

        foreach ($sources as $source) {
            foreach ($source as $row) {
                $dayTypeId = (int) ($row->day_type_id ?? 0);
                $timeTypeId = (int) ($row->time_type_id ?? 0);
                $hours = (float) ($row->hours ?? 0);

                if ($dayTypeId <= 0 || $timeTypeId <= 0 || $hours <= 0) {
                    continue;
                }

                $key = $dayTypeId.'-'.$timeTypeId;

                if (! isset($merged[$key])) {
                    $merged[$key] = (object) [
                        'day_type_id' => $dayTypeId,
                        'time_type_id' => $timeTypeId,
                        'hours' => 0.0,
                        'time_type_code' => $this->timeTypeCodeForRow($row),
                        'timeType' => $row instanceof RawPayrollHoursWorked ? $row->timeType : null,
                    ];
                }

                $merged[$key]->hours += $hours;
            }
        }

        return collect(array_values($merged))
            ->map(function (object $row) {
                $row->hours = round((float) $row->hours, 4);

                return $row;
            })
            ->filter(fn (object $row) => $row->hours > 0)
            ->values();
    }

    /**
     * @param  RawPayrollHoursWorked|object  $row
     */
    private function timeTypeCodeForRow(object $row): ?string
    {
        if ($row instanceof RawPayrollHoursWorked) {
            return $row->timeType?->time_type_code;
        }

        return $row->time_type_code ?? null;
    }

    /**
     * @param  RawPayrollHoursWorked|object  $row
     * @return array{income_type_id: int, amount: float, is_taxable: bool}|null
     */
    private function computeLineAmount(EmployeeSalary $salary, object $row, ?float $hourlyRate): ?array
    {
        $hours = (float) ($row->hours ?? 0);
        $dayTypeId = (int) ($row->day_type_id ?? 0);
        $timeTypeId = (int) ($row->time_type_id ?? 0);

        if ($hours <= 0 || $dayTypeId <= 0 || $timeTypeId <= 0) {
            return null;
        }

        $timeClassId = TimeType::TIME_CLASS_REGULAR;

        if ($row instanceof RawPayrollHoursWorked) {
            $timeClassId = (int) ($row->timeType?->time_class_id ?? TimeType::TIME_CLASS_REGULAR);
        } else {
            $timeType = TimeType::query()->find($timeTypeId);
            $timeClassId = (int) ($timeType?->time_class_id ?? TimeType::TIME_CLASS_REGULAR);
        }

        $isNightDiff = $timeClassId === TimeType::TIME_CLASS_NIGHT_DIFF;

        if ($isNightDiff) {
            return $this->computeNightDiffLineAmount($salary, $dayTypeId, $timeTypeId, $hours, $hourlyRate);
        }

        return $this->computeRegularLineAmount($salary, $dayTypeId, $timeTypeId, $hours, $hourlyRate);
    }

    /**
     * @return array{income_type_id: int, amount: float, is_taxable: bool}|null
     */
    private function computeRegularLineAmount(
        EmployeeSalary $salary,
        int $dayTypeId,
        int $timeTypeId,
        float $hours,
        ?float $hourlyRate,
    ): ?array {
        $rateGroupId = $salary->rate_group_id;

        if ($rateGroupId === null) {
            return null;
        }

        $rateGroup = RateGroup::query()
            ->with('rateBasis')
            ->find($rateGroupId);

        if ($rateGroup === null) {
            return null;
        }

        $rateRow = RateGroupDayType::query()
            ->where('rate_group_id', $rateGroupId)
            ->where('day_type_id', $dayTypeId)
            ->where('time_type_id', $timeTypeId)
            ->first();

        if ($rateRow === null || $rateRow->income_type_id === null) {
            return null;
        }

        $amount = $this->amountFromRateDefinition(
            (int) $rateGroup->rate_basis_id,
            $hours,
            (float) $rateRow->rate,
            $hourlyRate,
        );

        if ($amount === null) {
            return null;
        }

        return [
            'income_type_id' => (int) $rateRow->income_type_id,
            'amount' => $amount,
            'is_taxable' => (bool) $rateRow->is_taxable,
        ];
    }

    /**
     * @return array{income_type_id: int, amount: float, is_taxable: bool}|null
     */
    private function computeNightDiffLineAmount(
        EmployeeSalary $salary,
        int $dayTypeId,
        int $timeTypeId,
        float $hours,
        ?float $hourlyRate,
    ): ?array {
        $ndRateGroupId = $salary->nd_rate_group_id;

        if ($ndRateGroupId === null) {
            return null;
        }

        $ndRateGroup = NdRateGroup::query()
            ->with('rateBasis')
            ->find($ndRateGroupId);

        if ($ndRateGroup === null) {
            return null;
        }

        $rateRow = NdRateGroupDayType::query()
            ->where('nd_rate_group_id', $ndRateGroupId)
            ->where('day_type_id', $dayTypeId)
            ->where('time_type_id', $timeTypeId)
            ->first();

        if ($rateRow === null || $rateRow->income_type_id === null) {
            return null;
        }

        $amount = $this->amountFromRateDefinition(
            (int) $ndRateGroup->rate_basis_id,
            $hours,
            (float) $rateRow->rate,
            $hourlyRate,
        );

        if ($amount === null) {
            return null;
        }

        return [
            'income_type_id' => (int) $rateRow->income_type_id,
            'amount' => $amount,
            'is_taxable' => (bool) $rateRow->is_taxable,
        ];
    }

    private function amountFromRateDefinition(
        int $rateBasisId,
        float $hours,
        float $rate,
        ?float $hourlyRate,
    ): ?float {
        if ($rateBasisId === RateBasis::FIXED_AMOUNT_PER_HOUR) {
            return $hours * $rate;
        }

        if ($hourlyRate === null || $hourlyRate <= 0) {
            return null;
        }

        return $hours * $hourlyRate * $rate;
    }
}
