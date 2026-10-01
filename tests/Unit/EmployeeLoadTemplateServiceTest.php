<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\Campus;
use App\Services\EmployeeLoadTemplateService;
use App\Services\SkolarisApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EmployeeLoadTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_build_rows_uses_daily_loads_when_present(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'E001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'campus_id' => Campus::query()->value('campus_id'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 0,
        ]);

        $skolaris = Mockery::mock(SkolarisApiService::class);
        $skolaris->shouldReceive('dailyLoadsForEmployeeLoadTemplate')
            ->once()
            ->with('2026-09-01', '2026-09-02')
            ->andReturn([
                [
                    'full_name' => 'Jane Doe',
                    'employee_number' => 'E001',
                    'college_name' => 'College of Computing',
                    'loads' => [
                        [
                            'attendance_date' => '2026-09-01',
                            'subject_code' => 'CS101',
                            'section' => 'A',
                            'schedule' => '8:00 AM - 9:30 AM',
                            'time_in' => '08:00:00',
                            'time_out' => '09:30:00',
                            'offering_id' => 42,
                        ],
                    ],
                ],
            ]);
        $skolaris->shouldNotReceive('enrollmentPeriods');
        $skolaris->shouldNotReceive('facultyOverview');

        $service = new EmployeeLoadTemplateService($skolaris);
        $rows = $service->buildRows('2026-09-01', '2026-09-02');

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['row_no']);
        $this->assertSame('Jane Doe', $rows[0]['faculty_name']);
        $this->assertSame('College of Computing', $rows[0]['college']);
        $this->assertSame('CS101', $rows[0]['subject']);
        $this->assertSame('A', $rows[0]['section']);
        $this->assertSame('8:00 AM - 9:30 AM', $rows[0]['class_schedule']);
        $this->assertSame('8:00 AM', $rows[0]['time_in']);
        $this->assertSame('9:30 AM', $rows[0]['time_out']);
        $this->assertSame('E001', $rows[0]['employee_number']);
        $this->assertSame('42', $rows[0]['skolaris_offering_id']);
        $this->assertSame('2026-09-01', $rows[0]['session_date_iso']);
    }

    public function test_build_rows_falls_back_to_faculty_overview_when_daily_loads_empty(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'E002',
            'first_name' => 'Empty',
            'last_name' => 'Loads',
            'email' => 'empty.loads@example.com',
            'campus_id' => Campus::query()->value('campus_id'),
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 0,
        ]);

        $skolaris = Mockery::mock(SkolarisApiService::class);
        $skolaris->shouldReceive('dailyLoadsForEmployeeLoadTemplate')
            ->once()
            ->with('2026-06-01', '2026-06-01')
            ->andReturn([]);

        $skolaris->shouldReceive('enrollmentPeriods')
            ->once()
            ->andReturn([
                [
                    'enrollment_period_id' => 9,
                    'period_name' => 'SY 2025-2026 1st Sem',
                    'classes_start_date' => '2026-06-01',
                    'classes_end_date' => '2026-10-31',
                ],
            ]);

        $skolaris->shouldReceive('facultyOverview')
            ->once()
            ->with(9)
            ->andReturn([]);

        $service = new EmployeeLoadTemplateService($skolaris);
        $rows = $service->buildRows('2026-06-01', '2026-06-01');

        $this->assertSame([], $rows);
    }
}
