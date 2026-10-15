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

        CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->delete();

        LuIcctOffense::query()->forceDelete();

        app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();

        $this->assertSame(
            ReferenceDataBootstrapService::EXPECTED_ICCT_OFFENSE_COUNT,
            LuIcctOffense::query()->count(),
        );

        $this->assertTrue(
            CompanyDocumentForm::query()->where('code', 'hr_internal_memo')->where('is_active', true)->exists(),
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

        $labels = $form->fresh()->elements()->where('type', 'paragraph')->pluck('label')->implode("\n");
        $this->assertStringContainsString('Section 8', $labels);

        $this->assertSame(
            (string) CompanyDocumentHrLetterTemplatesSeeder::REVISION,
            trim((string) file_get_contents(storage_path(CompanyDocumentHrLetterTemplatesSeeder::REVISION_MARKER))),
        );
    }
}
