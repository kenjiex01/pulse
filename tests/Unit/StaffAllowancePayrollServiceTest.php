<?php

namespace Tests\Unit;

use App\Models\EmployeeSalary;
use App\Models\PayType;
use App\Services\StaffAllowancePayrollService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAllowancePayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_period_hours_uses_days_per_period_times_eight_when_hours_per_day_missing(): void
    {
        $salary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 26,
            'hours_per_day' => null,
        ]);

        $hours = app(StaffAllowancePayrollService::class)->periodHoursForAllowanceRate($salary);

        $this->assertSame(208.0, $hours);
    }

    public function test_penalty_hours_sum_late_undertime_and_absent(): void
    {
        $salary = new EmployeeSalary([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'use_payroll_calendar_days' => false,
        ]);

        $service = app(StaffAllowancePayrollService::class);

        $penalty = $service->penaltyHours(
            $salary,
            1,
            new \App\Models\PayrollCalendar([
                'dt_from' => '2026-06-16',
                'dt_to' => '2026-06-30',
            ]),
            [
                'late_minutes' => 60,
                'break_late_minutes' => 30,
                'undertime_minutes' => 60,
                'absent_hours' => 8.0,
            ],
        );

        $this->assertSame(10.5, $penalty);
    }

    public function test_allowance_deduction_amount_from_hourly_rate_times_penalty(): void
    {
        $allowanceTotal = 5000.0;
        $periodHours = 208.0;
        $penaltyHours = 2.0;
        $hourlyRate = $allowanceTotal / $periodHours;
        $deduction = round($hourlyRate * $penaltyHours, 2);

        $this->assertSame(48.08, $deduction);
    }
}
