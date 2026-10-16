<?php

namespace Tests\Unit;

use App\Models\EmployeeLoan;
use App\Models\PayrollCalendar;
use App\Services\LoanPayrollService;
use Tests\TestCase;

class LoanPayrollServiceTest extends TestCase
{
    public function test_principal_is_amortization_times_number_of_payments(): void
    {
        $principal = EmployeeLoan::computePrincipalAmount(12000, 99999, 553.74, 24);
        $this->assertSame('13289.76', number_format($principal, 2, '.', ''));
        $this->assertSame('1289.76', number_format(EmployeeLoan::computeLoanInterest($principal, 12000), 2, '.', ''));
        $this->assertSame(
            '6644.88',
            number_format(EmployeeLoan::computePrincipalAmount(12000, null, 553.74, 12), 2, '.', ''),
        );
        $this->assertSame(
            '13289.76',
            number_format(EmployeeLoan::computePrincipalAmount(12000, 1289.76, null, null), 2, '.', ''),
        );
    }

    public function test_payment_is_capped_to_remaining_balance(): void
    {
        $service = new LoanPayrollService;

        $this->assertSame(2500.0, $service->paymentForBalance(50000, 0, 2500));
        $this->assertSame(400.0, $service->paymentForBalance(400, 0, 2500));
        $this->assertSame(0.0, $service->paymentForBalance(50000, 50000, 2500));
        $this->assertSame(0.0, $service->paymentForBalance(500.4, 500, 2500));
    }

    public function test_half_per_cutoff_uses_half_the_amortization(): void
    {
        $service = new LoanPayrollService;
        $loan = new EmployeeLoan([
            'amortization_amount' => 5000,
            'deduct_half_per_cutoff' => true,
        ]);

        $this->assertSame(2500.0, $service->scheduledAmortization($loan));
    }

    public function test_cutoff_radio_matches_only_that_half_of_the_month(): void
    {
        $service = new LoanPayrollService;
        $first = new PayrollCalendar(['dt_to' => '2026-10-10']);
        $second = new PayrollCalendar(['dt_to' => '2026-10-25']);
        $loan = new EmployeeLoan(['deduction_cutoff' => EmployeeLoan::CUTOFF_FIRST]);

        $this->assertSame(EmployeeLoan::CUTOFF_FIRST, $first->cutoffNumber());
        $this->assertSame(EmployeeLoan::CUTOFF_SECOND, $second->cutoffNumber());
        $this->assertTrue($service->appliesToCalendar($loan, $first));
        $this->assertFalse($service->appliesToCalendar($loan, $second));
    }
}
