<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Services\EmployeeEmploymentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeEmploymentHistorySyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_closes_previous_employment_the_day_before_a_new_effectivity_date(): void
    {
        $employee = $this->employee();
        $employment = $this->employment($employee, [
            'position' => 'HR Assistant',
            'date_effective_from' => '2024-10-28',
        ]);

        EmployeeEmploymentSync::sync($employee, [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'HR Officer',
            'date_effective_from' => '2026-09-02',
        ]], false);

        $employment->refresh();

        $this->assertSame('HR Officer', $employment->position);
        $this->assertSame('2026-09-02', $employment->date_effective_from?->toDateString());
        $this->assertNull($employment->date_effective_to);

        $previous = $employment->previousEmployments()->first();

        $this->assertNotNull($previous);
        $this->assertSame('HR Assistant', $previous->position);
        $this->assertSame('2024-10-28', $previous->date_effective_from?->toDateString());
        $this->assertSame('2026-09-01', $previous->date_effective_to?->toDateString());
        $this->assertSame(1, $employee->employmentInformations()->count());
    }

    #[Test]
    public function it_archives_same_day_employment_changes_with_matching_from_and_to(): void
    {
        $employee = $this->employee();
        $employment = $this->employment($employee, [
            'position' => 'HR Assistant',
            'date_effective_from' => '2026-09-02',
        ]);

        EmployeeEmploymentSync::sync($employee, [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'HR Officer',
            'date_effective_from' => '2026-09-02',
        ]], false);

        $previous = $employment->previousEmployments()->first();

        $this->assertSame('2026-09-02', $previous->date_effective_from?->toDateString());
        $this->assertSame('2026-09-02', $previous->date_effective_to?->toDateString());
        $this->assertSame('HR Officer', $employment->fresh()->position);
    }

    #[Test]
    public function it_closes_previous_employment_the_day_before_an_earlier_effectivity_date(): void
    {
        $employee = $this->employee();
        $employment = $this->employment($employee, [
            'position' => 'HR Assistant',
            'date_effective_from' => '2024-10-28',
        ]);

        EmployeeEmploymentSync::sync($employee, [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'HR Assistant',
            'date_effective_from' => '2024-10-01',
        ]], false);

        $previous = $employment->previousEmployments()->first();

        $this->assertSame('2024-10-28', $previous->date_effective_from?->toDateString());
        $this->assertSame('2024-09-30', $previous->date_effective_to?->toDateString());
    }

    #[Test]
    public function it_sets_separation_date_to_the_earlier_of_last_log_and_last_payroll_date(): void
    {
        $this->assertSame(
            '2026-12-19',
            EmployeeEmploymentSync::separationDate('2026-12-21', '2026-12-19'),
        );
        $this->assertSame(
            '2026-12-05',
            EmployeeEmploymentSync::separationDate('2026-12-05', '2026-12-07'),
        );
        $this->assertNull(EmployeeEmploymentSync::separationDate(null, '2026-12-19'));
        $this->assertNull(EmployeeEmploymentSync::separationDate('2026-12-21', null));

        $employee = $this->employee();
        $employment = $this->employment($employee, [
            'date_effective_from' => '2026-01-01',
        ]);
        $this->log($employee, '2026-12-19 17:00:00');

        EmployeeEmploymentSync::sync($employee, [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'date_effective_from' => '2026-01-01',
            'last_payroll_date' => '2026-12-21',
        ]], false);

        $this->assertSame('2026-12-21', $employment->fresh()->last_payroll_date?->toDateString());
        $this->assertSame('2026-12-19', $employment->fresh()->separation_date?->toDateString());

        $this->log($employee, '2026-12-07 08:00:00');

        EmployeeEmploymentSync::sync($employee->fresh(), [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'date_effective_from' => '2026-01-01',
            'last_payroll_date' => '2026-12-05',
        ]], false);

        $this->assertSame('2026-12-05', $employment->fresh()->separation_date?->toDateString());
    }

    #[Test]
    public function it_does_not_archive_when_employment_settings_are_unchanged(): void
    {
        $employee = $this->employee();
        $employment = $this->employment($employee, [
            'position' => 'HR Assistant',
            'employment_type' => 'Fulltime',
            'date_effective_from' => '2024-10-28',
            'hire_date' => '2024-10-28',
        ]);

        EmployeeEmploymentSync::sync($employee, [[
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'HR Assistant',
            'employment_type' => 'Fulltime',
            'date_effective_from' => '2024-10-28',
            'hire_date' => '2024-10-28',
        ]], false);

        $this->assertSame(0, $employment->previousEmployments()->count());
    }

    private function employee(): Employee
    {
        return Employee::query()->create([
            'employee_number' => 'EMP-HIST-'.uniqid(),
            'first_name' => 'Employment',
            'middle_name' => 'History',
            'last_name' => 'Test',
            'email' => uniqid('employment').'@example.com',
            'phone' => '09170000002',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function employment(Employee $employee, array $overrides = []): EmployeeEmploymentInformation
    {
        return EmployeeEmploymentInformation::query()->create(array_merge([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 0,
        ], $overrides));
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
