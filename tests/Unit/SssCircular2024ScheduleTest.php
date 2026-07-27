<?php

namespace Tests\Unit;

use App\Models\GovtTableSss;
use App\Services\GovernmentDeductionPayrollService;
use Database\Seeders\GovernmentTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SssCircular2024ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernmentTablesSeeder::class);
    }

    #[Test]
    public function schedule_matches_circular_2024_006_sample_rows(): void
    {
        $samples = [
            // from, to, regular, mpf, erSs, erMpf, ec, eeSs, eeMpf
            [1.00, 5249.99, 5000, 0, 500, 0, 10, 250, 0],
            [5250.00, 5749.99, 5500, 0, 550, 0, 10, 275, 0],
            [14250.00, 14749.99, 14500, 0, 1450, 0, 10, 725, 0],
            [14750.00, 15249.99, 15000, 0, 1500, 0, 30, 750, 0],
            [19750.00, 20249.99, 20000, 0, 2000, 0, 30, 1000, 0],
            [20250.00, 20749.99, 20000, 500, 2000, 50, 30, 1000, 25],
            [24750.00, 25249.99, 20000, 5000, 2000, 500, 30, 1000, 250],
            [29750.00, 30249.99, 20000, 10000, 2000, 1000, 30, 1000, 500],
            [34750.00, 99999999.99, 20000, 15000, 2000, 1500, 30, 1000, 750],
        ];

        foreach ($samples as [$from, $to, $regular, $mpf, $erSs, $erMpf, $ec, $eeSs, $eeMpf]) {
            $row = GovtTableSss::query()->where('compensation_from', $from)->first();

            $this->assertNotNull($row, "Missing SSS row for compensation_from={$from}");
            $this->assertEqualsWithDelta($to, (float) $row->compensation_to, 0.001);
            $this->assertEqualsWithDelta($regular, (float) $row->salary_credit, 0.001);
            $this->assertEqualsWithDelta($mpf, (float) $row->mpf_salary_credit, 0.001);
            $this->assertEqualsWithDelta($erSs, (float) $row->employer_sss, 0.001);
            $this->assertEqualsWithDelta($erMpf, (float) $row->employer_mpf_share, 0.001);
            $this->assertEqualsWithDelta($ec, (float) $row->employer_ec, 0.001);
            $this->assertEqualsWithDelta($eeSs, (float) $row->employee_sss, 0.001);
            $this->assertEqualsWithDelta($eeMpf, (float) $row->employee_mpf_share, 0.001);
        }

        $this->assertSame(61, GovtTableSss::query()->count());
    }

    #[Test]
    public function payroll_sss_amounts_include_mpf_and_employer_ec(): void
    {
        $service = app(GovernmentDeductionPayrollService::class);

        // MSC 35,000 → EE 1,000+750=1,750; ER 2,000+1,500+30=3,530
        $high = $service->computeSss(35000);
        $this->assertSame(1750.0, $high['employee_amount']);
        $this->assertSame(3530.0, $high['employer_amount']);

        // MSC 5,000 → EE 250; ER 500+10=510
        $low = $service->computeSss(4000);
        $this->assertSame(250.0, $low['employee_amount']);
        $this->assertSame(510.0, $low['employer_amount']);
    }

    #[Test]
    public function regular_and_mpf_are_split_for_payroll_lines(): void
    {
        $service = app(GovernmentDeductionPayrollService::class);

        $regular = $service->computeSssRegular(35000);
        $mpf = $service->computeSssMpf(35000);

        $this->assertSame(1000.0, $regular['employee_amount']);
        $this->assertSame(2030.0, $regular['employer_amount']); // 2000 + EC 30
        $this->assertSame(750.0, $mpf['employee_amount']);
        $this->assertSame(1500.0, $mpf['employer_amount']);

        $lowMpf = $service->computeSssMpf(4000);
        $this->assertSame(0.0, $lowMpf['employee_amount']);
        $this->assertSame(0.0, $lowMpf['employer_amount']);
    }
}