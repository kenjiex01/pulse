<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference / lookup data only. Does not seed employees or transactional records.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CampusSeeder::class,
            DesignationSeeder::class,
            PositionSeeder::class,
            RankSeeder::class,
            EmploymentTypeSeeder::class,
            EmployeeDepartmentSeeder::class,
            CollegeSeeder::class,
            ProgramSeeder::class,
            PayrollMaintenanceSeeder::class,
            PayrollCalendarSeeder::class,
            PayrollBatchStatusSeeder::class,
            WithholdingTaxComputationSeeder::class,
            RateDefinitionSeeder::class,
            GovernmentTablesSeeder::class,
            TimekeepingPolicySeeder::class,
            TimeCaptureFormatSeeder::class,
            LuTemplateSeeder::class,
            UserRequestTypeSeeder::class,
        ]);
    }
}
