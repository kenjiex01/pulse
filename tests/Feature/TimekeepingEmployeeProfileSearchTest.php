<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\User;
use App\Support\TimekeepingEmployeeProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimekeepingEmployeeProfileSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_ajax_search_filters_employee_profile_results(): void
    {
        $user = User::query()->firstOrFail();

        Employee::query()->create([
            'employee_number' => 'EMP-BISCO',
            'first_name' => 'JUSTIN',
            'last_name' => 'BISCOCHO',
        ]);

        Employee::query()->create([
            'employee_number' => 'EMP-OTHER',
            'first_name' => 'MARIA',
            'last_name' => 'SANTOS',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['search' => 'bisco']));

        $response->assertOk();
        $response->assertSee('BISCOCHO');
        $response->assertDontSee('EMP-OTHER');
        $response->assertSee('data-total="1"', false);
    }

    public function test_ajax_employment_category_filter_limits_employee_profile_results(): void
    {
        $user = User::query()->firstOrFail();
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();

        $faculty = Employee::query()->create([
            'campus_id' => $campus->campus_id,
            'campus' => $campus->campus_code,
            'employee_number' => 'EMP-PROFILE-FAC',
            'first_name' => 'Faculty',
            'last_name' => 'Profile',
            'email' => 'emp-profile-fac@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
        ]);

        $staff = Employee::query()->create([
            'campus_id' => $campus->campus_id,
            'campus' => $campus->campus_code,
            'employee_number' => 'EMP-PROFILE-STAFF',
            'first_name' => 'Staff',
            'last_name' => 'Profile',
            'email' => 'emp-profile-staff@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $faculty->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'position' => 'Instructor',
            'sort_order' => 0,
        ]);
        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $staff->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'Clerk',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), [
                'employment_category' => EmployeeEmploymentInformation::TYPE_FACULTY,
            ]));

        $response->assertOk();
        $response->assertSee('EMP-PROFILE-FAC', false);
        $response->assertDontSee('EMP-PROFILE-STAFF', false);
    }
}
