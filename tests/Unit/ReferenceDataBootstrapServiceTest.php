<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\LuIcctOffense;
use App\Services\ReferenceDataBootstrapService;
use Database\Seeders\CompanyDocumentIcctOffensesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_seeds_icct_offenses_and_syncs_nature_dropdown(): void
    {
        $this->seed(CompanyDocumentIcctOffensesSeeder::class);

        CompanyDocumentElement::query()
            ->where('field_key', 'nature_of_offense')
            ->update([
                'type' => CompanyDocumentElement::TYPE_LONG_TEXT,
                'options_json' => null,
            ]);

        LuIcctOffense::query()->forceDelete();

        app(ReferenceDataBootstrapService::class)->ensureCriticalLookups();

        $this->assertSame(
            ReferenceDataBootstrapService::EXPECTED_ICCT_OFFENSE_COUNT,
            LuIcctOffense::query()->count(),
        );

        $nte = CompanyDocumentForm::query()->where('code', 'hr_notice_to_explain')->firstOrFail();
        $natureField = $nte->elements()->where('field_key', 'nature_of_offense')->firstOrFail();

        $this->assertSame(CompanyDocumentElement::TYPE_DROPDOWN, $natureField->type);
        $this->assertGreaterThanOrEqual(90, count($natureField->options_json['choices'] ?? []));
    }
}
