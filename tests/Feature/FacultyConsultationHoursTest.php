<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\SysLog;
use App\Models\TeachingLoadSession;
use App\Models\TimekeepingEmployeeConsultationHour;
use App\Models\User;
use App\Services\FacultyConsultationConflictService;
use App\Services\SkolarisApiService;
use App\Support\TimekeepingEmployeeProfile;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacultyConsultationHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();
        $this->mockSkolarisLoads([]);
    }

    #[Test]
    public function saving_warns_when_hours_conflict_with_skolaris_teaching_load(): void
    {
        $faculty = $this->employee('EMP-CONSULT-LOAD', EmployeeEmploymentInformation::TYPE_FACULTY);
        $monday = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        $this->mockSkolarisLoads([[
            'employee_number' => 'EMP-CONSULT-LOAD',
            'loads' => [[
                'attendance_date' => $monday,
                'schedule_day' => 'Monday',
                'subject_code' => 'OLHUM001',
                'section' => 'A1',
                'schedule' => '07:30 - 09:30',
                'scheduled_time_in' => '07:30:00',
                'scheduled_time_out' => '09:30:00',
            ]],
        ]]);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'consultation_hours' => [
                    'a' => ['day_id' => 2, 'time_start' => '09:00', 'time_end' => '10:00'],
                    'b' => ['day_id' => 2, 'time_start' => '14:00', 'time_end' => '15:00'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('consultation_check', [
                'employee_id' => $faculty->employee_id,
                'source' => FacultyConsultationConflictService::SOURCE_SKOLARIS,
                'conflicts' => ['Monday 9:00 AM–10:00 AM conflicts with OLHUM001 A1 (7:30 AM–9:30 AM).'],
            ]);

        $this->assertSame(2, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $faculty->employee_id)->count());

        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), [
                'search' => 'EMP-CONSULT-LOAD',
                'setup_employee' => $faculty->employee_id,
                'view_tab' => 'consultation',
            ]))
            ->assertOk()
            ->assertSee('data-consultation-conflicts', false)
            ->assertSee('conflicts with OLHUM001 A1');
    }

    #[Test]
    public function conflict_check_uses_last_pulled_load_when_skolaris_is_unreachable(): void
    {
        $faculty = $this->employee('EMP-CONSULT-STORED', EmployeeEmploymentInformation::TYPE_FACULTY);

        $skolaris = Mockery::mock(SkolarisApiService::class);
        $skolaris->shouldReceive('resolveSkolarisEmployeeRecord')->andReturn(null);
        $skolaris->shouldReceive('dailyLoads')->andThrow(new \RuntimeException('Connection refused'));
        $this->app->instance(SkolarisApiService::class, $skolaris);

        TeachingLoadSession::query()->create([
            'employee_id' => $faculty->employee_id,
            'session_date' => '2026-10-14',
            'employee_number' => 'EMP-CONSULT-STORED',
            'subject_code' => 'OLCPCRIM8',
            'schedule_day' => 'Wednesday',
            'class_schedule' => '11:30 - 13:00',
            'time_in' => '11:30:00',
            'time_out' => '13:00:00',
            'date_from' => '2026-10-11',
            'date_to' => '2026-10-26',
        ]);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'consultation_hours' => [
                    'a' => ['day_id' => 4, 'time_start' => '11:00', 'time_end' => '12:00'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('consultation_check', [
                'employee_id' => $faculty->employee_id,
                'source' => FacultyConsultationConflictService::SOURCE_STORED,
                'conflicts' => ['Wednesday 11:00 AM–12:00 PM conflicts with OLCPCRIM8 (11:30 AM–1:00 PM).'],
            ]);
    }

    #[Test]
    public function conflict_check_uses_latest_week_from_skolaris_employee_attendance(): void
    {
        $faculty = $this->employee('EMP-CONSULT-ATT', EmployeeEmploymentInformation::TYPE_FACULTY);
        $thisMonday = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);

        $skolaris = Mockery::mock(SkolarisApiService::class);
        $skolaris->shouldReceive('resolveSkolarisEmployeeRecord')->andReturn(['employee_id' => 282, 'employee_number' => 'EMP-CONSULT-ATT']);
        $skolaris->shouldReceive('timekeepingEmployeeAttendance')->once()->andReturn([
            'source' => 'stored',
            'logs' => [
                [
                    'attendance_date' => $thisMonday->subWeeks(2)->toDateString(),
                    'schedule_day' => 'MON',
                    'subject_code' => 'OLDTERM',
                    'time_in' => '14:00',
                    'time_out' => '15:00',
                    'schedule' => 'MON 14:00 - 15:00',
                ],
                [
                    'attendance_date' => $thisMonday->subWeek()->toDateString(),
                    'schedule_day' => 'MON',
                    'subject_code' => 'OLNSTP-1',
                    'section' => 'LFCA111M026',
                    'time_in' => '06:00',
                    'time_out' => '07:30',
                    'schedule' => 'MON 06:00 - 07:30',
                ],
            ],
        ]);
        $skolaris->shouldNotReceive('dailyLoads');
        $this->app->instance(SkolarisApiService::class, $skolaris);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'consultation_hours' => [
                    'a' => ['day_id' => 2, 'time_start' => '07:00', 'time_end' => '08:00'],
                    'b' => ['day_id' => 2, 'time_start' => '14:00', 'time_end' => '15:00'],
                ],
            ])
            ->assertSessionHas('consultation_check', [
                'employee_id' => $faculty->employee_id,
                'source' => FacultyConsultationConflictService::SOURCE_SKOLARIS,
                'conflicts' => ['Monday 7:00 AM–8:00 AM conflicts with OLNSTP-1 LFCA111M026 (6:00 AM–7:30 AM).'],
            ]);
    }

    #[Test]
    public function back_to_back_class_is_not_a_conflict(): void
    {
        $faculty = $this->employee('EMP-CONSULT-EDGE', EmployeeEmploymentInformation::TYPE_FACULTY);
        $monday = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        $this->mockSkolarisLoads([[
            'employee_number' => 'EMP-CONSULT-EDGE',
            'loads' => [[
                'attendance_date' => $monday,
                'subject_code' => 'OLHUM001',
                'scheduled_time_in' => '07:30:00',
                'scheduled_time_out' => '09:00:00',
            ]],
        ]]);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'consultation_hours' => [
                    'a' => ['day_id' => 2, 'time_start' => '09:00', 'time_end' => '10:00'],
                ],
            ])
            ->assertSessionHas('consultation_check.conflicts', []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function mockSkolarisLoads(array $rows): void
    {
        $skolaris = Mockery::mock(SkolarisApiService::class);
        $skolaris->shouldReceive('resolveSkolarisEmployeeRecord')->andReturn(null);
        $skolaris->shouldReceive('dailyLoads')->andReturn($rows);
        $this->app->instance(SkolarisApiService::class, $skolaris);
    }

    #[Test]
    public function consultation_tab_is_shown_only_for_faculty(): void
    {
        $faculty = $this->employee('EMP-CONSULT-FAC', EmployeeEmploymentInformation::TYPE_FACULTY);
        $staff = $this->employee('EMP-CONSULT-STF', EmployeeEmploymentInformation::TYPE_STAFF);

        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['search' => 'EMP-CONSULT-FAC']))
            ->assertOk()
            ->assertSee('Consultation Hours')
            ->assertSee('data-consultation-add', false);

        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['search' => 'EMP-CONSULT-STF']))
            ->assertOk()
            ->assertDontSee('consultation-employee-'.$staff->employee_id);

        $this->assertNotSame($faculty->employee_id, $staff->employee_id);
    }

    #[Test]
    public function faculty_can_save_multiple_consultation_ranges_per_day(): void
    {
        $faculty = $this->employee('EMP-CONSULT-SAVE', EmployeeEmploymentInformation::TYPE_FACULTY);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'form_context' => 'consultation-employee-'.$faculty->employee_id,
                'setup_employee_id' => $faculty->employee_id,
                'active_tab' => 'consultation',
                'consultation_hours' => [
                    'mon-1' => ['day_id' => 2, 'time_start' => '09:00', 'time_end' => '10:00'],
                    'mon-2' => ['day_id' => 2, 'time_start' => '14:00', 'time_end' => '15:00'],
                    'wed-1' => ['day_id' => 4, 'time_start' => '11:00', 'time_end' => '12:00'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $faculty->employee_id)->count());
        $this->assertDatabaseHas('tbl_timekeeping_employee_consultation_hours', [
            'employee_id' => $faculty->employee_id,
            'day_id' => 2,
            'time_start' => '09:00:00',
            'time_end' => '10:00:00',
        ]);
        $this->assertDatabaseHas('tbl_timekeeping_employee_consultation_hours', [
            'employee_id' => $faculty->employee_id,
            'day_id' => 2,
            'time_start' => '14:00:00',
            'time_end' => '15:00:00',
        ]);
        $this->assertTrue(
            SysLog::query()
                ->where('table_name', 'tbl_timekeeping_employee_consultation_hours')
                ->where('action', 'add')
                ->where('record_id', $faculty->employee_id)
                ->exists()
        );

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'consultation_hours' => [
                    'wed-1' => ['day_id' => 4, 'time_start' => '11:00', 'time_end' => '12:00'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $faculty->employee_id)->count());
    }

    #[Test]
    public function overlapping_ranges_on_the_same_day_are_rejected(): void
    {
        $faculty = $this->employee('EMP-CONSULT-OVER', EmployeeEmploymentInformation::TYPE_FACULTY);

        $this->actingAs($this->user)
            ->from(route(TimekeepingEmployeeProfile::routeName('index'), ['setup_employee' => $faculty->employee_id]))
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $faculty->employee_id), [
                'form_context' => 'consultation-employee-'.$faculty->employee_id,
                'setup_employee_id' => $faculty->employee_id,
                'active_tab' => 'consultation',
                'consultation_hours' => [
                    'a' => ['day_id' => 2, 'time_start' => '09:00', 'time_end' => '11:00'],
                    'b' => ['day_id' => 2, 'time_start' => '10:30', 'time_end' => '12:00'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('consultation_hours.b.time_start');

        $this->assertSame(0, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $faculty->employee_id)->count());
    }

    #[Test]
    public function staff_cannot_save_consultation_hours(): void
    {
        $staff = $this->employee('EMP-CONSULT-DENY', EmployeeEmploymentInformation::TYPE_STAFF);

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('consultation.store'), $staff->employee_id), [
                'consultation_hours' => [
                    'a' => ['day_id' => 2, 'time_start' => '09:00', 'time_end' => '10:00'],
                ],
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Consultation hours are only available for faculty employees.');

        $this->assertSame(0, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $staff->employee_id)->count());
    }

    private function employee(string $number, string $userType): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => $number,
            'first_name' => 'Consult',
            'last_name' => $userType,
        ]);

        $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => $userType,
            'sort_order' => 1,
        ]);

        return $employee;
    }
}
