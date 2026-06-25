<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            'Academic Coordinator - Angono Campus',
            'Academic Coordinator - Antipolo Campus',
            'Academic Coordinator - Binangonan Campus',
            'Academic Coordinator - BlockChain',
            'Academic Coordinator - CAS',
            'Academic Coordinator - CBA',
            'Academic Coordinator - CCS',
            'Academic Coordinator - CHS',
            'Academic Coordinator - CISCO',
            'Academic Coordinator - CoCA 1st Yr.',
            'Academic Coordinator - CoCA 2ndt Yr.',
            'Academic Coordinator - CoCA 3rd Yr.',
            'Academic Coordinator - CoCA 4th Yr.',
            'Academic Coordinator - CoEDT',
            'Academic Coordinator - Cogeo Campus',
            'Academic Coordinator - CoTEd',
            'Academic Coordinator - Huawei',
            'Academic Coordinator - ISHTM',
            'Academic Coordinator - NSTP',
            'Academic Coordinator - P.E.',
            'Academic Coordinator - San Mateo Campus',
            'Academic Coordinator - SAP',
            'Academic Coordinator - Sumulong Campus',
            'Academic Coordinator - Taytay Campus',
            'AVP for Curriculum & Instruction',
            'AVP for Student Affairs & Services',
            'Campus Academic Dean',
            'Campus Coordinator',
            'Campus Student Affairs Coordinator',
            'College Dean - CHS',
            'College Dean - CoCA',
            'College Dean - ISHTM',
            'Department Chair/Head',
            'Director of Admissions',
            'Faculty Member',
            'OIC (Officer-in-Charge)',
            'OIC CCS',
            'OIC TM',
            'Prefect of Discipline CoCA',
            'Registrar',
            'Senior High School Principal',
            'VP for Academic Affairs & Research',
            'VP for Accounting',
            'VP for Administration',
            'VP for Finance',
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate(
                ['position_name' => $position],
                ['is_active' => true]
            );
        }
    }
}
