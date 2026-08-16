<?php

namespace App\Support;

/**
 * Maps payroll lines into BIR Form 1601-C (January 2018 ENCS) items 14–36.
 */
class Bir1601cFormMapper
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array{include_annual_13th_month?: bool, annual_13th_month?: float}  $options
     * @return array<string, float>
     */
    public static function totalsFromLines(array $lines, array $options = []): array
    {
        $item14 = 0.0;
        $item15 = 0.0;
        $item16 = 0.0;
        $item17 = 0.0;
        $item18 = 0.0;
        $item19 = 0.0;
        $item20 = 0.0;
        $item23 = 0.0;
        $item25 = 0.0;
        $taxable = 0.0;
        $nonTaxable = 0.0;
        $includeAnnual13th = filter_var($options['include_annual_13th_month'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach ($lines as $line) {
            $form = Bir2316FormMapper::map($line);
            $item14 += (float) $form['item_19'];
            $taxable += (float) ($line['taxable_compensation'] ?? $form['item_52']);
            $nonTaxable += (float) ($line['non_taxable_compensation'] ?? $form['item_38']);

            $isMwe = ! (bool) ($line['is_above_minimum_wage_earner'] ?? false);
            $statutory = round(
                (float) ($line['sss_contribution'] ?? 0)
                + (float) ($line['philhealth_contribution'] ?? 0)
                + (float) ($line['pagibig_contribution'] ?? 0),
                2,
            );
            $gross = (float) ($line['gross_compensation'] ?? 0);
            if ($gross <= 0) {
                $gross = (float) ($line['taxable_compensation'] ?? 0)
                    + (float) ($line['non_taxable_compensation'] ?? 0);
            }

            if ($isMwe) {
                $item16Line = round(
                    (float) $form['item_30']
                    + (float) $form['item_31']
                    + (float) $form['item_32']
                    + (float) $form['item_33'],
                    2,
                );
                // Item 15 SMW = gross less SSS/PHIC/Pag-IBIG (not WHT), less holiday/OT/ND/hazard on item 16.
                $item15 += max(0.0, round($gross - $statutory - $item16Line, 2));
                $item16 += $item16Line;
            }

            $item18 += (float) $form['item_35'];
            $item19 += (float) $form['item_36'];
            $item20 += (float) $form['item_37'];
            $item25 += (float) $form['item_25a'] + (float) $form['item_25b'];

            if (! $isMwe && (float) ($line['tax_withheld'] ?? 0) <= 0) {
                $item23 += (float) $form['item_52'];
            }
        }

        $item17 = $includeAnnual13th
            ? round(max(0.0, (float) ($options['annual_13th_month'] ?? 0)), 2)
            : 0.0;

        $item14 = round($item14, 2);
        $item15 = round($item15, 2);
        $item16 = round($item16, 2);
        $item17 = round($item17, 2);
        $item18 = round($item18, 2);
        $item19 = round($item19, 2);
        $item20 = round($item20, 2);
        $item21 = round($item15 + $item16 + $item17 + $item18 + $item19 + $item20, 2);
        $item22 = round($item14 - $item21, 2);
        $item23 = round(min($item23, max(0.0, $item22)), 2);
        $item24 = round(max(0.0, $item22 - $item23), 2);
        $item25 = round($item25, 2);
        $item26 = 0.0;
        $item27 = round($item25 + $item26, 2);
        $item28 = 0.0;
        $item29 = 0.0;
        $item30 = 0.0;
        $item31 = round($item27 - $item30, 2);
        $item32 = 0.0;
        $item33 = 0.0;
        $item34 = 0.0;
        $item35 = 0.0;
        $item36 = round($item31 + $item35, 2);

        return [
            'gross_compensation' => $item14,
            'taxable_compensation' => round($taxable, 2),
            'non_taxable_compensation' => round($nonTaxable, 2),
            'tax_withheld' => $item25,
            'item_14' => $item14,
            'item_15' => $item15,
            'item_16' => $item16,
            'item_17' => $item17,
            'item_18' => $item18,
            'item_19' => $item19,
            'item_20' => $item20,
            'item_21' => $item21,
            'item_22' => $item22,
            'item_23' => $item23,
            'item_24' => $item24,
            'item_25' => $item25,
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
        ];
    }

    /**
     * PD 851: 13th month = year basic / 12. Non-taxable portion on 1601-C item 17 is capped at ₱90,000 per employee.
     */
    public static function thirteenthMonthFromYearBasic(float $yearBasic): float
    {
        return round(min(90000.0, max(0.0, $yearBasic) / 12), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $yearLines
     */
    public static function annual13thMonthFromLines(array $yearLines): float
    {
        $total = 0.0;

        foreach ($yearLines as $line) {
            $breakdown = $line['income_breakdown'] ?? [];
            $basic = (float) ($breakdown['BASC']['taxable'] ?? 0)
                + (float) ($breakdown['BASC']['non_taxable'] ?? 0);
            $total += self::thirteenthMonthFromYearBasic($basic);
        }

        return round($total, 2);
    }
}
