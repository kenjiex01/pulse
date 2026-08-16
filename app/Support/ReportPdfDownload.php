<?php

namespace App\Support;

use App\Services\Reports\ReportGenerationResult;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPdfDownload
{
    public static function stream(ReportGenerationResult $result, string $baseFilename): StreamedResponse
    {
        $preview = [
            'title' => $result->title,
            'headers' => $result->headers,
            'rows' => $result->rows,
            'meta' => $result->meta,
        ];

        $html = view('payroll.reports.pdf-document', ['preview' => $preview])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        self::applyPaper($dompdf, $result);
        $dompdf->render();

        $filename = Str::of($baseFilename)
            ->replaceMatches('/\.pdf$/i', '')
            ->append('.pdf')
            ->toString();

        $pdf = $dompdf->output();

        return response()->streamDownload(static function () use ($pdf): void {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private static function applyPaper(Dompdf $dompdf, ReportGenerationResult $result): void
    {
        if (($result->meta['layout'] ?? null) === 'icct_per_hour') {
            // Dompdf clips/overlaps if we force ~84 columns onto A4 landscape.
            // Use a custom wide canvas (points) so every column fits at a small font.
            $columns = max(count($result->headers), 1);
            $width = max(1200.0, ($columns * 32.0) + 72.0);
            $height = 792.0; // letter portrait height; width > height ⇒ landscape sheet

            // Pass as-is; do not use 'landscape' (Dompdf would swap the axes).
            $dompdf->setPaper([0.0, 0.0, $width, $height]);

            return;
        }

        $dompdf->setPaper('A4', self::orientationFor($result));
    }

    private static function orientationFor(ReportGenerationResult $result): string
    {
        if (in_array($result->meta['layout'] ?? null, ['payslip', 'attendance_view'], true)) {
            return 'landscape';
        }

        if (in_array($result->meta['layout'] ?? null, ['bir_1601c', 'bir_2316'], true)) {
            return 'portrait';
        }

        if (in_array($result->meta['layout'] ?? null, ['sss', 'philhealth', 'pagibig', 'bir_tax'], true)) {
            return 'landscape';
        }

        return count($result->headers) > 8 ? 'landscape' : 'portrait';
    }
}
