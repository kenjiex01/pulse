<?php

namespace Tests\Unit;

use App\Models\GovtTablePhilhealth;
use App\Services\GovernmentDeductionPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhilhealthBracketComputationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        GovtTablePhilhealth::query()->create([
            'salary_from' => 1.00,
            'salary_to' => 10000.00,
            'is_percent' => false,
            'percentage' => 0,
            'employee_share' => 250,
            'employer_share' => 250,
            'is_active' => true,
        ]);

        GovtTablePhilhealth::query()->create([
            'salary_from' => 10000.01,
            'salary_to' => 99999.99,
            'is_percent' => true,
            'percentage' => 5,
            'employee_share' => 0,
            'employer_share' => 0,
            'is_active' => true,
        ]);

        GovtTablePhilhealth::query()->create([
            'salary_from' => 100000.00,
            'salary_to' => 9999999.99,
            'is_percent' => false,
            'percentage' => 0,
            'employee_share' => 2500,
            'employer_share' => 2500,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function floor_bracket_uses_fixed_shares(): void
    {
        $result = app(GovernmentDeductionPayrollService::class)->computePhilhealthBracket(8000);

        $this->assertSame(250.0, $result['employee_amount']);
        $this->assertSame(250.0, $result['employer_amount']);
    }

    #[Test]
    public function percent_bracket_uses_contribution_base_times_percentage(): void
    {
        // Contribution base (Basic − Tardiness) 20,000 × 5% = 1,000 → EE 500 / ER 500
        $result = app(GovernmentDeductionPayrollService::class)->computePhilhealthBracket(20000);

        $this->assertSame(500.0, $result['employee_amount']);
        $this->assertSame(500.0, $result['employer_amount']);
    }

    #[Test]
    public function ceiling_bracket_uses_fixed_shares(): void
    {
        $result = app(GovernmentDeductionPayrollService::class)->computePhilhealthBracket(150000);

        $this->assertSame(2500.0, $result['employee_amount']);
        $this->assertSame(2500.0, $result['employer_amount']);
    }
}
