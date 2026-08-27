<?php

namespace App\Support;

/**
 * Maps People360 YTD line amounts (via Bir2316FormMapper) into PATHS Alphalist column codes.
 */
class AlphalistColumnMapper
{
    /**
     * @param  array<string, mixed>  $line  From BirFormEmployeeSelection + Bir2316FormMapper::map()
     * @return array{
     *     '4b': float,
     *     '4c': float,
     *     '4d': float,
     *     '4e': float,
     *     '4f': float,
     *     '4g': float,
     *     '4h': float,
     *     '4i': float,
     *     '4j': float,
     *     '4a': float,
     *     '5a': string,
     *     '5b': float,
     *     '6': float,
     *     '7': float,
     *     '8': float,
     *     '9': float,
     *     '10a': float,
     *     '10b': float,
     *     '11': float,
     *     '12': string,
     *     is_mwe: bool
     * }
     */
    public static function fromLine(array $line): array
    {
        $form = Bir2316FormMapper::map($line);

        $statutory = round(
            (float) ($line['sss_contribution'] ?? 0)
            + (float) ($line['philhealth_contribution'] ?? 0)
            + (float) ($line['pagibig_contribution'] ?? 0),
            2,
        );

        // Non-taxable (PATHS 4b–4e → 4f)
        $c4b = (float) ($form['item_34'] ?? 0); // 13th month & other benefits (non-taxable portion)
        $c4c = (float) ($form['item_35'] ?? 0); // De minimis
        $c4d = $statutory; // SSS/GSIS/PHIC/Pag-IBIG
        $c4e = round(
            (float) ($form['item_29'] ?? 0)
            + (float) ($form['item_31'] ?? 0)
            + (float) ($form['item_37'] ?? 0),
            2,
        ); // Other non-taxable salaries
        $c4f = round($c4b + $c4c + $c4e, 2);

        // Taxable (PATHS 4g–4i → 4j)
        $c4g = (float) ($form['item_39'] ?? 0); // Basic salary taxable
        $c4h = (float) ($form['item_48'] ?? 0); // 13th month taxable
        $c4i = round(
            (float) ($form['item_44a'] ?? 0)
            + (float) ($form['item_50'] ?? 0),
            2,
        ); // Other taxable + OT
        $c4j = round($c4g + $c4h + $c4i, 2);
        $c4a = round($c4f + $c4j, 2);

        $taxDue = (float) ($form['item_24'] ?? 0);
        $taxWithheld = (float) ($line['tax_withheld'] ?? $form['item_25a'] ?? 0);
        $over = max(0.0, round($taxWithheld - $taxDue, 2));
        $under = max(0.0, round($taxDue - $taxWithheld, 2));

        $taxStatus = trim((string) ($line['tax_status'] ?? ''));

        return [
            '4b' => $c4b,
            '4c' => $c4c,
            '4d' => $c4d,
            '4e' => $c4e,
            '4f' => $c4f,
            '4g' => $c4g,
            '4h' => $c4h,
            '4i' => $c4i,
            '4j' => $c4j,
            '4a' => $c4a,
            '5a' => $taxStatus !== '' ? $taxStatus : 'Z',
            '5b' => 0.0,
            '6' => 0.0,
            '7' => round(max(0.0, $c4j - $c4d), 2),
            '8' => $taxDue,
            '9' => $taxWithheld,
            '10a' => $under,
            '10b' => $over,
            '11' => $taxWithheld,
            '12' => 'Yes',
            'is_mwe' => (bool) ($form['is_mwe'] ?? false),
        ];
    }
}
