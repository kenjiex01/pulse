<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $designations = [
            'Academic Advisor',
            'Clerk/Assistant',
            'College Instructor/Lecturer',
            'Finance Officer',
            'Guidance Counselor',
            'Human Resources Manager',
            'IT Systems Administrator',
            'Librarian',
            'Marketing Officer',
            'Professor',
            'Property Custodian',
            'Registrar',
            'Research Specialist',
            'SHS Teacher',
            'Staff',
        ];

        foreach ($designations as $designation) {
            Designation::firstOrCreate(
                ['designation_name' => $designation],
                ['is_active' => true]
            );
        }
    }
}
