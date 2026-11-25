<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\RawEmployeeLoadEntry;
use App\Models\RawEmployeeLoadTransaction;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
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

    public function test_faculty_teaching_load_with_default_present_status_is_not_absent(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-003',
            'first_name' => 'Checker',
            'last_name' => 'Present',
            'email' => 'checker.present.'.uniqid().'@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Instructor',
        ]);

        \App\Models\TeachingLoadSession::query()->create([
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-09-17',
            'date_from' => '2026-09-17',
            'date_to' => '2026-09-17',
            'class_schedule' => '08:00 AM - 10:00 AM',
            'time_in' => '08:00:00',
            'time_out' => '10:00:00',
            'status_code' => 'P',
            'subject_code' => 'MATH101',
            'section' => 'A',
        ]);

        $days = app(TimekeepingMemoAttendanceService::class)->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-09-17',
            '2026-09-17',
            TimekeepingMemoSetup::TYPE_ABSENT,
        );

        $this->assertSame([], $days);
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

    public function test_faculty_late_still_counts_when_another_class_that_day_has_no_time_in(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-004',
            'first_name' => 'Mae',
            'last_name' => 'Bautista',
            'email' => 'mae.bautista.'.uniqid().'@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Faculty',
        ]);

        $transactionId = $this->createLoadTransaction()->employee_load_transaction_id;

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-11-04',
            'class_schedule' => 'WED 06:00 - 07:30',
            'time_in' => '06:43:00',
            'time_out' => null,
            'subject' => 'OLMATH01',
            'section' => 'A',
        ]);

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-11-04',
            'class_schedule' => '15:00 - 16:30',
            'time_in' => null,
            'time_out' => '18:01:00',
            'subject' => 'OLMATH01',
            'section' => 'B',
        ]);

        $days = app(TimekeepingMemoAttendanceService::class)->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-11-04',
            '2026-11-04',
            TimekeepingMemoSetup::TYPE_LATE,
        );

        $this->assertCount(1, $days);
        $this->assertSame('2026-11-04', $days[0]['work_date']);
        $this->assertGreaterThan(0, $days[0]['minutes']);

        $absentDays = app(TimekeepingMemoAttendanceService::class)->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-11-04',
            '2026-11-04',
            TimekeepingMemoSetup::TYPE_ABSENT,
        );

        $this->assertSame([], $absentDays);
    }

    public function test_faculty_day_with_only_time_in_is_absent(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-005',
            'first_name' => 'In',
            'last_name' => 'Only',
            'email' => 'in.only.'.uniqid().'@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Faculty',
        ]);

        $transactionId = $this->createLoadTransaction()->employee_load_transaction_id;

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-11-03',
            'class_schedule' => '06:00 - 07:30',
            'time_in' => '06:40:00',
            'time_out' => null,
        ]);

        $days = app(TimekeepingMemoAttendanceService::class)->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-11-03',
            '2026-11-03',
            TimekeepingMemoSetup::TYPE_ABSENT,
        );

        $this->assertCount(1, $days);
        $this->assertSame('06:40', $days[0]['time_in']);
        $this->assertNull($days[0]['time_out']);
    }

    public function test_faculty_day_uses_raw_logs_when_class_rows_split_in_and_out(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-MEMO-006',
            'first_name' => 'Mae',
            'last_name' => 'Logs',
            'email' => 'mae.logs.'.uniqid().'@example.com',
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Faculty',
        ]);

        $transactionId = $this->createLoadTransaction()->employee_load_transaction_id;

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-11-04',
            'class_schedule' => 'WED 06:00 - 07:30',
            'time_in' => '06:43:00',
            'time_out' => null,
        ]);

        RawEmployeeLoadEntry::query()->create([
            'employee_load_transaction_id' => $transactionId,
            'employee_id' => $employee->employee_id,
            'employee_number' => $employee->employee_number,
            'session_date' => '2026-11-04',
            'class_schedule' => '16:30 - 18:00',
            'time_in' => null,
            'time_out' => null,
        ]);

        $punchBatch = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => 1,
            'dt_from' => '2026-11-04',
            'dt_to' => '2026-11-04',
            'uploaded_by_id' => 1,
            'batch_no' => 41,
        ]);

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $punchBatch->timekeeping_transaction_id,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => '2026-11-04 06:43:00',
            'is_in' => true,
            'timekeeping_trantype' => 1,
        ]);
        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $punchBatch->timekeeping_transaction_id,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => '2026-11-04 18:01:00',
            'is_in' => false,
            'timekeeping_trantype' => 1,
        ]);

        $days = app(TimekeepingMemoAttendanceService::class)->violationDaysForEmployee(
            $employee->fresh(['employmentInformations']),
            '2026-11-04',
            '2026-11-04',
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
