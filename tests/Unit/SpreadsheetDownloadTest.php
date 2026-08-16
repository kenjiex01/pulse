<?php

namespace Tests\Unit;

use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpreadsheetDownloadTest extends TestCase
{
    #[Test]
    public function it_picks_a_writer_compatible_with_available_php_extensions(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['A', 'B'], [1, 2]], null, 'A1');

        [$writer, $filename, $contentType] = SpreadsheetDownload::writerFor($spreadsheet, 'Report_Test.xlsx');

        if (SpreadsheetDownload::supportsXlsx()) {
            $this->assertInstanceOf(Xlsx::class, $writer);
            $this->assertStringEndsWith('.xlsx', $filename);
            $this->assertStringContainsString('spreadsheetml', $contentType);
        } else {
            $this->assertInstanceOf(Xls::class, $writer);
            $this->assertStringEndsWith('.xls', $filename);
            $this->assertSame('application/vnd.ms-excel', $contentType);
        }
    }
}
