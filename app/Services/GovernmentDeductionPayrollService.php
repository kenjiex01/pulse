<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\GovtTablePagibig;
use App\Models\GovtTablePhilhealth;
use App\Models\GovtTablePhilhealthMinimum;
use App\Models\GovtTableSss;
use App\Models\GovtTableWtax2023;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Support\PhilhealthDeductionTypes;
use App\Support\SssDeductionTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GovernmentDeductionPayrollService
{
    public const GOVT_HDMF = 1;

    public const GOVT_PHIC = 2;

    public const GOVT_SSS = 3;

    public const GOVT_SSEC = 4;

    public const GOVT_WTAX = 5;

    /**
     * @return Collection<int, array{
     *     deduction_type_id: int,
     *     employee_amount: float,
     *     employer_amount: float
     * }>
     */
    public function computeForDetail(PayrollBatchDetail $detail, PayrollBatch $batch): Collection
    {
        $calendar = $batch->payrollCalendar;

        if ($calendar === null) {
            return collect();
        }

        $calendar->loadMissing(['deductions.deductionType']);

        $periodGrossTaxable = (float) $detail->incomes()->sum('taxable');
        $monthlyGrossTaxable = $this->monthlyGrossTaxable($detail, $batch);

        // Period gross drives WHT; SSS / PhilHealth / Pag-IBIG use whole-month gross (true-up).
        if ($periodGrossTaxable <= 0 && $monthlyGrossTaxable <= 0) {
            return collect();
        }

        $existingTypeIds = $detail->deductions()
            ->pluck('deduction_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $statutoryEmployeeTotal = 0.0;
        $lines = collect();

        $govtDeductions = $calendar->deductions
            ->map(fn ($row) => $row->deductionType)
            ->filter(fn (?DeductionType $type) => $type !== null && $type->is_valid_govt_deduction)
            ->sortBy(fn (DeductionType $type) => $type->govt_table_id ?? 999)
            ->values();

        foreach ($govtDeductions as $deductionType) {
            if (in_array((int) $deductionType->deduction_type_id, $existingTypeIds, true)) {
                continue;
            }

            $amounts = $this->computeForDeductionType(
                $deductionType,
                $detail,
                $batch,
                $periodGrossTaxable,
                $monthlyGrossTaxable,
                $statutoryEmployeeTotal,
            );

            if ($amounts === null) {
                continue;
            }

            $employeeAmount = round(max(0, $amounts['employee_amount']), 2);
            $employerAmount = round(max(0, $amounts['employer_amount']), 2);

            if ($employeeAmount <= 0 && $employerAmount <= 0) {
                continue;
            }

            $govtTableId = (int) $deductionType->govt_table_id;

            if ($govtTableId !== self::GOVT_WTAX) {
                $statutoryEmployeeTotal += $employeeAmount;
            }

            $lines->push([
                'deduction_type_id' => (int) $deductionType->deduction_type_id,
                'employee_amount' => $employeeAmount,
                'employer_amount' => $employerAmount,
            ]);
        }

        return $lines;
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}|null
     */
    public function computeForDeductionType(
        DeductionType $deductionType,
        PayrollBatchDetail $detail,
        PayrollBatch $batch,
        float $periodGrossTaxable,
        float $monthlyGrossTaxable,
        float $statutoryEmployeeTotal,
    ): ?array {
        return match ($deductionType->deduction_type_code) {
            PhilhealthDeductionTypes::MINIMUM => $this->netOfPriorDeductions(
                $this->computePhilhealthMinimum(),
                $detail,
                $batch,
                (int) $deductionType->deduction_type_id,
            ),
            PhilhealthDeductionTypes::PREMIUM => $this->netOfPriorDeductions(
                $this->computePhilhealthPremium($detail, $batch),
                $detail,
                $batch,
                (int) $deductionType->deduction_type_id,
            ),
            SssDeductionTypes::PREMIUM => $this->netOfPriorDeductions(
                $this->computeSssRegular($monthlyGrossTaxable),
                $detail,
                $batch,
                (int) $deductionType->deduction_type_id,
            ),
            SssDeductionTypes::MPF => $this->netOfPriorDeductions(
                $this->computeSssMpf($monthlyGrossTaxable),
                $detail,
                $batch,
                (int) $deductionType->deduction_type_id,
            ),
            default => $this->netOfPriorForMonthlyGovt(
                $this->computeAmounts(
                    (int) $deductionType->govt_table_id,
                    match ((int) $deductionType->govt_table_id) {
                        // WHT: this period only + pay-type frequency table.
                        self::GOVT_WTAX => $periodGrossTaxable,
                        // Pag-IBIG / PhilHealth fallback: whole-month base (true-up vs prior periods).
                        self::GOVT_HDMF => $monthlyGrossTaxable,
                        self::GOVT_PHIC => $this->philhealthContributionBase($detail, $batch),
                        self::GOVT_SSS => $monthlyGrossTaxable,
                        default => $periodGrossTaxable,
                    },
                    $statutoryEmployeeTotal,
                    $batch,
                ),
                $detail,
                $batch,
                $deductionType,
            ),
        };
    }

    /**
     * Apply prior-period lessing for monthly statutory tables; leave WHT / non-govt untouched.
     *
     * @param  array{employee_amount: float, employer_amount: float}|null  $amounts
     * @return array{employee_amount: float, employer_amount: float}|null
     */
    private function netOfPriorForMonthlyGovt(
        ?array $amounts,
        PayrollBatchDetail $detail,
        PayrollBatch $batch,
        DeductionType $deductionType,
    ): ?array {
        if ($amounts === null) {
            return null;
        }

        $govtTableId = (int) $deductionType->govt_table_id;

        if (! in_array($govtTableId, [self::GOVT_HDMF, self::GOVT_PHIC, self::GOVT_SSS], true)) {
            return $amounts;
        }

        return $this->netOfPriorDeductions(
            $amounts,
            $detail,
            $batch,
            (int) $deductionType->deduction_type_id,
        );
    }

    /**
     * Monthly contribution due minus amounts already withheld in earlier pay periods
     * of the same calendar month / year for this employee and deduction type.
     *
     * @param  array{employee_amount: float, employer_amount: float}  $due
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function netOfPriorDeductions(
        array $due,
        PayrollBatchDetail $detail,
        PayrollBatch $batch,
        int $deductionTypeId,
    ): array {
        $prior = $this->priorDeductionAmounts($detail, $batch, $deductionTypeId);

        return [
            'employee_amount' => round(max(0, $due['employee_amount'] - $prior['employee_amount']), 2),
            'employer_amount' => round(max(0, $due['employer_amount'] - $prior['employer_amount']), 2),
        ];
    }

    /**
     * Sum of a deduction type from earlier pay periods in the same calendar month/year.
     *
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function priorDeductionAmounts(
        PayrollBatchDetail $detail,
        PayrollBatch $batch,
        int $deductionTypeId,
    ): array {
        $batch->loadMissing('payrollCalendar');
        $calendar = $batch->payrollCalendar;

        if ($calendar === null || $calendar->calendar_month === null || $calendar->pay_year === null) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        $totals = PayrollDeduction::query()
            ->where('deduction_type_id', $deductionTypeId)
            ->whereHas('payrollBatchDetail', function ($detailQuery) use ($detail, $calendar) {
                $detailQuery
                    ->where('employee_id', $detail->employee_id)
                    ->whereHas('payrollBatch.payrollCalendar', function ($calendarQuery) use ($calendar) {
                        $calendarQuery
                            ->where('pay_year', (int) $calendar->pay_year)
                            ->where('calendar_month', (int) $calendar->calendar_month)
                            ->where('pay_period', '<', (int) $calendar->pay_period);
                    });
            })
            ->selectRaw('COALESCE(SUM(employee_amount), 0) as employee_total')
            ->selectRaw('COALESCE(SUM(employer_amount), 0) as employer_total')
            ->first();

        return [
            'employee_amount' => (float) ($totals->employee_total ?? 0),
            'employer_amount' => (float) ($totals->employer_total ?? 0),
        ];
    }

    /**
     * PhilHealth contribution base: Basic Salary (excludes Overtime) − Tardiness.
     */
    public function philhealthContributionBase(PayrollBatchDetail $detail, PayrollBatch $batch): float
    {
        $basic = $this->monthlyBasicSalaryExcludingOvertime($detail, $batch);
        $tardiness = $this->monthlyTardinessAmount($detail, $batch);

        return round(max(0, $basic - $tardiness), 2);
    }

    /**
     * Monthly Basic (BASC / default basic) taxable + non-taxable, excluding OVRT.
     */
    public function monthlyBasicSalaryExcludingOvertime(PayrollBatchDetail $detail, PayrollBatch $batch): float
    {
        $total = (float) $this->monthlyIncomesQuery($detail, $batch)
            ->whereHas('incomeType', function (Builder $typeQuery) {
                $typeQuery
                    ->where(function (Builder $basicQuery) {
                        $basicQuery
                            ->where('is_default_basic', true)
                            ->orWhere('income_type_code', 'BASC');
                    })
                    ->where(function (Builder $notOt) {
                        $notOt
                            ->whereNull('income_type_code')
                            ->orWhere('income_type_code', '!=', 'OVRT');
                    });
            })
            ->get(['taxable', 'non_taxable'])
            ->sum(fn (PayrollIncome $income) => (float) $income->taxable + (float) $income->non_taxable);

        return round($total, 2);
    }

    /**
     * Monthly tardiness (late) deduction amounts for the employee.
     */
    public function monthlyTardinessAmount(PayrollBatchDetail $detail, PayrollBatch $batch): float
    {
        $lateTypeIds = DeductionType::query()
            ->whereIn('deduction_type_code', ['LTDE'])
            ->pluck('deduction_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($lateTypeIds === []) {
            return 0.0;
        }

        $total = (float) $this->monthlyDeductionsQuery($detail, $batch)
            ->whereIn('deduction_type_id', $lateTypeIds)
            ->sum('employee_amount');

        return round($total, 2);
    }

    /**
     * Sum taxable incomes for this employee across all payroll batches that share
     * the same calendar_month + pay_year (whole calendar month gross).
     */
    public function monthlyGrossTaxable(PayrollBatchDetail $detail, PayrollBatch $batch): float
    {
        $total = (float) $this->monthlyIncomesQuery($detail, $batch)->sum('taxable');

        return round($total, 2);
    }

    private function monthlyIncomesQuery(PayrollBatchDetail $detail, PayrollBatch $batch): Builder
    {
        $batch->loadMissing('payrollCalendar');
        $calendar = $batch->payrollCalendar;

        if ($calendar === null || $calendar->calendar_month === null || $calendar->pay_year === null) {
            return PayrollIncome::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id);
        }

        return PayrollIncome::query()
            ->whereHas('payrollBatchDetail', function ($query) use ($detail, $calendar) {
                $query
                    ->where('employee_id', $detail->employee_id)
                    ->whereHas('payrollBatch.payrollCalendar', function ($calendarQuery) use ($calendar) {
                        $calendarQuery
                            ->where('pay_year', (int) $calendar->pay_year)
                            ->where('calendar_month', (int) $calendar->calendar_month);
                    });
            });
    }

    private function monthlyDeductionsQuery(PayrollBatchDetail $detail, PayrollBatch $batch): Builder
    {
        $batch->loadMissing('payrollCalendar');
        $calendar = $batch->payrollCalendar;

        if ($calendar === null || $calendar->calendar_month === null || $calendar->pay_year === null) {
            return PayrollDeduction::query()->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id);
        }

        return PayrollDeduction::query()
            ->whereHas('payrollBatchDetail', function ($query) use ($detail, $calendar) {
                $query
                    ->where('employee_id', $detail->employee_id)
                    ->whereHas('payrollBatch.payrollCalendar', function ($calendarQuery) use ($calendar) {
                        $calendarQuery
                            ->where('pay_year', (int) $calendar->pay_year)
                            ->where('calendar_month', (int) $calendar->calendar_month);
                    });
            });
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}|null
     */
    public function computeAmounts(
        int $govtTableId,
        float $grossTaxable,
        float $statutoryEmployeeTotal,
        PayrollBatch $batch,
    ): ?array {
        return match ($govtTableId) {
            self::GOVT_HDMF => $this->computePagibig($grossTaxable),
            self::GOVT_PHIC => $this->computePhilhealthBracket($grossTaxable),
            self::GOVT_SSS => $this->computeSssRegular($grossTaxable),
            self::GOVT_SSEC => ['employee_amount' => 0.0, 'employer_amount' => 0.0],
            self::GOVT_WTAX => $this->computeWithholdingTax($grossTaxable, $statutoryEmployeeTotal, $batch),
            default => null,
        };
    }

    /**
     * Pag-IBIG contributions are fixed peso amounts from the government table
     * (not percentages of gross), keyed by salary_cap bracket.
     *
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computePagibig(float $grossTaxable): array
    {
        if ($grossTaxable <= 0) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        $rows = GovtTablePagibig::query()->orderBy('salary_cap')->get();

        if ($rows->isEmpty()) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        $selected = $rows->last();

        foreach ($rows as $row) {
            if ($grossTaxable <= (float) $row->salary_cap) {
                $selected = $row;
                break;
            }
        }

        return [
            'employee_amount' => (float) $selected->employee_contribution,
            'employer_amount' => (float) $selected->employer_contribution,
        ];
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computePhilhealthBracket(float $grossTaxable): array
    {
        $row = GovtTablePhilhealth::query()
            ->where('is_active', true)
            ->where('salary_from', '<=', $grossTaxable)
            ->where('salary_to', '>=', $grossTaxable)
            ->orderBy('salary_from')
            ->first();

        if ($row === null) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        if ($row->is_percent) {
            // Base is already Basic Salary − Tardiness (Overtime excluded).
            $percentage = max(0.0, (float) $row->percentage);
            $totalPremium = round($grossTaxable * ($percentage / 100), 2);
            $employeeShare = round($totalPremium / 2, 2);
            $employerShare = round($totalPremium - $employeeShare, 2);

            return [
                'employee_amount' => $employeeShare,
                'employer_amount' => $employerShare,
            ];
        }

        return [
            'employee_amount' => (float) $row->employee_share,
            'employer_amount' => (float) $row->employer_share,
        ];
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computePhilhealthMinimum(): array
    {
        $row = GovtTablePhilhealthMinimum::query()->first();

        return [
            'employee_amount' => (float) ($row->employee_amount ?? 0),
            'employer_amount' => (float) ($row->employer_amount ?? 0),
        ];
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computePhilhealthPremium(
        PayrollBatchDetail $detail,
        PayrollBatch $batch,
    ): array {
        // Percent brackets: (Basic − Tardiness) × %; fixed brackets lookup the same base.
        $contributionBase = $this->philhealthContributionBase($detail, $batch);
        $bracket = $this->computePhilhealthBracket($contributionBase);
        $priorMinimum = $this->priorPhilhealthMinimumDeductions($detail, $batch);

        return [
            'employee_amount' => max(0, $bracket['employee_amount'] - $priorMinimum['employee_amount']),
            'employer_amount' => max(0, $bracket['employer_amount'] - $priorMinimum['employer_amount']),
        ];
    }

    /**
     * Sum Philhealth Minimum deductions from earlier pay periods in the same calendar month/year.
     *
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function priorPhilhealthMinimumDeductions(PayrollBatchDetail $detail, PayrollBatch $batch): array
    {
        $minimumTypeId = DeductionType::query()
            ->where('deduction_type_code', PhilhealthDeductionTypes::MINIMUM)
            ->value('deduction_type_id');

        if (! $minimumTypeId) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        return $this->priorDeductionAmounts($detail, $batch, (int) $minimumTypeId);
    }

    /**
     * @deprecated Use computePhilhealthBracket, computePhilhealthMinimum, or computePhilhealthPremium.
     *
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computePhilhealth(float $grossTaxable): array
    {
        $bracket = $this->computePhilhealthBracket($grossTaxable);
        $minimum = $this->computePhilhealthMinimum();

        return [
            'employee_amount' => max($bracket['employee_amount'], $minimum['employee_amount']),
            'employer_amount' => max($bracket['employer_amount'], $minimum['employer_amount']),
        ];
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computeSssRegular(float $grossTaxable): array
    {
        $row = $this->sssBracketRow($grossTaxable);

        if ($row === null) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        return [
            'employee_amount' => round((float) $row->employee_sss, 2),
            // Regular SS + EC (employer only)
            'employer_amount' => round((float) $row->employer_sss + (float) $row->employer_ec, 2),
        ];
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computeSssMpf(float $grossTaxable): array
    {
        $row = $this->sssBracketRow($grossTaxable);

        if ($row === null) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        return [
            'employee_amount' => round((float) $row->employee_mpf_share, 2),
            'employer_amount' => round((float) $row->employer_mpf_share, 2),
        ];
    }

    /**
     * Combined Regular + MPF (+ EC on employer) — kept for callers that need the full SSS total.
     *
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computeSss(float $grossTaxable): array
    {
        $regular = $this->computeSssRegular($grossTaxable);
        $mpf = $this->computeSssMpf($grossTaxable);

        return [
            'employee_amount' => round($regular['employee_amount'] + $mpf['employee_amount'], 2),
            'employer_amount' => round($regular['employer_amount'] + $mpf['employer_amount'], 2),
        ];
    }

    private function sssBracketRow(float $grossTaxable): ?GovtTableSss
    {
        return GovtTableSss::query()
            ->where('compensation_from', '<=', $grossTaxable)
            ->where('compensation_to', '>=', $grossTaxable)
            ->orderBy('compensation_from')
            ->first();
    }

    /**
     * @return array{employee_amount: float, employer_amount: float}
     */
    public function computeWithholdingTax(float $grossTaxable, float $statutoryEmployeeTotal, PayrollBatch $batch): array
    {
        if ((int) $batch->withholding_tax_computation_id !== 1) {
            return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
        }

        $netTaxable = max(0, $grossTaxable - $statutoryEmployeeTotal);
        // Match BIR table to payroll calendar pay type (semi-monthly employee → semi-monthly table).
        $frequencyId = $this->withholdingTaxFrequencyId($batch);

        $rows = GovtTableWtax2023::query()
            ->where('withholding_tax_table_type_id', $frequencyId)
            ->orderByDesc('amount')
            ->get();

        foreach ($rows as $row) {
            if ($netTaxable < (float) $row->amount) {
                continue;
            }

            $base = (float) $row->amount;
            $tax = (float) $row->tax_amount;

            if ($netTaxable > $base) {
                $tax += ($netTaxable - $base) * ((float) $row->tax_plus / 100);
            }

            return [
                'employee_amount' => max(0, $tax),
                'employer_amount' => 0.0,
            ];
        }

        return ['employee_amount' => 0.0, 'employer_amount' => 0.0];
    }

    public function withholdingTaxFrequencyId(PayrollBatch $batch): int
    {
        $payTypeId = (int) ($batch->payrollCalendar?->pay_type_id ?? PayType::MONTHLY);

        return match ($payTypeId) {
            PayType::DAILY => GovtTableWtax2023::DAILY,
            PayType::WEEKLY => GovtTableWtax2023::WEEKLY,
            PayType::SEMI_MONTHLY => GovtTableWtax2023::SEMI_MONTHLY,
            PayType::MONTHLY => GovtTableWtax2023::MONTHLY,
            default => GovtTableWtax2023::MONTHLY,
        };
    }

    /**
     * @param  Collection<int, array{
     *     deduction_type_id: int,
     *     employee_amount: float,
     *     employer_amount: float
     * }>  $lines
     */
    public function persistLines(PayrollBatchDetail $detail, Collection $lines): void
    {
        foreach ($lines as $line) {
            PayrollDeduction::query()->create([
                'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
                'deduction_type_id' => $line['deduction_type_id'],
                'employee_amount' => $line['employee_amount'],
                'employer_amount' => $line['employer_amount'],
                'is_manual' => false,
                'is_editable' => true,
                'is_deletable' => true,
            ]);
        }
    }

    public function refreshGovernmentDeductionsForDetail(PayrollBatchDetail $detail, PayrollBatch $batch): void
    {
        $batch->loadMissing('payrollCalendar.deductions.deductionType');
        $calendar = $batch->payrollCalendar;

        if ($calendar === null) {
            return;
        }

        $govtTypeIds = $calendar->deductions
            ->map(fn ($row) => $row->deductionType)
            ->filter(fn (?DeductionType $type) => $type !== null && $type->is_valid_govt_deduction)
            ->pluck('deduction_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($govtTypeIds === []) {
            return;
        }

        $detail->deductions()
            ->whereIn('deduction_type_id', $govtTypeIds)
            ->each(fn (PayrollDeduction $deduction) => $deduction->forceDelete());

        $detail->load(['incomes', 'deductions']);

        $this->persistLines(
            $detail,
            $this->computeForDetail($detail, $batch),
        );
    }
}
