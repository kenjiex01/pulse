<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollCalendarUserType;
use App\Models\PayType;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollBatchEligibleEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_add_employee_list_matches_the_pay_period_category(): void
    {
        $staff = $this->makeEmployee('Staff', EmployeeEmploymentInformation::TYPE_STAFF);
        $faculty = $this->makeEmployee('Faculty', EmployeeEmploymentInformation::TYPE_FACULTY);
        $admin = $this->makeEmployee('Admin', EmployeeEmploymentInformation::TYPE_ADMIN);
        $batch = $this->makeBatch([EmployeeEmploymentInformation::TYPE_STAFF]);

        $ids = app(PayrollBatchService::class)
            ->eligibleEmployeesQuery($batch)
            ->pluck('employee_id')
            ->all();

        $this->assertContains($staff->employee_id, $ids);
        $this->assertNotContains($faculty->employee_id, $ids);
        $this->assertNotContains($admin->employee_id, $ids);
    }

    public function test_add_employee_list_includes_every_selected_category(): void
    {
        $staff = $this->makeEmployee('Staff', EmployeeEmploymentInformation::TYPE_STAFF);
        $faculty = $this->makeEmployee('Faculty', EmployeeEmploymentInformation::TYPE_FACULTY);
        $admin = $this->makeEmployee('Admin', EmployeeEmploymentInformation::TYPE_ADMIN);
        $batch = $this->makeBatch([
            EmployeeEmploymentInformation::TYPE_STAFF,
            EmployeeEmploymentInformation::TYPE_FACULTY,
        ]);

        $ids = app(PayrollBatchService::class)
            ->eligibleEmployeesQuery($batch)
            ->pluck('employee_id')
            ->all();

        $this->assertContains($staff->employee_id, $ids);
        $this->assertContains($faculty->employee_id, $ids);
        $this->assertNotContains($admin->employee_id, $ids);
    }

    public function test_hybrid_employee_appears_when_one_role_matches_the_pay_period(): void
    {
        $hybrid = $this->makeEmployee('Hybrid', EmployeeEmploymentInformation::TYPE_FACULTY, hybrid: true);
        $hybrid->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 2,
        ]);
        $staffEmployment = $hybrid->employmentInformations()
            ->where('user_type', EmployeeEmploymentInformation::TYPE_STAFF)
            ->firstOrFail();
        $this->attachSalary($staffEmployment);

        $batch = $this->makeBatch([EmployeeEmploymentInformation::TYPE_STAFF]);

        $ids = app(PayrollBatchService::class)
            ->eligibleEmployeesQuery($batch)
            ->pluck('employee_id')
            ->all();

        $this->assertContains($hybrid->employee_id, $ids);
    }

    public function test_faculty_cannot_be_added_to_a_staff_only_pay_period(): void
    {
        $faculty = $this->makeEmployee('Faculty', EmployeeEmploymentInformation::TYPE_FACULTY);
        $batch = $this->makeBatch([EmployeeEmploymentInformation::TYPE_STAFF]);

        $this->expectException(ValidationException::class);

        app(PayrollBatchService::class)->addEmployees($batch, [$faculty->employee_id]);
    }

    public function test_empty_message_names_the_pay_period_category(): void
    {
        $batch = $this->makeBatch([EmployeeEmploymentInformation::TYPE_STAFF]);

        $message = app(PayrollBatchService::class)->addEmployeesEmptyMessage($batch, false);

        $this->assertStringContainsString('Staff', $message);
    }

    private function makeEmployee(string $lastName, string $userType, bool $hybrid = false): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-CAT-'.uniqid(),
            'first_name' => 'Eligible',
            'last_name' => $lastName,
            'is_hybrid' => $hybrid,
            'is_active' => true,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $employment = $employee->employmentInformations()->create([
            'user_type' => $userType,
            'sort_order' => 1,
        ]);

        $this->attachSalary($employment);

        return $employee;
    }

    private function attachSalary(EmployeeEmploymentInformation $employment): void
    {
        $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 11,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
        ]);
    }

    /**
     * @param  list<string>  $userTypes
     */
    private function makeBatch(array $userTypes): PayrollBatch
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        foreach ($userTypes as $userType) {
            PayrollCalendarUserType::query()->create([
                'payroll_calendar_id' => $calendar->payroll_calendar_id,
                'user_type' => $userType,
            ]);
        }

        return PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => User::query()->value('id'),
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);
    }
}
