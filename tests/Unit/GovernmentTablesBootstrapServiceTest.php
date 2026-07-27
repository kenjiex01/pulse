<?php

namespace Tests\Unit;

use App\Models\GovtTableSss;
use App\Services\GovernmentTablesBootstrapService;
use Database\Seeders\GovernmentTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GovernmentTablesBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function enforce_restores_tampered_sss_schedule(): void
    {
        $this->seed(GovernmentTablesSeeder::class);

        $row = GovtTableSss::query()->where('govt_table_sss_id', 1)->firstOrFail();
        $officialEmployeeSss = (float) $row->employee_sss;

        $row->update(['employee_sss' => 999.99]);
        $this->assertSame(999.99, (float) GovtTableSss::query()->find(1)->employee_sss);

        app(GovernmentTablesBootstrapService::class)->enforceOfficialSchedules();

        $this->assertSame($officialEmployeeSss, (float) GovtTableSss::query()->find(1)->employee_sss);
    }
}
