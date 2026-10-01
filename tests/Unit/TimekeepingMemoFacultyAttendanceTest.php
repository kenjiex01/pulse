<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\TimekeepingMemoSetup;
use App\Services\TimekeepingMemoAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimekeepingMemoFacultyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_absent_memo_uses_scheduled_load_without_time_in(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-001',
            'first_name' => 'Gina',
            'last_name' => 'Faculty',
            'email' => 'gina.faculty@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Instructor',
        ]);

        $transactionId = $this->createLoadTransaction()->employee_load_transaction_id;

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-09-16',
            'class_schedule' => '08:00 AM - 10:00 AM',
            'time_in' => null,
            'time_out' => null,
            'subject' => 'MATH101',
            'section' => 'A',
        ]);

        /** @var TimekeepingMemoAttendanceService $service */
        $service = app(TimekeepingMemoAttendanceService::class);

        $days = $service->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-09-16',
            '2026-09-16',
            TimekeepingMemoSetup::TYPE_ABSENT,
        );

        $this->assertCount(1, $days);
        $this->assertSame('2026-09-16', $days[0]['work_date']);
        $this->assertSame(0, $days[0]['minutes']);
    }

    public function test_faculty_with_time_in_is_not_absent_for_that_day(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-002',
            'first_name' => 'Present',
            'last_name' => 'Faculty',
            'email' => 'present.faculty@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Instructor',
        ]);

        $transactionId = $this->createLoadTransaction()->employee_load_transaction_id;

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-09-16',
            'class_schedule' => '08:00 AM - 10:00 AM',
            'time_in' => '08:05:00',
            'time_out' => '10:00:00',
            'subject' => 'MATH101',
            'section' => 'A',
        ]);

        /** @var TimekeepingMemoAttendanceService $service */
        $service = app(TimekeepingMemoAttendanceService::class);

        $days = $service->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-09-16',
            '2026-09-16',
            TimekeepingMemoSetup::TYPE_ABSENT,
        );

        $this->assertSame([], $days);
    }

    private function createLoadTransaction(): RawEmployeeLoadTransaction
    {
        return RawEmployeeLoadTransaction::query()->create([
            'batch_no' => 1,
            'filename' => 'memo-test.csv',
            'dt_from' => '2026-09-16',
            'dt_to' => '2026-09-16',
            'dt_uploaded' => now(),
        ]);
    }
}
