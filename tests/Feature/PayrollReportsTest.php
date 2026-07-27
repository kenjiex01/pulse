<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_reports_index_loads_for_admin(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.index'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Payroll Register')
            ->assertSee('SSS Monthly Contribution');
    }

    public function test_report_options_partial_loads_for_payroll_register(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payroll Register')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('Payroll Register Options')
            ->assertSee('payroll_batch_ids');
    }

    public function test_report_options_partial_loads_for_sss_contribution(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'SSS Monthly Contribution')->firstOrFail();

        $this->actingAs($user)
            ->get(route('payroll.reports.options', ['report' => $report->report_id, 'classification' => 'payroll']))
            ->assertOk()
            ->assertSee('SSS Monthly Contribution Options')
            ->assertSee('same pay month and pay year');
    }

    public function test_generate_requires_processed_batch_selection(): void
    {
        $user = User::query()->firstOrFail();
        $report = Report::query()->where('title', 'Payroll Register')->firstOrFail();

        $this->actingAs($user)
            ->post(route('payroll.reports.generate'), [
                'classification' => 'payroll',
                'report_id' => $report->report_id,
                'output_format' => 'html',
            ])
            ->assertSessionHasErrors('payroll_batch_ids');
    }
}
