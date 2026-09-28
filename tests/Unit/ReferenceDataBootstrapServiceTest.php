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
}
