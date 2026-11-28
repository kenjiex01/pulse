<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\College;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_dept_college_filter_lists_each_college_name_once(): void
    {
        $this->assertGreaterThan(1, Campus::query()->count());
        $this->assertGreaterThan(
            College::query()->distinct()->count('college_name'),
            College::query()->count(),
        );

        $html = $this->actingAs(User::query()->firstOrFail())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'value="college:College of Arts &amp; Sciences"'));
        $this->assertSame(1, substr_count($html, 'value="college:College of Business &amp; Accountancy"'));
        $this->assertSame(1, substr_count($html, 'value="college:College of Computer Studies"'));
    }

    public function test_ajax_college_filter_returns_matching_employees(): void
    {
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();

        $arts = $this->createListEmployee($campus, 'EMP-ARTS-1', 'College of Arts & Sciences');
        $this->createListEmployee($campus, 'EMP-CCS-1', 'College of Computer Studies');

        $response = $this->actingAs(User::query()->firstOrFail())
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('employees.index', [
                'dept_college' => 'college:College of Arts & Sciences',
            ]));

        $response->assertOk();
        $response->assertSee('EMP-ARTS-1', false);
        $response->assertDontSee('EMP-CCS-1', false);
        $response->assertSee('data-total="1"', false);
        $this->assertTrue($arts->exists);
    }

    public function test_ajax_employment_category_filter_uses_employment_table(): void
    {
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();
        $faculty = $this->createListEmployee($campus, 'EMP-FAC-1', 'College of Arts & Sciences');
        $staff = $this->createListEmployee($campus, 'EMP-STAFF-1', 'College of Arts & Sciences');

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

        $response = $this->actingAs(User::query()->firstOrFail())
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('employees.index', [
                'employment_category' => EmployeeEmploymentInformation::TYPE_FACULTY,
            ]));

        $response->assertOk();
        $response->assertSee('EMP-FAC-1', false);
        $response->assertDontSee('EMP-STAFF-1', false);
    }

    public function test_employee_list_shows_length_of_service_from_hire_date(): void
    {
        Carbon::setTestNow('2026-11-28');

        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();
        $employee = $this->createListEmployee($campus, 'EMP-LOS-1', 'College of Arts & Sciences');

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'position' => 'Clerk',
            'hire_date' => '2024-09-15',
            'sort_order' => 0,
        ]);

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('employees.index', ['search' => 'EMP-LOS-1']))
            ->assertOk()
            ->assertSee('Length of Service', false)
            ->assertSee('2 years, 2 months, 13 days', false);

        Carbon::setTestNow();
    }

    private function createListEmployee(Campus $campus, string $employeeNumber, string $college): Employee
    {
        return Employee::query()->create([
            'campus_id' => $campus->campus_id,
            'campus' => $campus->campus_code,
            'employee_number' => $employeeNumber,
            'first_name' => 'Test',
            'last_name' => $employeeNumber,
            'email' => strtolower($employeeNumber).'@example.com',
            'college' => $college,
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
        ]);
    }
}
