<?php

namespace Tests\Unit;

use App\Models\IncomeType;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Support\PayrollPeriodComputation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodComputationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_regular_only_period_computes_basic_not_allowance(): void
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 1,
            'dt_from' => '2026-01-01',
            'dt_to' => '2026-01-15',
            'calendar_month' => 1,
            'is_regular_period' => true,
            'is_allowance_period' => null,
        ]);

        $this->assertTrue($calendar->computesBasic());
        $this->assertFalse($calendar->computesAllowance());
    }

    public function test_allowance_only_period_computes_allowance_not_basic(): void
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 2,
            'dt_from' => '2026-01-16',
            'dt_to' => '2026-01-31',
            'calendar_month' => 1,
            'is_regular_period' => null,
            'is_allowance_period' => true,
        ]);

        $this->assertFalse($calendar->computesBasic());
        $this->assertTrue($calendar->computesAllowance());
    }

    public function test_both_flags_compute_basic_and_allowance_incomes(): void
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 3,
            'dt_from' => '2026-02-01',
            'dt_to' => '2026-02-15',
            'calendar_month' => 2,
            'is_regular_period' => true,
            'is_allowance_period' => true,
        ]);

        $basic = IncomeType::query()->where('income_type_code', 'BASC')->firstOrFail();
        $allowance = IncomeType::query()->where('income_type_code', 'DEMN')->firstOrFail();

        $this->assertTrue(PayrollPeriodComputation::includesIncomeType($basic, $calendar));
        $this->assertTrue(PayrollPeriodComputation::includesIncomeType($allowance, $calendar));
    }
}
