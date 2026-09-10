<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

class PayslipPdfProtector
{
    /**
     * @param  list<string>  $permissions
     */
    public function protect(string $pdfBinary, string $password, array $permissions = ['print', 'copy']): string
    {
        if ($password === '') {
            throw new RuntimeException('PDF password cannot be empty.');
        }

        $pdf = new Fpdi('L', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetProtection($permissions, $password, null, 0, null);

        $reader = StreamReader::createByString($pdfBinary);
        $pageCount = $pdf->setSourceFile($reader);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        return $pdf->Output('', 'S');
    }

    public static function passwordFromBirthDate(mixed $birthDate): ?string
    {
        if ($birthDate === null || $birthDate === '') {
            return null;
        }

        $parsed = $birthDate instanceof CarbonInterface
            ? $birthDate
            : Carbon::parse((string) $birthDate);

        return $parsed->format('Ymd');
    }
}
