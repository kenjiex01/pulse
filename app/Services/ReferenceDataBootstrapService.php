<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\City;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\Country;
use App\Models\LuIcctOffense;
use App\Models\PayType;
use App\Models\Province;
use App\Models\Region;
use Database\Seeders\CampusSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CompanyDocumentIcctOffensesSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IcctOffenseSeeder;
use Database\Seeders\PayTypeSeeder;
use Database\Seeders\PayrollMaintenanceSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReferenceDataBootstrapService
{
    public const EXPECTED_ICCT_OFFENSE_COUNT = 93;

    private static bool $ensured = false;

    /**
     * Repair missing lookup rows on desktop — safe to run every app open.
     *
     * Desktop users cannot run artisan manually after reinstall; partial seeds or
     * wiped SQLite files left pay type / address dropdowns empty.
     */
    public function ensureCriticalLookups(): void
    {
        if (self::$ensured) {
            return;
        }

        self::$ensured = true;

        if (! Schema::hasTable('lu_pay_types')) {
            return;
        }

        try {
            if (Schema::hasTable('tbl_campuses') && $this->campusesIncomplete()) {
                $this->runSeeder(CampusSeeder::class);
            }

            if ($this->payTypesIncomplete()) {
                $this->runSeeder(PayTypeSeeder::class);
            }

            if (
                Schema::hasTable('lu_basic_computations')
                && BasicComputation::query()->count() < 2
            ) {
                $this->runSeeder(PayrollMaintenanceSeeder::class);
            }

            if (Schema::hasTable('tbl_countries') && ! Country::query()->exists()) {
                $this->runSeeder(CountrySeeder::class);
            }

            if (Schema::hasTable('tbl_regions') && ! Region::query()->exists()) {
                $this->runSeeder(RegionSeeder::class);
            }

            if (Schema::hasTable('tbl_provinces') && ! Province::query()->exists()) {
                $this->runSeeder(ProvinceSeeder::class);
            }

            if (Schema::hasTable('tbl_cities') && ! City::query()->exists()) {
                $this->runSeeder(CitySeeder::class);
            }

            if (Schema::hasTable('lu_icct_offenses') && $this->icctOffensesIncomplete()) {
                $this->runSeeder(IcctOffenseSeeder::class);
            }

            if (Schema::hasTable('tbl_company_document_forms') && $this->icctOffenseMemosNeedSync()) {
                $this->runSeeder(CompanyDocumentIcctOffensesSeeder::class);
            }
        } catch (Throwable $exception) {
            Log::error('Reference data bootstrap failed — dropdowns may be incomplete.', [
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    private function campusesIncomplete(): bool
    {
        $expectedCodes = ['AG', 'UA', 'BI', 'CA', 'CO', 'DC', 'SA', 'SU', 'TA', 'GH', 'ND', 'WR', 'O8', 'F6'];

        $existingCodes = Campus::query()
            ->whereIn('campus_code', $expectedCodes)
            ->pluck('campus_code')
            ->all();

        return count(array_unique($existingCodes)) < count($expectedCodes);
    }

    private function payTypesIncomplete(): bool
    {
        $requiredIds = [
            PayType::DAILY,
            PayType::WEEKLY,
            PayType::SEMI_MONTHLY,
            PayType::MONTHLY,
        ];

        $existingIds = PayType::query()
            ->whereIn('pay_type_id', $requiredIds)
            ->pluck('pay_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return count(array_unique($existingIds)) < count($requiredIds);
    }

    private function icctOffensesIncomplete(): bool
    {
        return LuIcctOffense::withTrashed()->count() < self::EXPECTED_ICCT_OFFENSE_COUNT;
    }

    private function icctOffenseMemosNeedSync(): bool
    {
        if (! Schema::hasTable('tbl_company_document_elements')) {
            return ! CompanyDocumentForm::query()->where('code', 'hr_notice_to_explain')->exists();
        }

        if (! CompanyDocumentForm::query()->where('code', 'hr_notice_to_explain')->exists()) {
            return true;
        }

        $elements = CompanyDocumentElement::query()
            ->where('field_key', 'nature_of_offense')
            ->get(['type', 'options_json']);

        if ($elements->isEmpty()) {
            return true;
        }

        $minimumChoices = self::EXPECTED_ICCT_OFFENSE_COUNT - 3;

        foreach ($elements as $element) {
            if ($element->type !== CompanyDocumentElement::TYPE_DROPDOWN) {
                return true;
            }

            $choices = is_array($element->options_json)
                ? ($element->options_json['choices'] ?? [])
                : [];

            if (count($choices) < $minimumChoices) {
                return true;
            }
        }

        return false;
    }

    private function runSeeder(string $class): void
    {
        Artisan::call('db:seed', [
            '--class' => $class,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
}
