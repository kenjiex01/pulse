<?php

namespace Tests\Unit;

use App\Services\CompanyDocumentMergeTagService;
use Tests\TestCase;

class CompanyDocumentMergeTagServiceTest extends TestCase
{
    public function test_render_inline_tags_for_design_shows_tag_labels(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $html = $service->renderInlineTagsForDesign('Dear {{employee_full_name}}, lates: {{count_of_lates}}');

        $this->assertStringContainsString('Employee Full name', $html);
        $this->assertStringContainsString('Count of lates', $html);
        $this->assertStringNotContainsString('Juan Dela Cruz', $html);
    }

    public function test_resolve_inline_tags_replaces_tokens_with_values(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $text = $service->resolveInlineTags('Date: {{current_date}}');

        $this->assertStringNotContainsString('{{current_date}}', $text);
        $this->assertStringStartsWith('Date: ', $text);
    }

    public function test_render_inline_tags_for_design_preserves_bold_formatting(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $html = $service->renderInlineTagsForDesign('Employee Name: <b>{{employee_full_name}}</b>');

        $this->assertStringContainsString('<b>', $html);
        $this->assertStringContainsString('Employee Full name', $html);
    }

    public function test_resolve_late_dates_from_memo_context(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $resolved = $service->resolve('late_dates', null, [
            'violation_type' => 'late',
            'violation_count' => 2,
            'selected_dates' => ['2026-08-11', '2026-08-12'],
        ]);

        $this->assertSame('Aug 11, 2026; Aug 12, 2026', $resolved);
    }

    public function test_resolve_late_dates_empty_when_violation_type_mismatch(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $resolved = $service->resolve('late_dates', null, [
            'violation_type' => 'absent',
            'violation_count' => 2,
            'selected_dates' => ['2026-08-11'],
        ]);

        $this->assertSame('', $resolved);
    }

    public function test_resolve_offense_tags_from_memo_context(): void
    {
        $service = app(CompanyDocumentMergeTagService::class);

        $context = [
            'disciplinary_action' => 'Written Warning',
            'offense_frequency_label' => 'Second Offense',
        ];

        $this->assertSame('Written Warning', $service->resolve('disciplinary_action', null, $context));
        $this->assertSame('Second Offense', $service->resolve('offense_frequency', null, $context));
    }
}
