<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\College;
use Illuminate\Database\Seeder;

class CollegeSeeder extends Seeder
{
    /**
     * ICCT colleges aligned with Skolaris / icct.edu.ph bachelor-degree programs.
     */
    private function collegeDefinitions(): array
    {
        return [
            [
                'college_code' => 'CCS',
                'college_name' => 'College of Computer Studies',
                'description' => 'Computer Science, Information Technology, and Information Systems programs.',
            ],
            [
                'college_code' => 'CHS',
                'college_name' => 'College of Health Sciences',
                'description' => 'Nursing, Medical Technology, and allied health programs.',
            ],
            [
                'college_code' => 'ISHTM',
                'college_name' => 'International School of Hospitality & Tourism Management',
                'description' => 'Hotel and tourism management programs.',
            ],
            [
                'college_code' => 'CoTEd',
                'college_name' => 'College of Teacher Education',
                'description' => 'Early childhood, elementary, and secondary education programs.',
            ],
            [
                'college_code' => 'CoEDT',
                'college_name' => 'College of Engineering & Digital Technology',
                'description' => 'Engineering and digital technology programs.',
            ],
            [
                'college_code' => 'CAS',
                'college_name' => 'College of Arts & Sciences',
                'description' => 'Communication, journalism, and liberal arts programs.',
            ],
            [
                'college_code' => 'CBA',
                'college_name' => 'College of Business & Accountancy',
                'description' => 'Accountancy, business administration, and related programs.',
            ],
            [
                'college_code' => 'CCA',
                'college_name' => 'College of Criminology & Administration',
                'description' => 'Criminology and public administration programs.',
            ],
        ];
    }

    public function run(): void
    {
        $campuses = Campus::query()->get();

        if ($campuses->isEmpty()) {
            $this->command?->error('No campuses found. Please run CampusSeeder first.');

            return;
        }

        $this->removeLegacyCampusSuffixedColleges($campuses);

        $created = 0;

        foreach ($campuses as $campus) {
            foreach ($this->collegeDefinitions() as $definition) {
                College::query()->updateOrCreate(
                    [
                        'campus_id' => $campus->campus_id,
                        'college_code' => $definition['college_code'],
                    ],
                    [
                        'college_name' => $definition['college_name'],
                        'description' => $definition['description'],
                        'is_active' => true,
                    ],
                );

                $created++;
            }
        }

        $this->command?->info('ICCT colleges seeded successfully.');
        $this->command?->info('Created/updated: '.$created.' college records.');
        $this->command?->info('Colleges per campus: '.count($this->collegeDefinitions()));
    }

    private function removeLegacyCampusSuffixedColleges($campuses): void
    {
        $campusCodes = $campuses->pluck('campus_code')->filter()->all();

        College::query()
            ->where(function ($query) use ($campusCodes) {
                foreach ($campusCodes as $campusCode) {
                    $query->orWhere('college_code', 'like', '%-'.$campusCode);
                }
            })
            ->delete();
    }
}
