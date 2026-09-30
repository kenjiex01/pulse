<?php

namespace Tests\Unit;

use App\Support\CompanyDocumentInlineFormatting;
use PHPUnit\Framework\TestCase;

class CompanyDocumentInlineFormattingTest extends TestCase
{
    public function test_render_segment_preserves_allowed_tags_and_escapes_other_html(): void
    {
        $html = CompanyDocumentInlineFormatting::renderSegment('Hello <b>world</b> <script>alert(1)</script>');

        $this->assertSame('Hello <b>world</b> alert(1)', $html);
    }

    public function test_sanitize_keeps_only_allowed_tags(): void
    {
        $text = CompanyDocumentInlineFormatting::sanitize('Dear <i>Team</i><div>bad</div>');

        $this->assertSame('Dear <i>Team</i>bad', $text);
    }

    public function test_render_segment_converts_float_tags_to_spans(): void
    {
        $html = CompanyDocumentInlineFormatting::renderSegment(
            '<float-left>Control No. 001</float-left><float-right>Date: today</float-right>',
        );

        $this->assertStringContainsString('float:left', $html);
        $this->assertStringContainsString('float:right', $html);
        $this->assertStringContainsString('Control No. 001', $html);
        $this->assertStringContainsString('Date: today', $html);
    }

    public function test_sanitize_keeps_float_tags(): void
    {
        $text = CompanyDocumentInlineFormatting::sanitize('<float-left>Left</float-left>');

        $this->assertSame('<float-left>Left</float-left>', $text);
    }
}
