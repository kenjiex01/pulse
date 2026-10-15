<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ICCT company document / memo templates only — safe to run on build and dev refresh.
 */
class CompanyDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanyDocumentHrLetterTemplatesSeeder::class,
        ]);
    }
}
