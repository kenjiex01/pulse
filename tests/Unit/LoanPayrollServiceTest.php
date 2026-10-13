<?php

namespace Tests\Unit;

use App\Services\LoanPayrollService;
use Tests\TestCase;

class LoanPayrollServiceTest extends TestCase
{
    public function test_payment_is_capped_to_remaining_balance(): void
    {
        $service = new LoanPayrollService;

        $this->assertSame(2500.0, $service->paymentForBalance(50000, 0, 2500));
        $this->assertSame(400.0, $service->paymentForBalance(400, 0, 2500));
        $this->assertSame(0.0, $service->paymentForBalance(50000, 50000, 2500));
        $this->assertSame(0.0, $service->paymentForBalance(500.4, 500, 2500));
    }
}
