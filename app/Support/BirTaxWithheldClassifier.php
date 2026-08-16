<?php

namespace App\Support;

use App\Models\IncomeType;
use App\Models\PayType;

class BirTaxWithheldClassifier
{
    /**
     * TRAIN monthly tax-exempt compensation (₱250,000 / 12), prorated by pay frequency.
     */
    public static function taxableThresholdForPayType(int $payTypeId): float
    {
        return match ($payTypeId) {
            PayType::DAILY => 685.0,
            PayType::WEEKLY => 4808.0,
            PayType::SEMI_MONTHLY => 10417.0,
            PayType::MONTHLY => 20833.0,
            default => 20833.0,
        };
    }

    public static function isDeminimisIncomeType(?IncomeType $type): bool
    {
        if ($type === null) {
            return false;
        }

        $haystack = strtolower(trim(
            (string) ($type->income_type_code ?? '').' '.(string) ($type->description ?? '')
        ));

        return str_contains($haystack, 'deminimis')
            || str_contains($haystack, 'de minimis')
            || str_contains($haystack, 'de-minimis');
    }

    /**
     * @param  array{
     *     gross_income: float,
     *     overtime_amount: float,
     *     deminimis_benefit: float,
     *     is_above_minimum_wage_earner: bool,
     *     tax_withheld: float,
     *     pay_type_id: int
     * }  $input
     * @return array{
     *     non_taxable_overtime: float,
     *     mwe_income: float,
     *     taxable_no_wt: float,
     *     taxable_with_wt: float,
     *     tax_withheld: float,
     *     deminimis_benefit: float,
     *     gross_income: float,
     *     threshold: float,
     *     is_taxable: bool
     * }
     */
    public static function classify(array $input): array
    {
        $gross = round(max(0, (float) ($input['gross_income'] ?? 0)), 2);
        $overtime = round(max(0, (float) ($input['overtime_amount'] ?? 0)), 2);
        $deminimis = round(max(0, (float) ($input['deminimis_benefit'] ?? 0)), 2);
        $isAboveMwe = (bool) ($input['is_above_minimum_wage_earner'] ?? false);
        $taxWithheld = round(max(0, (float) ($input['tax_withheld'] ?? 0)), 2);
        $threshold = self::taxableThresholdForPayType((int) ($input['pay_type_id'] ?? PayType::MONTHLY));

        // Optional override for monthly BIR worksheet (summed cutoffs vs TRAIN monthly exempt).
        if (array_key_exists('threshold', $input) && $input['threshold'] !== null) {
            $threshold = (float) $input['threshold'];
        }

        $nonTaxableOvertime = 0.0;
        $classificationBase = max(0, round($gross - $deminimis, 2));

        if (! $isAboveMwe) {
            $nonTaxableOvertime = $overtime;
            $classificationBase = max(0, round($classificationBase - $overtime, 2));
        }

        $isTaxable = $classificationBase > $threshold;

        $mweIncome = 0.0;
        $taxableNoWt = 0.0;
        $taxableWithWt = 0.0;
        $reportTaxWithheld = 0.0;

        if (! $isAboveMwe) {
            if ($isTaxable) {
                $taxableWithWt = $classificationBase;
                $reportTaxWithheld = $taxWithheld;
            } else {
                $mweIncome = $classificationBase;
            }
        } elseif ($isTaxable) {
            $taxableWithWt = $classificationBase;
            $reportTaxWithheld = $taxWithheld;
        } else {
            $taxableNoWt = $classificationBase;
        }

        return [
            'non_taxable_overtime' => $nonTaxableOvertime,
            'mwe_income' => $mweIncome,
            'taxable_no_wt' => $taxableNoWt,
            'taxable_with_wt' => $taxableWithWt,
            'tax_withheld' => $reportTaxWithheld,
            'deminimis_benefit' => $deminimis,
            'gross_income' => $gross,
            'threshold' => $threshold,
            'is_taxable' => $isTaxable,
        ];
    }
}
