<?php

namespace App\Services\Reports;

use setasign\Fpdi\Fpdi;

/**
 * Stamp values onto BIR Form 1601-C (January 2018 ENCS, 215.9 × 330.2 mm).
 *
 * Amount digits are centered on measured comb-box centers (pixel-scanned from the blank PDF).
 */
class Bir1601cOfficialPdfWriter
{
    private const TEMPLATE = 'bir-forms/BIR1601c.pdf';

    private const PAGE_W = 215.9;

    private const PAGE_H = 330.2;

    /**
     * Decimal-bullet Y (mm) for items 14–36 — stamp digits slightly below this.
     *
     * @var array<int, float>
     */
    private const AMOUNT_Y = [
        14 => 107.08,
        15 => 116.14,
        16 => 121.77,
        17 => 127.40,
        18 => 132.99,
        19 => 138.62,
        20 => 144.25,
        21 => 149.88,
        22 => 155.51,
        23 => 161.14,
        24 => 166.78,
        25 => 172.41,
        26 => 178.04,
        27 => 183.67,
        28 => 189.30,
        29 => 194.88,
        30 => 200.77,
        31 => 206.40,
        32 => 212.03,
        33 => 217.62,
        34 => 223.26,
        35 => 228.89,
        36 => 234.52,
    ];

    /**
     * Horizontal centers of the 11 peso digit boxes (mm) — midpoints between comb ticks.
     *
     * @var list<float>
     */
    private const PESO_CX = [
        140.35, 145.41, 150.47, 155.52, 160.57, 165.63, 170.69, 175.75, 180.77, 185.83, 191.04,
    ];

    /**
     * Horizontal centers of the 2 centavo digit boxes (mm).
     *
     * @var list<float>
     */
    private const CENT_CX = [201.85, 206.97];

    /**
     * @param  array<string, mixed>  $meta
     */
    public function write(array $meta): string
    {
        $template = resource_path(self::TEMPLATE);

        if (! is_file($template)) {
            throw new \RuntimeException('BIR 1601-C template not found at '.$template);
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($template);

        $tpl1 = $pdf->importPage(1);
        $pdf->AddPage('P', [self::PAGE_W, self::PAGE_H]);
        $pdf->useTemplate($tpl1, 0, 0, self::PAGE_W, self::PAGE_H);
        $pdf->SetFont('Courier', '', 10);

        $debug = 0;
        $month = (int) ($meta['calendar_month'] ?? 0);
        $year = (string) ($meta['pay_year'] ?? '');
        $monthStr = $month > 0 ? str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '';

        // Item 1 — six white comb boxes (MM + YYYY); centers from blank-template scan
        $monthY = 40.4;
        $this->digitAt($pdf, 18.94, $monthY, $monthStr[0] ?? '', $debug);
        $this->digitAt($pdf, 23.97, $monthY, $monthStr[1] ?? '', $debug);
        $this->digitAt($pdf, 28.99, $monthY, $year[0] ?? '', $debug);
        $this->digitAt($pdf, 34.02, $monthY, $year[1] ?? '', $debug);
        $this->digitAt($pdf, 39.09, $monthY, $year[2] ?? '', $debug);
        $this->digitAt($pdf, 44.16, $monthY, $year[3] ?? '', $debug);

        // Item 2 Amended Return? — No (checkbox center)
        $this->mark($pdf, 83.7, 40.9, $debug);

        $totals = $meta['totals'] ?? [];
        $withheld = (float) ($totals['item_25'] ?? $totals['tax_withheld'] ?? 0);

        // Item 3 Any Taxes Withheld?
        if ($withheld > 0) {
            $this->mark($pdf, 109.8, 40.6, $debug);
        } else {
            $this->mark($pdf, 124.15, 40.4, $debug);
        }

        $tin = preg_replace('/\D/', '', (string) ($meta['company_tin'] ?? '')) ?? '';
        $tin = $tin === '' ? '' : str_pad(substr($tin, 0, 14), 14, '0', STR_PAD_RIGHT);
        if ($tin !== '') {
            $tinCx = [84.55, 89.65, 94.75, 104.85, 109.95, 114.95, 125.05, 130.15, 135.15, 145.35, 150.35, 155.45, 160.55, 165.55];
            foreach ($tinCx as $i => $cx) {
                $this->digitAt($pdf, $cx, 51.6, $tin[$i] ?? '', $debug);
            }
        }

        $rdo = substr(preg_replace('/\D/', '', (string) ($meta['company_rdo_code'] ?? '')) ?? '', 0, 3);
        $this->digitAt($pdf, 196.05, 51.6, $rdo[0] ?? '', $debug);
        $this->digitAt($pdf, 201.45, 51.6, $rdo[1] ?? '', $debug);
        $this->digitAt($pdf, 206.75, 51.6, $rdo[2] ?? '', $debug);

        $pdf->SetFont('Courier', '', 8);
        $pdf->SetXY(8.1, 59.5);
        $pdf->Cell(199.6, 4.0, strtoupper((string) ($meta['company_name'] ?? '')), $debug, 0);

        $address = strtoupper((string) ($meta['company_address'] ?? ''));
        $pdf->SetFont('Courier', '', strlen($address) < 90 ? 8 : 6);
        $pdf->SetXY(8.1, 68.8);
        $pdf->Cell(150, 4.0, $address, $debug, 0);
        $pdf->SetFont('Courier', '', 10);

        $zip = substr(preg_replace('/\D/', '', (string) ($meta['company_zip'] ?? '')) ?? '', 0, 4);
        $this->digitAt($pdf, 190.75, 75.6, $zip[0] ?? '', $debug);
        $this->digitAt($pdf, 196.15, 75.6, $zip[1] ?? '', $debug);
        $this->digitAt($pdf, 201.45, 75.6, $zip[2] ?? '', $debug);
        $this->digitAt($pdf, 206.75, 75.6, $zip[3] ?? '', $debug);

        // Item 11 Category of Withholding Agent — Private
        $this->mark($pdf, 160.25, 82.3, $debug);

        // Item 13 tax relief — No
        $this->mark($pdf, 79.45, 94.8, $debug);

        foreach (self::AMOUNT_Y as $item => $y) {
            $this->amountAt($pdf, $y, (float) ($totals['item_'.$item] ?? 0), $debug);
        }

        $pdf->SetFont('Courier', '', 8);
        $signatoryName = strtoupper(trim((string) ($meta['signatory_name'] ?? '')));
        $signatoryTitle = trim((string) ($meta['signatory_title'] ?? ''));

        if ($signatoryName !== '') {
            $pdf->SetXY(109.4, 248.8);
            $pdf->Cell(98.4, 2.8, $signatoryName, $debug, 0, 'C');
        }

        if ($signatoryTitle !== '') {
            $pdf->SetFont('Courier', '', 7);
            $pdf->SetXY(109.4, 251.6);
            $pdf->Cell(98.4, 2.5, strtoupper($signatoryTitle), $debug, 0, 'C');
            $pdf->SetFont('Courier', '', 8);
        }

        $pdf->SetFont('Courier', '', 10);

        for ($page = 2; $page <= max(1, $pageCount); $page++) {
            $tpl = $pdf->importPage($page);
            $pdf->AddPage('P', [self::PAGE_W, self::PAGE_H]);
            $pdf->useTemplate($tpl, 0, 0, self::PAGE_W, self::PAGE_H);
        }

        return $pdf->Output('S');
    }

    private function digitAt(Fpdi $pdf, float $centerX, float $y, string $char, int $debug, float $boxW = 4.5): void
    {
        if ($char === '') {
            return;
        }

        $pdf->SetFont('Courier', '', 10);
        $pdf->SetXY($centerX - ($boxW / 2), $y);
        $pdf->Cell($boxW, 3.2, $char, $debug, 0, 'C');
    }

    private function mark(Fpdi $pdf, float $centerX, float $y, int $debug): void
    {
        $pdf->SetFont('Courier', 'B', 11);
        $pdf->SetXY($centerX - 2.35, $y);
        $pdf->Cell(4.7, 3.8, 'X', $debug, 0, 'C');
        $pdf->SetFont('Courier', '', 10);
    }

    private function amountAt(Fpdi $pdf, float $bulletY, float $amount, int $debug): void
    {
        $abs = abs($amount);
        $pesoDigits = (string) ((int) floor($abs + 1e-9));
        $centDigits = str_pad((string) ((int) round(($abs - floor($abs)) * 100)), 2, '0', STR_PAD_LEFT);

        $slots = count(self::PESO_CX);
        if (strlen($pesoDigits) > $slots) {
            $pesoDigits = substr($pesoDigits, -$slots);
        }

        $padded = str_pad($pesoDigits, $slots, ' ', STR_PAD_LEFT);
        // Cell top so glyph centers in the white comb band (above the row’s bottom rule)
        $y = $bulletY - 2.0;

        $pdf->SetFont('Courier', '', 9);
        foreach (self::PESO_CX as $i => $cx) {
            $char = $padded[$i] ?? ' ';
            if ($char === ' ') {
                continue;
            }
            $pdf->SetXY($cx - 2.0, $y);
            $pdf->Cell(4.0, 2.8, $char, $debug, 0, 'C');
        }

        foreach (self::CENT_CX as $i => $cx) {
            $pdf->SetXY($cx - 2.0, $y);
            $pdf->Cell(4.0, 2.8, $centDigits[$i] ?? '0', $debug, 0, 'C');
        }
    }
}
