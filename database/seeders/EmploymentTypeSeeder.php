<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use Illuminate\Database\Seeder;

class EmploymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $employmentTypes = [
            ['type_code' => 'FT', 'type_name' => 'Full-Time', 'description' => 'Full-time employment with regular hours', 'sort_order' => 1],
            ['type_code' => 'PT', 'type_name' => 'Part-Time', 'description' => 'Part-time employment with reduced hours', 'sort_order' => 2],
            ['type_code' => 'CONTRACT', 'type_name' => 'Contract', 'description' => 'Contract-based employment for a specific period', 'sort_order' => 3],
            ['type_code' => 'PROBATION', 'type_name' => 'Probationary', 'description' => 'Probationary employment period', 'sort_order' => 4],
            ['type_code' => 'TEMP', 'type_name' => 'Temporary', 'description' => 'Temporary employment assignment', 'sort_order' => 5],
            ['type_code' => 'CASUAL', 'type_name' => 'Casual', 'description' => 'Casual employment on an as-needed basis', 'sort_order' => 6],
        ];

        foreach ($employmentTypes as $employmentType) {
            // type_code is the stable key — type_name may have been renamed by the client (e.g. "Contractual").
            $existing = EmploymentType::withTrashed()
                ->where('type_code', $employmentType['type_code'])
                ->orWhere('type_name', $employmentType['type_name'])
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'type_code' => $existing->type_code ?: $employmentType['type_code'],
                    'description' => $existing->description ?: $employmentType['description'],
                ])->save();

                continue;
            }

            EmploymentType::query()->create(array_merge($employmentType, ['is_active' => true]));
        }
    }
}
