<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** @deprecated Use {@see CompanyDocumentHrLetterTemplatesSeeder} for memo templates. Offense lookups remain in IcctOffenseSeeder. */
class CompanyDocumentIcctOffensesSeeder extends Seeder
{
    public function run(): void
    {
        // Legacy ICCT designer templates replaced by CompanyDocumentHrLetterTemplatesSeeder.
    }

    public function syncOffenseBlockDropdowns(): void
    {
        // No-op: offense block forms removed from Company Documents templates.
    }
}
