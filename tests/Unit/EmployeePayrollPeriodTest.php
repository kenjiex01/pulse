<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Services\TimeLogsPayrollService;
use App\Support\EmployeePayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeePayrollPeriodTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stops_the_pay_period_on_the_employee_last_payroll_date(): void
    {
        $employee = $this->employee('2026-06-23');
        $from = CarbonImmutable::parse('2026-06-16');
        $to = CarbonImmutable::parse('2026-06-30');

        $end = EmployeePayrollPeriod::effectiveEnd($employee, $from, $to);

        $this->assertSame('2026-06-23', $end?->toDateString());
    }

    #[Test]
    public function it_keeps_the_full_period_when_last_payroll_date_is_blank_or_later(): void
    {
        $from = CarbonImmutable::parse('2026-06-16');
        $to = CarbonImmutable::parse('2026-06-30');

        $this->assertSame(
            '2026-06-30',
            EmployeePayrollPeriod::effectiveEnd($this->employee(null), $from, $to)?->toDateString(),
        );
        $this->assertSame(
            '2026-06-30',
            EmployeePayrollPeriod::effectiveEnd($this->employee('2026-07-15'), $from, $to)?->toDateString(),
        );
    }

    #[Test]
    public function it_excludes_the_period_when_last_payroll_date_is_before_it_starts(): void
    {
        $employee = $this->employee('2026-06-10');

        $this->assertNull(EmployeePayrollPeriod::effectiveEnd(
            $employee,
            CarbonImmutable::parse('2026-06-16'),
            CarbonImmutable::parse('2026-06-30'),
        ));
    }

    #[Test]
    public function it_ignores_time_logs_after_the_last_payroll_date(): void
    {
        $employee = $this->employee('2026-06-23');
        $this->log($employee, '2026-06-23 08:00:00');
        $this->log($employee, '2026-06-24 08:00:00');

        $from = CarbonImmutable::parse('2026-06-16');
        $to = EmployeePayrollPeriod::effectiveEnd(
            $employee,
            $from,
            CarbonImmutable::parse('2026-06-30'),
        );

        $dates = app(TimeLogsPayrollService::class)
            ->daySessionsForPeriod($employee->employee_id, $from, $to)
            ->map(fn (array $session) => $session['date']->toDateString())
            ->all();

        $this->assertSame(['2026-06-23'], $dates);
    }

    private function employee(?string $lastPayrollDate): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'PAY-CUT-'.uniqid(),
            'first_name' => 'Payroll',
            'middle_name' => 'Cutoff',
            'last_name' => 'Test',
            'email' => uniqid('cutoff').'@example.com',
            'phone' => '09170000003',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'date_effective_from' => '2026-01-01',
            'last_payroll_date' => $lastPayrollDate,
            'sort_order' => 0,
        ]);

        return $employee->fresh('employmentInformations');
    }

    private function log(Employee $employee, string $dateTime): void
    {
        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => $dateTime,
            'dt_to' => $dateTime,
            'uploaded_by_id' => 0,
            'batch_no' => 1,
        ]);

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => $dateTime,
            'is_in' => true,
        ]);
    }
}
