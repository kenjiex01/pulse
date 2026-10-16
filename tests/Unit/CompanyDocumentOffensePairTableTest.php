<?php

namespace Tests\Unit;

use App\Support\CompanyDocumentOffensePairTable;
use PHPUnit\Framework\TestCase;

class CompanyDocumentOffensePairTableTest extends TestCase
{
    public function test_pair_line_becomes_bordered_table_and_other_lines_stay_text(): void
    {
        $html = CompanyDocumentOffensePairTable::render(
            "Please be reminded:\n\n{{disciplinary_action}} {{nature_of_offense}}\n\nWe look forward.",
            fn (string $text): string => $text,
            fn (string $tagKey): string => $tagKey === 'nature_of_offense' ? 'AWOL' : 'Written Warning',
        );

        $this->assertStringContainsString('Please be reminded:', $html);
        $this->assertStringContainsString('We look forward.', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('border:1px solid #111', $html);
        $this->assertStringContainsString('>Nature of offense</th>', $html);
        $this->assertStringContainsString('>Disciplinary action</th>', $html);
        $this->assertStringContainsString('>AWOL</td>', $html);
        $this->assertStringContainsString('>Written Warning</td>', $html);
        $this->assertStringNotContainsString('{{nature_of_offense}}', $html);
    }

    public function test_tags_mixed_with_words_stay_inline(): void
    {
        $html = CompanyDocumentOffensePairTable::render(
            'See {{nature_of_offense}} and {{disciplinary_action}} today.',
            fn (string $text): string => $text,
            fn (): string => 'unused',
        );

        $this->assertSame('See {{nature_of_offense}} and {{disciplinary_action}} today.', $html);
    }
}
