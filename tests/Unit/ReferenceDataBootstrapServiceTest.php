<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentForm;
use App\Models\LuIcctOffense;
use App\Services\ReferenceDataBootstrapService;
use Database\Seeders\CompanyDocumentHrLetterTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(ReferenceDataBootstrapService::class, 'ensured');
        $property->setAccessible(true);
        $property->setValue(null, false);

        parent::tearDown();
    }

    public function test_auto_seeds_icct_offenses_and_hr_letter_templates(): void
    {
        $this->seed(CompanyDocumentHrLetterTemplatesSeeder::class);

        CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->delete();

        LuIcctOffense::query()->forceDelete();

        app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();

        $this->assertSame(
            ReferenceDataBootstrapService::EXPECTED_ICCT_OFFENSE_COUNT,
            LuIcctOffense::query()->count(),
        );

        $this->assertTrue(
            CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->where('is_active', true)->exists(),
        );
    }

    public function test_resyncs_hr_letter_templates_when_revision_bumps(): void
    {
        $this->seed(CompanyDocumentHrLetterTemplatesSeeder::class);

        $form = CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->firstOrFail();
        $form->elements()->update(['label' => 'Stale layout marker']);

        file_put_contents(
            storage_path(CompanyDocumentHrLetterTemplatesSeeder::REVISION_MARKER),
            (string) (CompanyDocumentHrLetterTemplatesSeeder::REVISION - 1),
        );

        app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();

        $stored = $form->fresh()->elements()->get()->map(function ($element): string {
            if ($element->type === 'table') {
                return json_encode($element->options_json);
            }

            return (string) $element->label;
        })->implode("\n");
        $this->assertStringContainsString('{{nature_of_offense}}', $stored);

        $this->assertSame(
            (string) CompanyDocumentHrLetterTemplatesSeeder::REVISION,
            trim((string) file_get_contents(storage_path(CompanyDocumentHrLetterTemplatesSeeder::REVISION_MARKER))),
        );
    }

    public function test_revision_bump_replaces_installed_company_documents(): void
    {
        $this->seed(CompanyDocumentHrLetterTemplatesSeeder::class);

        CompanyDocumentForm::query()->create([
            'code' => 'campus_custom_memo',
            'name' => 'Campus Custom Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
        ]);

        $absences = CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->firstOrFail();
        $absences->update([
            'name' => 'Old installed name',
            'description' => 'Edited on an installed campus',
        ]);
        $absences->elements()->update(['label' => 'Old installed body']);

        file_put_contents(
            storage_path(CompanyDocumentHrLetterTemplatesSeeder::REVISION_MARKER),
            (string) (CompanyDocumentHrLetterTemplatesSeeder::REVISION - 1),
        );

        app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();

        $this->assertSoftDeleted('tbl_company_document_forms', [
            'code' => 'campus_custom_memo',
        ]);

        $fresh = CompanyDocumentForm::query()->where('code', 'hr_memo_absences_tardiness')->firstOrFail();
        $this->assertSame('Internal Memo — Absences & Tardiness', $fresh->name);
        $this->assertStringNotContainsString('Old installed', (string) $fresh->description);

        $stored = $fresh->elements()->get()->map(function ($element): string {
            if ($element->type === 'table') {
                return json_encode($element->options_json);
            }

            return (string) $element->label;
        })->implode("\n");
        $this->assertStringContainsString('{{nature_of_offense}}', $stored);
        $this->assertStringNotContainsString('Old installed body', $stored);

        $expected = CompanyDocumentHrLetterTemplatesSeeder::TEMPLATE_CODES;
        sort($expected);
        $this->assertSame(
            $expected,
            CompanyDocumentForm::query()->orderBy('code')->pluck('code')->all(),
        );
    }
}
