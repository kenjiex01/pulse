<?php

namespace App\Support;

use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use Illuminate\Support\Collection;

class PayslipYearToDate
{
    /**
     * YTD totals from posted payroll batches in the same pay year through the current period.
     *
     * @return array{
     *     gross_income: float,
     *     taxable_income: float,
     *     withholding_tax: float,
     *     sss_ee: float,
     *     phic_ee: float,
     *     hdmf_ee: float,
     *     net_pay: float
     * }
     */
    public static function forEmployee(int $employeeId, PayrollCalendar $calendar): array
    {
        $payYear = (int) ($calendar->pay_year ?? 0);
        $asOf = $calendar->dt_from?->toDateString();

        if ($payYear < 1 || $asOf === null) {
            return self::emptyTotals();
        }

        $details = PayrollBatchDetail::query()
            ->where('employee_id', $employeeId)
            ->whereHas('payrollBatch', function ($query) use ($payYear, $asOf): void {
                $query->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
                    ->whereHas('payrollCalendar', function ($calendarQuery) use ($payYear, $asOf): void {
                        $calendarQuery->where('pay_year', $payYear)
                            ->whereDate('dt_from', '<=', $asOf);
                    });
            })
            ->with([
                'incomes.incomeType',
                'deductions.deductionType',
                'loanPayments',
                'leaves',
            ])
            ->get();

        $gross = 0.0;
        $taxable = 0.0;
        $withholding = 0.0;
        $sss = 0.0;
        $phic = 0.0;
        $hdmf = 0.0;
        $net = 0.0;

        foreach ($details as $detail) {
            $leaveCost = round((float) $detail->leaves->sum('cost'), 2);
            $periodTaxable = round((float) $detail->incomes->sum(fn (PayrollIncome $income) => (float) $income->taxable), 2);
            $periodNonTaxable = round((float) $detail->incomes->sum(fn (PayrollIncome $income) => (float) $income->non_taxable), 2);
            $periodGross = round($periodTaxable + $periodNonTaxable, 2);
            $periodTaxableNet = round(max(0, $periodTaxable - $leaveCost), 2);

            $gross += $periodGross;
            $taxable += $periodTaxableNet;

            $withholding += self::deductionTotal($detail->deductions, ['WHTX']);
            $sss += self::deductionTotal($detail->deductions, ['SSSP', 'SSMP']);
            $phic += self::deductionTotal($detail->deductions, ['PHIL', 'PHIM']);
            $hdmf += self::deductionTotal($detail->deductions, ['PIBG']);

            $incomeLines = PayrollBatchNetPayLines::incomeLines($detail->incomes);
            $deductionRows = PayrollBatchNetPayLines::deductionRows($detail->deductions);
            $earnings = round(collect(PayrollBatchNetPayLines::payslipEarningsLines($incomeLines))->sum('amount'), 2);
            $deductions = round(
                collect(PayrollBatchNetPayLines::payslipDeductionLines($deductionRows))->sum('amount')
                + collect(PayrollBatchNetPayLines::loanPaymentLines($detail->loanPayments))->sum('amount'),
                2,
            );
            $net += round($earnings - $deductions, 2);
        }

        return [
            'gross_income' => round($gross, 2),
            'taxable_income' => round($taxable, 2),
            'withholding_tax' => round($withholding, 2),
            'sss_ee' => round($sss, 2),
            'phic_ee' => round($phic, 2),
            'hdmf_ee' => round($hdmf, 2),
            'net_pay' => round($net, 2),
        ];
    }

    /**
     * @return array{
     *     gross_income: float,
     *     taxable_income: float,
     *     withholding_tax: float,
     *     sss_ee: float,
     *     phic_ee: float,
     *     hdmf_ee: float,
     *     net_pay: float
     * }
     */
    public static function emptyTotals(): array
    {
        return [
            'gross_income' => 0.0,
            'taxable_income' => 0.0,
            'withholding_tax' => 0.0,
            'sss_ee' => 0.0,
            'phic_ee' => 0.0,
            'hdmf_ee' => 0.0,
            'net_pay' => 0.0,
        ];
    }

    /**
     * @param  Collection<int, PayrollDeduction>  $deductions
     * @param  list<string>  $codes
     */
    private static function deductionTotal(Collection $deductions, array $codes): float
    {
        $codes = array_map('strtoupper', $codes);

        return round((float) $deductions->sum(function (PayrollDeduction $deduction) use ($codes): float {
            $code = strtoupper((string) ($deduction->deductionType?->deduction_type_code ?? ''));

            if (! in_array($code, $codes, true)) {
                return 0.0;
            }

            return (float) ($deduction->employee_amount ?? 0);
        }), 2);
    }
}
