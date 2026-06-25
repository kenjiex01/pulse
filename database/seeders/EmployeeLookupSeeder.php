<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** @deprecated Use ReferenceDataSeeder instead. */
class EmployeeLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
