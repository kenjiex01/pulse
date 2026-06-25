<?php

namespace Database\Seeders;

use App\Models\EmployeeDepartment;
use Illuminate\Database\Seeder;

class EmployeeDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['department_name' => 'SPAMO', 'sort_order' => 1],
            ['department_name' => 'SPD', 'sort_order' => 2],
            ['department_name' => 'Finance', 'sort_order' => 3],
            ['department_name' => 'Purchasing', 'sort_order' => 4],
            ['department_name' => 'Academic Affairs', 'sort_order' => 5],
            ['department_name' => 'Library', 'sort_order' => 6],
            ['department_name' => 'Permits, Licensing & Curriculum Development', 'sort_order' => 7],
            ['department_name' => 'Registrar Office', 'sort_order' => 8],
            ['department_name' => 'Student Affairs', 'sort_order' => 9],
            ['department_name' => 'Accounting', 'sort_order' => 10],
            ['department_name' => 'Asset Management', 'sort_order' => 11],
            ['department_name' => 'Human Resources', 'sort_order' => 12],
            ['department_name' => 'Administration', 'sort_order' => 13],
            ['department_name' => 'MIS', 'sort_order' => 14],
            ['department_name' => 'Executive Office', 'sort_order' => 15],
        ];

        foreach ($departments as $department) {
            EmployeeDepartment::query()->updateOrCreate(
                ['department_name' => $department['department_name']],
                [
                    'department_code' => null,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $department['sort_order'],
                ],
            );
        }
    }
}
