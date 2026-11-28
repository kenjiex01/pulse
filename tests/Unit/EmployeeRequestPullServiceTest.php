<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeOvertimeApproval;
use App\Models\EmployeeRequest;
use App\Models\Module;
use App\Models\RawTimekeepingInandout;
use App\Models\Role;
use App\Models\SubModule;
use App\Models\User;
use App\Services\EmployeeAttendanceLogService;
use App\Services\EmployeeRequestPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeRequestPullServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_approved_amendment_replaces_that_days_logs_and_job_order_files_overtime(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => '19-ACDO180497',
            'first_name' => 'Renato',
            'middle_name' => 'Justiniano',
            'last_name' => 'Alcantara',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $logs = app(EmployeeAttendanceLogService::class);
        $logs->create($employee, now()->parse('2026-11-18 06:45:00'), true, $user->id);
        $logs->create($employee, now()->parse('2026-11-18 17:02:00'), false, $user->id);

        $service = app(EmployeeRequestPullService::class);
        $summary = $service->importRows([
            [
                'request_id' => 10,
                'request_number' => 'AMENDATTENDANCE-20261118-0001',
                'request_type' => ['type_code' => 'amend_attendance', 'type_name' => 'Amendment Form'],
                'status' => ['status_code' => 'for_approval', 'status_name' => 'For Approval'],
                'prepared_by_employee_id' => '19-ACDO180497',
                'prepared_by_name' => 'Renato Justiniano Alcantara',
                'form_payload' => [
                    'attendance_date' => '2026-11-18',
                    'time_in_am' => '08:00',
                    'time_out_pm' => '17:30',
                ],
            ],
            [
                'request_id' => 11,
                'request_number' => 'AMENDATTENDANCE-20261118-0002',
                'request_type' => ['type_code' => 'amend_attendance', 'type_name' => 'Amendment Form'],
                'status' => ['status_code' => 'approved', 'status_name' => 'Approved'],
                'prepared_by_employee_id' => '19-ACDO180497',
                'prepared_by_name' => 'Renato Justiniano Alcantara',
                'approved_at' => '2026-11-20 09:00:00',
                'form_payload' => [
                    'attendance_date' => '2026-11-18',
                    'time_in_am' => '08:00',
                    'time_out_pm' => '17:30',
                    'reason' => 'Wrong biometric time',
                ],
            ],
            [
                'request_id' => 12,
                'request_number' => 'JOBORDER-20261118-0001',
                'request_type' => ['type_code' => 'job_order', 'type_name' => 'Job Order'],
                'status' => ['status_code' => 'approved', 'status_name' => 'Approved'],
                'prepared_by_employee_id' => '19-ACDO180497',
                'prepared_by_name' => 'Renato Justiniano Alcantara',
                'approvers' => [[
                    'name' => 'Ana Liza Roxas Kho',
                    'status' => 'approved',
                    'signature_data_uri' => 'data:image/png;base64,AAAA',
                ]],
                'items' => [[
                    'employee_id' => '19-ACDO180497',
                    'employee_name' => 'Renato Justiniano Alcantara',
                    'description_of_works' => 'Encoding',
                    'work_schedules' => [[
                        'date_from' => '2026-11-18',
                        'date_to' => '2026-11-18',
                        'time_from' => '18:00',
                        'time_to' => '20:00',
                    ]],
                ]],
            ],
        ], $user->id);

        $this->assertSame(2, $summary['pulled']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $summary['applied']);

        $punches = RawTimekeepingInandout::query()
            ->where('employee_id', $employee->employee_id)
            ->whereDate('dt_datetime', '2026-11-18')
            ->orderBy('dt_datetime')
            ->get();

        $this->assertCount(2, $punches);
        $this->assertSame('2026-11-18 08:00:00', $punches[0]->dt_datetime?->format('Y-m-d H:i:s'));
        $this->assertTrue((bool) $punches[0]->is_in);
        $this->assertSame('2026-11-18 17:30:00', $punches[1]->dt_datetime?->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) $punches[1]->is_in);

        $overtime = EmployeeOvertimeApproval::query()->where('employee_id', $employee->employee_id)->get();
        $this->assertCount(1, $overtime);
        $this->assertSame('2026-11-18', $overtime[0]->work_date?->toDateString());
        $this->assertSame('18:00:00', $overtime[0]->ot_start?->format('H:i:s'));
        $this->assertSame('20:00:00', $overtime[0]->ot_end?->format('H:i:s'));

        $jobOrder = EmployeeRequest::query()->where('skolaris_request_id', 12)->first();
        $this->assertSame('Ana Liza Roxas Kho', $jobOrder->approvers[0]['name']);
        $this->assertArrayNotHasKey('signature_data_uri', $jobOrder->approvers[0]);

        $service->importRows([[
            'request_id' => 12,
            'request_number' => 'JOBORDER-20261118-0001',
            'request_type' => ['type_code' => 'job_order', 'type_name' => 'Job Order'],
            'status' => ['status_code' => 'approved'],
            'prepared_by_employee_id' => '19-ACDO180497',
            'items' => [[
                'employee_id' => '19-ACDO180497',
                'work_schedules' => [[
                    'date_from' => '2026-11-18',
                    'time_from' => '18:00',
                    'time_to' => '20:00',
                ]],
            ]],
        ]]);

        $this->assertSame(1, EmployeeOvertimeApproval::query()->where('employee_id', $employee->employee_id)->count());
        $this->assertSame(2, EmployeeRequest::query()->count());
    }

    #[Test]
    public function test_employee_request_page_shows_the_pull_button(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'slug' => Role::SLUG_ADMIN]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $module = Module::query()->create([
            'name' => 'Human Resource',
            'route_name' => 'hr.index',
            'route_pattern' => 'hr.*',
            'icon' => 'human-resource',
            'section' => 'Human Resource',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        SubModule::query()->create([
            'module_id' => $module->id,
            'name' => 'Employee Request',
            'route_name' => 'employee-requests.index',
            'route_pattern' => 'employee-requests.*',
            'icon' => 'employee-request',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('employee-requests.index'))
            ->assertOk()
            ->assertSee('Employee Request')
            ->assertSee('Pull approved')
            ->assertSee('No approved forms pulled yet.');
    }
}
