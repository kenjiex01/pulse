<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ranks = [
            'Assistant Professor I',
            'Assistant Professor II',
            'Assistant Professor III',
            'Assistant Vice President (AVP)',
            'Associate Professor I - Junior',
            'Associate Professor II - Junior',
            'Associate Professor III - Junior',
            'Associate Vice President (AVP)',
            'Associate/Assistant Director',
            'Campus Director',
            'Clerk/Assistant',
            'Coordinator',
            'Dean',
            'Department Chair',
            'Director',
            'Executive Vice President (EVP)',
            'Instructor I',
            'Instructor II',
            'Instructor III',
            'Manager/Supervisor',
            'Officer/Specialist',
            'President',
            'Principal',
            'Provost',
            'Senior Specialist/Officer',
            'Staff',
            'Teacher Assistant I',
            'Teacher Assistant II',
            'Teacher Assistant III',
            'Vice President (VP)',
        ];

        foreach ($ranks as $rank) {
            Rank::firstOrCreate(
                ['rank_name' => $rank],
                ['is_active' => true]
            );
        }
    }
}
