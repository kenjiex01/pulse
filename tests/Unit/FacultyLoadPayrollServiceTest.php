<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\RawEmployeeLoadEntry;
use App\Services\EmployeeLoadPayrollService;
use App\Services\FacultyLoadPayrollService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacultyLoadPayrollServiceTest extends TestCase
{
    #[Test]
    public function hours_for_entry_uses_time_in_out_duration(): void
    {
        $service = app(EmployeeLoadPayrollService::class);

        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => '1:00 PM - 2:00 PM',
            'total_hours' => 1.50,
            'session_date' => CarbonImmutable::parse('2026-06-16'),
            'time_in' => '13:15:00',
            'time_out' => '14:00:00',
            'late_waived' => false,
        ]);

        $this->assertSame(0.75, $service->hoursForEntry($entry));
    }

    #[Test]
    public function hours_for_entry_credits_late_gap_when_waived(): void
    {
        $service = app(EmployeeLoadPayrollService::class);

        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => '1:00 PM - 2:00 PM',
            'session_date' => CarbonImmutable::parse('2026-06-16'),
            'time_in' => '13:15:00',
            'time_out' => '14:00:00',
            'late_waived' => true,
        ]);

        $this->assertSame(1.0, $service->hoursForEntry($entry));
        $this->assertSame(0, $service->lateRawMinutesForEntry($entry));
        $this->assertSame(15, $service->clockLateMinutesForEntry($entry));
    }

    #[Test]
    public function hours_for_entry_falls_back_to_class_schedule_duration(): void
    {
        $service = app(EmployeeLoadPayrollService::class);

        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => 'TUESDAY 06:00 - 07:30',
            'total_hours' => null,
            'session_date' => CarbonImmutable::parse('2026-06-16'),
            'time_in' => null,
            'time_out' => null,
        ]);

        $this->assertSame(1.5, $service->hoursForEntry($entry));
    }

    #[Test]
    public function faculty_path_is_used_regardless_of_leaves_basic_computation(): void
    {
        $employee = new class extends Employee
        {
            public function isFaculty(): bool
            {
                return true;
            }
        };
        $salary = new EmployeeSalary(['basic_computation_id' => 2]); // Leaves

        $service = app(FacultyLoadPayrollService::class);

        $this->assertTrue($service->shouldUseFacultyLoadPath(
            $employee,
            $salary,
            CarbonImmutable::parse('2026-06-15'),
            CarbonImmutable::parse('2026-06-30'),
        ));
    }

    #[Test]
    public function non_faculty_does_not_use_faculty_path(): void
    {
        $employee = new class extends Employee
        {
            public function isFaculty(): bool
            {
                return false;
            }
        };
        $salary = new EmployeeSalary(['basic_computation_id' => 1]);

        $service = app(FacultyLoadPayrollService::class);

        $this->assertFalse($service->shouldUseFacultyLoadPath(
            $employee,
            $salary,
            CarbonImmutable::parse('2026-06-15'),
            CarbonImmutable::parse('2026-06-30'),
        ));
    }
}
