<?php

namespace Tests\Unit;

use App\Support\CompanyDocumentMemoTable;
use PHPUnit\Framework\TestCase;

class CompanyDocumentMemoTableTest extends TestCase
{
    public function test_html_renders_a_bordered_header_and_body(): void
    {
        $html = CompanyDocumentMemoTable::html([
            ['Nature of offense', 'Disciplinary action'],
            ['AWOL', 'Written Warning'],
        ]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('border:1px solid #111', $html);
        $this->assertStringContainsString('>Nature of offense</th>', $html);
        $this->assertStringContainsString('>AWOL</td>', $html);
    }

    public function test_normalize_rows_pads_short_rows_and_drops_extra_columns(): void
    {
        $rows = CompanyDocumentMemoTable::normalizeRows([
            ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
            ['Only one'],
        ]);

        $this->assertCount(2, $rows);
        $this->assertCount(6, $rows[0]);
        $this->assertSame('Only one', $rows[1][0]);
        $this->assertSame('', $rows[1][5]);
    }

    public function test_content_height_grows_with_wrapped_cell_text(): void
    {
        $height = CompanyDocumentMemoTable::contentHeight([
            ['Nature of offense', 'Disciplinary action'],
            ['Absence without official leave (AWOL) for five (5) consecutive days or more.', 'Written Warning'],
        ], 640);

        $this->assertGreaterThan(96, $height);
    }
}
