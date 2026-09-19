<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\Employee;
use App\Models\LuIcctOffense;
use App\Services\CompanyDocumentOffenseMemoService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IcctOffensePenaltySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDocumentOffenseMemoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(IcctOffensePenaltySeeder::class);
        $this->seed(\Database\Seeders\IcctOffenseSeeder::class);
    }

    public function test_frequency_increments_from_prior_sends_of_same_memo_template(): void
    {
        $service = app(CompanyDocumentOffenseMemoService::class);
        $employee = Employee::query()->create([
            'employee_number' => 'OFFENSE-TAG-001',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'offense.tag@example.com',
        ]);
        $offense = LuIcctOffense::query()->where('section_code', 'I.7')->firstOrFail();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'test_written_warning_freq',
            'name' => 'Test Written Warning',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'icct_offense_id' => $offense->icct_offense_id,
            'is_active' => true,
        ]);

        $this->assertSame(1, $service->nextFrequencyOrdinal($form, $employee));

        CompanyDocumentSendLog::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'submission_id' => null,
            'sent_by_user_id' => null,
            'sent_at' => now(),
        ]);

        $context = $service->enrichMemoContext($form, $employee, []);

        $this->assertSame(2, $context['offense_frequency_ordinal']);
        $this->assertSame('Second Offense', $context['offense_frequency_label']);
        $this->assertSame('Written Warning', $context['disciplinary_action']);
    }

    public function test_offense_tags_are_not_available_without_nature_of_offense(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->firstOrFail();
        $form->load('elements');

        $palette = \App\Support\CompanyDocumentElementCatalog::palette($form);
        $tagKeys = array_column($palette['TAGS'], 'tag_key');

        $this->assertNotContains('disciplinary_action', $tagKeys);
        $this->assertNotContains('offense_frequency', $tagKeys);
    }

    public function test_preview_disciplinary_action_falls_back_when_category_has_no_exact_frequency_row(): void
    {
        $service = app(CompanyDocumentOffenseMemoService::class);
        $offense = LuIcctOffense::query()->where('section_code', 'I.1')->firstOrFail();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'test_category_d_preview',
            'name' => 'Test Category D Preview',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'icct_offense_id' => $offense->icct_offense_id,
            'is_active' => true,
        ]);

        $context = $service->enrichMemoContext($form, null, []);

        $this->assertSame('Second Offense', $context['offense_frequency_label']);
        $this->assertSame('Dismissal', $context['disciplinary_action']);
    }

    public function test_icct_verbal_reprimand_shows_offense_tags_via_offense_block(): void
    {
        $form = CompanyDocumentForm::query()->where('code', 'hr_verbal_reprimand')->firstOrFail();
        $form->load('elements');

        $this->assertNull($form->icct_offense_id);
        $this->assertTrue($form->hasOffenseNatureConfigured());

        $palette = \App\Support\CompanyDocumentElementCatalog::palette($form);
        $tagKeys = array_column($palette['TAGS'], 'tag_key');

        $this->assertContains('disciplinary_action', $tagKeys);
        $this->assertContains('offense_frequency', $tagKeys);
    }

    public function test_offense_tags_appear_when_template_has_nature_of_offense(): void
    {
        $this->seed(\Database\Seeders\IcctOffenseSeeder::class);

        $offense = LuIcctOffense::query()->where('section_code', 'I.7')->firstOrFail();
        $form = CompanyDocumentForm::query()->create([
            'code' => 'test_offense_tags_palette',
            'name' => 'Test Offense Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'icct_offense_id' => $offense->icct_offense_id,
            'is_active' => true,
        ]);

        $palette = \App\Support\CompanyDocumentElementCatalog::palette($form);
        $tagKeys = array_column($palette['TAGS'], 'tag_key');

        $this->assertContains('disciplinary_action', $tagKeys);
        $this->assertContains('offense_frequency', $tagKeys);
    }

    public function test_offense_frequency_label_for_send_log_uses_submission_value_when_present(): void
    {
        $service = app(CompanyDocumentOffenseMemoService::class);
        $employee = Employee::query()->create([
            'employee_number' => 'OFFENSE-RPT-001',
            'first_name' => 'Report',
            'last_name' => 'Row',
            'email' => 'offense.report@example.com',
        ]);
        $offense = LuIcctOffense::query()->where('section_code', 'I.7')->firstOrFail();
        $form = CompanyDocumentForm::query()->create([
            'code' => 'test_offense_report_label',
            'name' => 'Test Offense Report Label',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'icct_offense_id' => $offense->icct_offense_id,
            'is_active' => true,
        ]);

        $submission = \App\Models\CompanyDocumentSubmission::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'form_version' => 1,
            'form_snapshot_json' => [],
            'submitted_by_user_id' => null,
            'status' => \App\Models\CompanyDocumentSubmission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);

        \App\Models\CompanyDocumentSubmissionValue::query()->create([
            'submission_id' => $submission->submission_id,
            'element_id' => null,
            'field_key' => 'offense_frequency',
            'value_text' => 'Third Offense',
        ]);

        $label = $service->offenseFrequencyLabelForSendLog(
            $form,
            $employee,
            $submission,
            now(),
        );

        $this->assertSame('Third Offense', $label);
    }
}
