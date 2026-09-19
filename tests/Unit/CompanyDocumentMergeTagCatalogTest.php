<?php

namespace Tests\Unit;

use App\Support\CompanyDocumentMergeTagCatalog;
use Tests\TestCase;

class CompanyDocumentMergeTagCatalogTest extends TestCase
{
    public function test_palette_includes_requested_merge_tags(): void
    {
        $items = CompanyDocumentMergeTagCatalog::paletteItems();

        $this->assertNotEmpty($items);
        $this->assertContains('employee_full_name', array_column($items, 'tag_key'));
        $this->assertContains('employee_first_name', array_column($items, 'tag_key'));
        $this->assertContains('employee_middle_name', array_column($items, 'tag_key'));
        $this->assertContains('employee_last_name', array_column($items, 'tag_key'));
        $this->assertContains('employee_number', array_column($items, 'tag_key'));
        $this->assertContains('count_of_lates', array_column($items, 'tag_key'));
        $this->assertContains('count_of_absences', array_column($items, 'tag_key'));
        $this->assertContains('count_of_undertimes', array_column($items, 'tag_key'));
        $this->assertContains('late_dates', array_column($items, 'tag_key'));
        $this->assertContains('undertime_dates', array_column($items, 'tag_key'));
        $this->assertContains('absent_dates', array_column($items, 'tag_key'));
        $this->assertContains('current_date', array_column($items, 'tag_key'));
        $this->assertContains('current_time', array_column($items, 'tag_key'));
        $this->assertContains('current_datetime', array_column($items, 'tag_key'));
        $this->assertNotContains('disciplinary_action', array_column($items, 'tag_key'));
        $this->assertNotContains('offense_frequency', array_column($items, 'tag_key'));
    }

    public function test_offense_palette_includes_disciplinary_tags(): void
    {
        $items = CompanyDocumentMergeTagCatalog::paletteItems(true);
        $tagKeys = array_column($items, 'tag_key');

        $this->assertContains('disciplinary_action', $tagKeys);
        $this->assertContains('offense_frequency', $tagKeys);
        $this->assertSame('disciplinary_action', $tagKeys[0] ?? null);
        $this->assertSame('offense_frequency', $tagKeys[1] ?? null);
    }
}
