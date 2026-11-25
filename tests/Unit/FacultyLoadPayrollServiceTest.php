<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\RawEmployeeLoadEntry;
use App\Services\EmployeeLoadPayrollService;
use App\Services\FacultyLoadPayrollService;
use App\Support\SkolarisCheckerLoadStatus;
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
    public function faculty_late_without_policy_uses_teaching_load_clock_gap(): void
    {
        $service = app(EmployeeLoadPayrollService::class);

        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => 'WED 06:00 - 07:30',
            'session_date' => CarbonImmutable::parse('2026-11-04'),
            'time_in' => '06:43:00',
            'time_out' => null,
            'late_waived' => false,
        ]);

        $resolved = $service->resolvedLateForEntry($entry, null);
        $metrics = $service->attendanceMetricsForEntry($entry, null);

        $this->assertSame(43, $resolved['raw_minutes']);
        $this->assertSame(43, $resolved['billable_minutes']);
        $this->assertFalse($resolved['is_absent']);
        $this->assertSame('43 min', $metrics['late_display']);
    }

    #[Test]
    public function faculty_overtime_is_not_inferred_from_time_out_past_class_end(): void
    {
        $service = app(EmployeeLoadPayrollService::class);

        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => '15:00 - 16:30',
            'session_date' => CarbonImmutable::parse('2026-11-05'),
            'time_in' => null,
            'time_out' => '18:58:00',
            'late_waived' => false,
        ]);

        $this->assertSame(0, $service->overtimeMinutesForEntry($entry));
        $this->assertSame(0, $service->attendanceMetricsForEntry($entry, null)['overtime_minutes']);
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
    public function present_mark_without_punches_still_counts_as_present(): void
    {
        $this->assertTrue(SkolarisCheckerLoadStatus::countsAsPresent('P'));
        $this->assertFalse(SkolarisCheckerLoadStatus::isExplicitlyAbsent('P'));

        $service = app(EmployeeLoadPayrollService::class);
        $entry = new RawEmployeeLoadEntry([
            'class_schedule' => 'MON 16:30 - 18:00',
            'total_hours' => 1.50,
            'session_date' => CarbonImmutable::parse('2026-09-21'),
            'time_in' => null,
            'time_out' => null,
            'remarks' => 'P',
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
