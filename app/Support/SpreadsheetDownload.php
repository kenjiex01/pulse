<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Desktop NativePHP PHP binaries ship without ext-xmlwriter, which Xlsx requires.
 * Prefer Xlsx when available; otherwise fall back to Excel 97-2003 (.xls).
 */
class SpreadsheetDownload
{
    public static function supportsXlsx(): bool
    {
        return extension_loaded('xmlwriter') || class_exists(\XMLWriter::class, false);
    }

    /**
     * @return array{0: IWriter, 1: string, 2: string} writer, filename, content-type
     */
    public static function writerFor(Spreadsheet $spreadsheet, string $baseFilename): array
    {
        $baseFilename = preg_replace('/\.(xlsx|xls)$/i', '', $baseFilename) ?: $baseFilename;

        if (self::supportsXlsx()) {
            return [
                new Xlsx($spreadsheet),
                $baseFilename.'.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        return [
            new Xls($spreadsheet),
            $baseFilename.'.xls',
            'application/vnd.ms-excel',
        ];
    }

    public static function stream(Spreadsheet $spreadsheet, string $baseFilename): StreamedResponse
    {
        [$writer, $filename, $contentType] = self::writerFor($spreadsheet, $baseFilename);

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }
}
