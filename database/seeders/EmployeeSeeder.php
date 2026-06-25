<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Services\EmployeeEmploymentSync;
use Illuminate\Database\Seeder;

/**
 * Sample employees for local development only.
 * Not included in DatabaseSeeder — run manually if needed:
 *   php artisan db:seed --class=EmployeeSeeder
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $mainCampus = Campus::query()->where('campus_code', 'CA')->first();
        $antipoloCampus = Campus::query()->where('campus_code', 'UA')->first();

        $employees = [
            [
                'employee_number' => '2026-00001',
                'campus_id' => $mainCampus?->campus_id,
                'first_name' => 'Maria',
                'middle_name' => 'L.',
                'last_name' => 'Santos',
                'suffix' => null,
                'is_hybrid' => false,
                'email' => 'maria.santos@icct.edu.ph',
                'phone' => '09171234567',
                'department' => 'Human Resource',
                'college' => 'Human Resource',
                'campus' => 'CA',
                'employment_status' => 'active',
                'compliance_status' => 'compliant',
                'is_active' => true,
                'birth_date' => '1990-05-12',
                'gender' => 'female',
                'civil_status' => 'single',
                'nationality' => 'Filipino',
                'is_confidential' => false,
                'extended_profile' => [
                    'family_members' => [],
                    'employment_history' => [
                        ['company_name' => 'ICCT Colleges', 'position' => 'HR Assistant', 'from_date' => '2020-01-01', 'to_date' => '2023-12-31'],
                    ],
                ],
                'employment_informations' => [[
                    'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
                    'position' => 'HR Officer',
                    'designation' => 'Administrative Officer',
                    'employment_type' => 'Full-time',
                    'hire_date' => '2024-01-15',
                ]],
            ],
            [
                'employee_number' => '2026-00002',
                'campus_id' => $antipoloCampus?->campus_id,
                'first_name' => 'Juan',
                'middle_name' => 'D.',
                'last_name' => 'Reyes',
                'suffix' => null,
                'is_hybrid' => false,
                'email' => 'juan.reyes@icct.edu.ph',
                'phone' => '09181234567',
                'department' => 'College of Computer Studies',
                'college' => 'College of Computer Studies',
                'campus' => 'UA',
                'employment_status' => 'inactive',
                'compliance_status' => 'pending',
                'is_active' => false,
                'birth_date' => '1988-11-03',
                'gender' => 'male',
                'civil_status' => 'married',
                'nationality' => 'Filipino',
                'is_confidential' => false,
                'extended_profile' => [],
                'employment_informations' => [[
                    'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
                    'position' => 'Faculty',
                    'designation' => 'Instructor',
                    'employment_type' => 'Full-time',
                    'hire_date' => '2023-06-01',
                ]],
            ],
        ];

        foreach ($employees as $employeeData) {
            $employmentInformations = $employeeData['employment_informations'];
            unset($employeeData['employment_informations']);

            $employee = Employee::query()->updateOrCreate(
                ['employee_number' => $employeeData['employee_number']],
                $employeeData,
            );

            EmployeeEmploymentSync::sync($employee, $employmentInformations);
        }
    }
}
