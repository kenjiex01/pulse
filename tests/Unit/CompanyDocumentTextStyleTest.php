<?php

namespace Tests\Unit;

use App\Support\CompanyDocumentTextStyle;
use PHPUnit\Framework\TestCase;

class CompanyDocumentTextStyleTest extends TestCase
{
    public function test_normalize_applies_defaults_for_invalid_values(): void
    {
        $normalized = CompanyDocumentTextStyle::normalize([
            'font_family' => 'Comic Sans MS',
            'font_size' => 200,
            'font_color' => 'red',
        ]);

        $this->assertSame('', $normalized['font_family']);
        $this->assertSame(72, $normalized['font_size']);
        $this->assertSame('#4B5563', $normalized['font_color']);
    }

    public function test_inline_style_includes_font_family_when_set(): void
    {
        $style = CompanyDocumentTextStyle::inlineStyle([
            'font_family' => 'Georgia, serif',
            'font_size' => 18,
            'font_color' => '#111827',
        ]);

        $this->assertSame('font-family:Georgia, serif;font-size:18px;color:#111827;', $style);
    }

    public function test_inline_style_omits_font_family_for_system_default(): void
    {
        $style = CompanyDocumentTextStyle::inlineStyle([
            'font_family' => '',
            'font_size' => 14,
            'font_color' => '#4B5563',
        ]);

        $this->assertSame('font-size:14px;color:#4B5563;', $style);
    }
}
