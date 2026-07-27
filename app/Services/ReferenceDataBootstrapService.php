<?php

namespace App\Services;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\City;
use App\Models\Country;
use App\Models\PayType;
use App\Models\Province;
use App\Models\Region;
use Database\Seeders\CampusSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
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
    /**
     * Repair missing lookup rows on desktop — safe to run every app open.
     *
     * Desktop users cannot run artisan manually after reinstall; partial seeds or
     * wiped SQLite files left pay type / address dropdowns empty.
     */
    public function ensureCriticalLookups(): void
    {
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
        } catch (Throwable $exception) {
            Log::error('Reference data bootstrap failed — dropdowns may be incomplete.', [
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }

    private function campusesIncomplete(): bool
    {
        $expectedCodes = ['AG', 'UA', 'BI', 'CA', 'CO', 'DC', 'SA', 'SU', 'TA'];

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

    private function runSeeder(string $class): void
    {
        Artisan::call('db:seed', [
            '--class' => $class,
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
}
