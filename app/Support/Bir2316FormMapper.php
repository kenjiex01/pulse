<?php

namespace App\Support;

use App\Models\IncomeType;

/**
 * Maps payroll batch amounts into official BIR Form 2316 numbered items.
 *
 * @phpstan-type FormAmounts array<string, float|string|bool|null>
 */
class Bir2316FormMapper
{
    /**
     * @param  array{
     *     taxable_compensation: float,
     *     non_taxable_compensation: float,
     *     tax_withheld: float,
     *     sss_contribution: float,
     *     philhealth_contribution: float,
     *     pagibig_contribution: float,
     *     income_breakdown?: array<string, array{taxable: float, non_taxable: float}>,
     *     is_above_minimum_wage_earner?: bool
     * }  $line
     * @return array<string, mixed>
     */
    public static function map(array $line): array
    {
        $breakdown = $line['income_breakdown'] ?? [];

        $basicTax = 0.0;
        $basicNon = 0.0;
        $holidayTax = 0.0;
        $holidayNon = 0.0;
        $otTax = 0.0;
        $otNon = 0.0;
        $ndTax = 0.0;
        $ndNon = 0.0;
        $hazardTax = 0.0;
        $hazardNon = 0.0;
        $thirteenthTax = 0.0;
        $thirteenthNon = 0.0;
        $deminimis = 0.0;
        $otherTax = 0.0;
        $otherNon = 0.0;

        foreach ($breakdown as $code => $amounts) {
            $tax = (float) ($amounts['taxable'] ?? 0);
            $non = (float) ($amounts['non_taxable'] ?? 0);
            $code = strtoupper((string) $code);
            $kind = self::premiumKind($code);

            if ($code === 'BASC') {
                $basicTax += $tax;
                $basicNon += $non;
            } elseif ($code === '13TH') {
                $thirteenthTax += $tax;
                $thirteenthNon += $non;
            } elseif ($code === 'DEMN') {
                $deminimis += $tax + $non;
            } elseif ($kind === 'holiday') {
                $holidayTax += $tax;
                $holidayNon += $non;
            } elseif ($kind === 'ot') {
                $otTax += $tax;
                $otNon += $non;
            } elseif ($kind === 'nd') {
                $ndTax += $tax;
                $ndNon += $non;
            } elseif ($kind === 'hazard') {
                $hazardTax += $tax;
                $hazardNon += $non;
            } else {
                $otherTax += $tax;
                $otherNon += $non;
            }
        }

        // Fallback when only totals exist (no per-code breakdown keys besides totals).
        if ($breakdown === []) {
            $taxable = (float) ($line['taxable_compensation'] ?? 0);
            $nonTaxable = (float) ($line['non_taxable_compensation'] ?? 0);
            $basicTax = $taxable;
            $basicNon = $nonTaxable;
        }

        $statutory = round(
            (float) ($line['sss_contribution'] ?? 0)
            + (float) ($line['philhealth_contribution'] ?? 0)
            + (float) ($line['pagibig_contribution'] ?? 0),
            2,
        );

        $isAboveMwe = (bool) ($line['is_above_minimum_wage_earner'] ?? false);
        $taxWithheld = round((float) ($line['tax_withheld'] ?? 0), 2);
        $treatAsMwe = ! $isAboveMwe;

        if ($treatAsMwe) {
            // MWE: compensation sits in non-taxable section (items 29–37).
            $item29 = round($basicNon + $basicTax, 2);
            $item30 = round($holidayNon + $holidayTax, 2);
            $item31 = round($otNon + $otTax, 2);
            $item32 = round($ndNon + $ndTax, 2);
            $item33 = round($hazardNon + $hazardTax, 2);
            $item34 = round(min(90000.0, $thirteenthNon + $thirteenthTax), 2);
            $item35 = round($deminimis, 2);
            $item36 = $statutory;
            $item37 = round($otherNon + $otherTax, 2);
            $item39 = 0.0;
            $item48 = 0.0;
            $item50 = 0.0;
            $item44a = 0.0;
            $item51a = 0.0;
        } else {
            $item29 = round($basicNon, 2);
            $item30 = round($holidayNon, 2);
            $item31 = round($otNon, 2);
            $item32 = round($ndNon, 2);
            $item33 = round($hazardNon, 2);
            $item34 = round(min(90000.0, $thirteenthNon), 2);
            $item35 = round($deminimis, 2);
            $item36 = $statutory;
            $item37 = round($otherNon, 2);
            $item39 = round($basicTax, 2);
            $item48 = round($thirteenthTax + max(0.0, ($thirteenthNon + $thirteenthTax) - 90000.0), 2);
            $item50 = round($otTax, 2);
            $item44a = round($otherTax + $holidayTax + $ndTax + $hazardTax, 2);
            $item51a = 0.0;
        }

        $item38 = round($item29 + $item30 + $item31 + $item32 + $item33 + $item34 + $item35 + $item36 + $item37, 2);
        $item52 = round($item39 + $item44a + $item48 + $item50 + $item51a, 2);

        $item19 = round($item38 + $item52, 2);
        $item20 = $item38;
        $item21 = $item52;
        $item22 = 0.0;
        $item23 = round($item21 + $item22, 2);
        $item24 = $taxWithheld; // Tax due equals withheld for batch substitute when fully withheld
        $item25a = $taxWithheld;
        $item25b = 0.0;
        $item26 = round($item25a + $item25b, 2);
        $item27 = 0.0;
        $item28 = round($item26 + $item27, 2);

        return [
            'is_mwe' => $treatAsMwe,
            'item_19' => $item19,
            'item_20' => $item20,
            'item_21' => $item21,
            'item_22' => $item22,
            'item_23' => $item23,
            'item_24' => $item24,
            'item_25a' => $item25a,
            'item_25b' => $item25b,
            'item_26' => $item26,
            'item_27' => $item27,
            'item_28' => $item28,
            'item_29' => $item29,
            'item_30' => $item30,
            'item_31' => $item31,
            'item_32' => $item32,
            'item_33' => $item33,
            'item_34' => $item34,
            'item_35' => $item35,
            'item_36' => $item36,
            'item_37' => $item37,
            'item_38' => $item38,
            'item_39' => $item39,
            'item_40' => 0.0,
            'item_41' => 0.0,
            'item_42' => 0.0,
            'item_43' => 0.0,
            'item_44a' => $item44a,
            'item_44a_label' => $item44a > 0 ? 'Other Taxable Income' : '',
            'item_44b' => 0.0,
            'item_44b_label' => '',
            'item_45' => 0.0,
            'item_46' => 0.0,
            'item_47' => 0.0,
            'item_48' => $item48,
            'item_49' => 0.0,
            'item_50' => $item50,
            'item_51a' => $item51a,
            'item_51a_label' => '',
            'item_51b' => 0.0,
            'item_51b_label' => '',
            'item_52' => $item52,
        ];
    }

    public static function codeForIncomeType(?IncomeType $type): string
    {
        if ($type === null) {
            return 'OTHR';
        }

        if (BirTaxWithheldClassifier::isDeminimisIncomeType($type)) {
            return 'DEMN';
        }

        $code = strtoupper((string) ($type->income_type_code ?? ''));

        return $code !== '' ? $code : 'OTHR';
    }

    /**
     * Classify holiday / OT / night differential / hazard income codes for 2316 items 30–33 and 1601-C item 16.
     */
    public static function premiumKind(string $code): ?string
    {
        $code = strtoupper(trim($code));

        if ($code === 'OVRT' || $code === 'OT') {
            return 'ot';
        }

        if (in_array($code, ['HOLI', 'HPAY', 'HOLP', 'RDOT', 'RSTP'], true) || str_contains($code, 'HOL')) {
            return 'holiday';
        }

        if (in_array($code, ['NDIF', 'NDOT', 'NDSOT', 'NDFF'], true) || str_starts_with($code, 'ND')) {
            return 'nd';
        }

        if (in_array($code, ['HAZD', 'HAZP', 'HZRD'], true) || str_contains($code, 'HAZ')) {
            return 'hazard';
        }

        return null;
    }
}
