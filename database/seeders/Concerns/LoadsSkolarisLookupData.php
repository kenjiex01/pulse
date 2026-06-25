<?php

namespace Database\Seeders\Concerns;

trait LoadsSkolarisLookupData
{
    /**
     * Campuses and programs exported from Skolaris production
     * (https://api-skolaris.icct.edu.ph/api/v1 — same source as /pulse/employees).
     */
    protected function skolarisLookupData(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $path = database_path('seeders/data/skolaris_campuses_programs.json');

        if (! is_file($path)) {
            throw new \RuntimeException("Missing Skolaris lookup data at {$path}.");
        }

        $cache = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $cache;
    }
}
