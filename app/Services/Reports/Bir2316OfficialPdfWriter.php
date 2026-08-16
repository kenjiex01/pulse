<?php

namespace App\Services\Reports;

use setasign\Fpdi\Fpdi;

/**
 * Stamp values onto BIR Form 2316 (September 2021 ENCS, 215.9 × 330.2 mm).
 *
 * Right-column items 29–52 and left summary items 19–28 match Bir2316FormMapper keys.
 */
class Bir2316OfficialPdfWriter
{
    private const TEMPLATE = 'bir-forms/BIR2316.pdf';

    private const PAGE_W = 215.9;

    private const PAGE_H = 330.2;

    /** @var array<string, float> */
    private const AMOUNT_Y = [
        'item_29' => 49.9,
        'item_30' => 56.6,
        'item_31' => 63.6,
        'item_32' => 70.8,
        'item_33' => 77.8,
        'item_34' => 84.7,
        'item_35' => 91.4,
        'item_36' => 98.2,
        'item_37' => 105.0,
        'item_38' => 111.7,
        'item_39' => 125.1,
        'item_40' => 131.9,
        'item_41' => 138.6,
        'item_42' => 145.5,
        'item_43' => 152.2,
        'item_44a' => 161.9,
        'item_44b' => 168.2,
        'item_45' => 177.5,
        'item_46' => 184.1,
        'item_47' => 191.0,
        'item_48' => 197.8,
        'item_49' => 204.7,
        'item_50' => 211.6,
        'item_51a' => 221.8,
        'item_51b' => 228.7,
        'item_52' => 235.4,
    ];

    /** @var array<string, float> */
    private const SUMMARY_Y = [
        'item_19' => 180.7,
        'item_20' => 187.6,
        'item_21' => 194.4,
        'item_22' => 201.3,
        'item_23' => 208.0,
        'item_24' => 214.9,
        'item_25a' => 221.7,
        'item_25b' => 228.7,
        'item_26' => 235.6,
        'item_27' => 242.6,
        'item_28' => 249.5,
    ];

    /**
     * @param  list<array<string, mixed>>  $certificates
     * @param  array<string, mixed>  $meta
     */
    public function write(array $certificates, array $meta): string
    {
        $template = resource_path(self::TEMPLATE);

        if (! is_file($template)) {
            throw new \RuntimeException('BIR 2316 template not found at '.$template);
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->setSourceFile($template);
        $tpl = $pdf->importPage(1);

        $employer = $meta['employer'] ?? [];
        $signatory = strtoupper((string) ($meta['signatory_name'] ?? ''));
        $debug = 0;

        foreach ($certificates as $certificate) {
            $pdf->AddPage('P', [self::PAGE_W, self::PAGE_H]);
            $pdf->useTemplate($tpl, 0, 0, self::PAGE_W, self::PAGE_H);

            $pdf->SetFont('Courier', '', 9);

            $pdf->SetXY(44.0, 33.3);
            $pdf->Cell(25.0, 5.3, (string) ($certificate['for_year'] ?? ''), $debug, 0, 'C');

            $from = $this->mmdd($certificate['period_from_mm'] ?? '', $certificate['period_from_dd'] ?? '');
            $to = $this->mmdd($certificate['period_to_mm'] ?? '', $certificate['period_to_dd'] ?? '');
            $pdf->SetXY(136.5, 33.4);
            $pdf->Cell(24.6, 5.2, $from, $debug, 0, 'C');
            $pdf->SetXY(180.8, 33.4);
            $pdf->Cell(24.7, 5.2, $to, $debug, 0, 'C');

            $this->tinRow($pdf, 43.3, $this->tin12($certificate['tin'] ?? ''), $debug);

            $pdf->SetFont('Courier', '', 7);
            $pdf->SetXY(14.9, 52.4);
            $pdf->Cell(74.6, 5.3, strtoupper((string) ($certificate['employee_name'] ?? '')), $debug, 0);
            $pdf->SetFont('Courier', '', 9);
            $pdf->SetXY(93.3, 52.4);
            $pdf->Cell(13.4, 5.3, (string) ($certificate['employee_rdo'] ?? ''), $debug, 0, 'C');

            $pdf->SetFont('Courier', '', 7);
            $pdf->SetXY(15.0, 62.3);
            $pdf->Cell(74.4, 5.2, strtoupper((string) ($certificate['address'] ?? '')), $debug, 0);
            $pdf->SetFont('Courier', '', 9);
            $pdf->SetXY(92.2, 62.3);
            $pdf->Cell(16.9, 5.2, substr((string) ($certificate['postal_code'] ?? ''), 0, 4), $debug, 0, 'C');

            $home = strtoupper((string) ($certificate['local_home_address'] ?? ''));
            if ($home !== '') {
                $pdf->SetFont('Courier', '', 7);
                $pdf->SetXY(15.0, 71.2);
                $pdf->Cell(74.4, 5.2, $home, $debug, 0);
                $pdf->SetFont('Courier', '', 9);
            }

            $dob = (string) ($certificate['birth_date_mmddyyyy'] ?? '');
            if (strlen($dob) === 8) {
                $dobXs = [16.5, 20.9, 25.4, 30.1, 34.3, 38.7, 43.2, 47.8];
                foreach ($dobXs as $i => $x) {
                    $this->digitAt($pdf, $x, 89.7, 4.4, 5.1, $dob[$i], $debug);
                }
            }

            $phone = preg_replace('/\D/', '', (string) ($certificate['phone'] ?? '')) ?? '';
            if ($phone !== '') {
                $phoneXs = [59.0, 63.6, 68.2, 72.6, 77.4, 82.2, 86.4, 90.8, 95.2, 99.6, 104.4];
                foreach ($phoneXs as $i => $x) {
                    $this->digitAt($pdf, $x, 89.7, 4.4, 5.3, $phone[$i] ?? '', $debug);
                }
            }

            if (! empty($certificate['is_mwe'])) {
                $pdf->SetXY(74.0, 96.1);
                $pdf->Cell(34.8, 5.2, $this->money($certificate['smw_rate_per_day'] ?? 0), $debug, 0, 'R');
                $pdf->SetXY(74.0, 102.6);
                $pdf->Cell(34.8, 5.2, $this->money($certificate['smw_rate_per_month'] ?? 0), $debug, 0, 'R');
                $pdf->SetFont('Courier', 'B', 10);
                $pdf->SetXY(16.8, 109.3);
                $pdf->Cell(4.8, 4.4, 'X', $debug, 0, 'C');
                $pdf->SetFont('Courier', '', 9);
            }

            $this->tinRow($pdf, 118.3, $this->tin12($employer['tin'] ?? ''), $debug);

            $pdf->SetFont('Courier', '', 8);
            $pdf->SetXY(14.9, 127.3);
            $pdf->Cell(93.9, 5.3, strtoupper((string) ($employer['name'] ?? '')), $debug, 0);

            $address = (string) ($employer['address'] ?? '');
            $pdf->SetFont('Courier', '', strlen($address) < 40 ? 8 : 6);
            $pdf->SetXY(15.0, 136.6);
            $pdf->Cell(74.4, 5.3, strtoupper($address), $debug, 0);
            $pdf->SetFont('Courier', '', 9);
            $pdf->SetXY(91.5, 136.9);
            $pdf->Cell(16.9, 5.0, substr((string) ($employer['zip'] ?? ''), 0, 4), $debug, 0, 'C');

            // Item 15 Main Employer
            $pdf->SetFont('Courier', 'B', 10);
            $pdf->SetXY(40.9, 143.3);
            $pdf->Cell(4.8, 4.4, 'X', $debug, 0, 'C');
            $pdf->SetFont('Courier', '', 9);

            foreach (self::AMOUNT_Y as $key => $y) {
                $this->amountAt($pdf, $y, $certificate[$key] ?? 0, $debug);
            }

            if (($certificate['item_44a'] ?? 0) > 0 || ($certificate['item_44a_label'] ?? '') !== '') {
                $pdf->SetFont('Courier', '', 7);
                $pdf->SetXY(120.6, 161.9);
                $pdf->Cell(46.9, 5.4, (string) ($certificate['item_44a_label'] ?: 'Other Earnings'), $debug, 0, 'L');
                $pdf->SetFont('Courier', '', 9);
            }

            if (($certificate['item_51a'] ?? 0) > 0 || ($certificate['item_51a_label'] ?? '') !== '') {
                $pdf->SetFont('Courier', '', 7);
                $pdf->SetXY(120.4, 221.8);
                $pdf->Cell(47.9, 5.4, (string) ($certificate['item_51a_label'] ?: 'Other Earnings'), $debug, 0, 'L');
                $pdf->SetFont('Courier', '', 9);
            }

            foreach (self::SUMMARY_Y as $key => $y) {
                $this->summaryAt($pdf, $y, $certificate[$key] ?? 0, $debug);
            }

            $pdf->SetFont('Courier', '', 8);
            $employeePrinted = strtoupper((string) ($certificate['employee_name_upper'] ?? $certificate['employee_name'] ?? ''));
            $pdf->SetXY(22.0, 265.5);
            $pdf->Cell(88.5, 3.5, $signatory, $debug, 0, 'C');
            $pdf->SetXY(12.1, 276.5);
            $pdf->Cell(103.1, 3.5, $employeePrinted, $debug, 0, 'C');
            $pdf->SetXY(12.1, 306.5);
            $pdf->Cell(110.0, 3.5, $signatory, $debug, 0, 'C');
            $pdf->SetXY(118.0, 313.0);
            $pdf->Cell(88.0, 3.5, $employeePrinted, $debug, 0, 'C');
        }

        if ($certificates === []) {
            $pdf->AddPage('P', [self::PAGE_W, self::PAGE_H]);
            $pdf->useTemplate($tpl, 0, 0, self::PAGE_W, self::PAGE_H);
            $pdf->SetFont('Courier', '', 10);
            $pdf->SetXY(20, 40);
            $pdf->Cell(0, 6, 'No employees selected or no compensation found.', 0, 1);
        }

        return $pdf->Output('S');
    }

    private function tinRow(Fpdi $pdf, float $y, string $tin, int $debug): void
    {
        if ($tin === '') {
            return;
        }

        $xs = [30.4, 34.9, 39.4, 47.9, 52.4, 56.9, 65.5, 69.9, 74.4, 83.0, 87.8, 93.0];
        foreach ($xs as $i => $x) {
            $this->digitAt($pdf, $x, $y, 4.5, 5.3, $tin[$i] ?? '', $debug);
        }
    }

    private function digitAt(Fpdi $pdf, float $x, float $y, float $w, float $h, string $char, int $debug): void
    {
        $pdf->SetFont('Courier', '', 9);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $char, $debug, 0, 'C');
    }

    private function amountAt(Fpdi $pdf, float $y, mixed $amount, int $debug): void
    {
        $pdf->SetFont('Courier', '', 8);
        $pdf->SetXY(170.7, $y);
        $pdf->Cell(35.9, 5.3, $this->money($amount), $debug, 0, 'R');
    }

    private function summaryAt(Fpdi $pdf, float $y, mixed $amount, int $debug): void
    {
        $pdf->SetFont('Courier', '', 8);
        $pdf->SetXY(73.2, $y);
        $pdf->Cell(35.9, 5.4, $this->money($amount), $debug, 0, 'R');
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    private function mmdd(mixed $mm, mixed $dd): string
    {
        $month = str_pad(preg_replace('/\D/', '', (string) $mm) ?? '', 2, '0', STR_PAD_LEFT);
        $day = str_pad(preg_replace('/\D/', '', (string) $dd) ?? '', 2, '0', STR_PAD_LEFT);

        if ($month === '00' && $day === '00') {
            return '';
        }

        return $month.'/'.$day;
    }

    private function tin12(mixed $tin): string
    {
        $digits = preg_replace('/\D/', '', (string) $tin) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, 0, 12), 12, '0', STR_PAD_RIGHT);
    }
}
