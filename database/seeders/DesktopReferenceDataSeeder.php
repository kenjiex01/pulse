<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\City;
use App\Models\Country;
use App\Models\Program;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Reference data sync for desktop upgrades — idempotent only.
 *
 * Heavy geographic/campus seeders run only when tables are empty.
 * Feature seeders (payroll, PHIM, etc.) always run on version bump.
 * Government tables are re-seeded on every desktop launch via GovernmentTablesBootstrapService.
 */
class DesktopReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! Country::query()->exists()) {
            $this->call(CountrySeeder::class);
        }

        if (! Region::query()->exists()) {
            $this->call(RegionSeeder::class);
        }

        if (! Province::query()->exists()) {
            $this->call(ProvinceSeeder::class);
        }

        if (! City::query()->exists()) {
            $this->call(CitySeeder::class);
        }

        if (! Campus::query()->exists()) {
            $this->call(CampusSeeder::class);
        }

        if (Campus::query()->exists()) {
            $this->call(CollegeSeeder::class);
        }

        if (! Program::query()->exists() && Campus::query()->exists()) {
            $this->call(ProgramSeeder::class);
        }

        $this->call([
            DesignationSeeder::class,
            PositionSeeder::class,
            RankSeeder::class,
            EmploymentTypeSeeder::class,
            EmployeeDepartmentSeeder::class,
            PayTypeSeeder::class,
            PayrollMaintenanceSeeder::class,
            PhilhealthMinimumDeductionTypeSeeder::class,
            SssMpfDeductionTypeSeeder::class,
            PayrollCalendarSeeder::class,
            PayrollBatchStatusSeeder::class,
            WithholdingTaxComputationSeeder::class,
            RateDefinitionSeeder::class,
            GovernmentTablesSeeder::class,
            PhilhealthMinimumSeeder::class,
            ReportSeeder::class,
            TimekeepingPolicySeeder::class,
            FlexiShiftCodeSeeder::class,
            TimeCaptureFormatSeeder::class,
            LuTemplateSeeder::class,
            UserRequestTypeSeeder::class,
        ]);
    }
}
