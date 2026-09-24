<?php

namespace Tests\Feature;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeGenderStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_update_stores_male_and_female_with_title_case(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user, 'male');

        $this->assertSame(Employee::GENDER_MALE, $employee->gender);

        $payload = $this->employeePayload($employee);
        $payload['gender'] = 'female';

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'personal']));

        $employee->refresh();

        $this->assertSame(Employee::GENDER_FEMALE, $employee->gender);
        $this->assertSame('Female', $employee->gender);
    }

    public function test_edit_form_uses_title_case_gender_option_values(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user, 'female');

        $html = $this->actingAs($user)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="Male"', $html);
        $this->assertStringContainsString('value="Female"', $html);
        $this->assertStringNotContainsString('value="male"', $html);
        $this->assertStringNotContainsString('value="female"', $html);
        $this->assertStringContainsString('selected', $html);
    }

    public function test_form_options_submit_title_case_values(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['gender'] = Employee::GENDER_MALE;

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'personal']));

        $this->assertSame('Male', $employee->fresh()->gender);
    }

    private function createEmployee(User $user, string $gender = 'Male'): Employee
    {
        $this->actingAs($user)->post(route('employees.wizard.campus'), [
            'campus_id' => Campus::query()->where('campus_code', 'CA')->firstOrFail()->campus_id,
        ]);

        $payload = $this->employeePayload();
        $payload['gender'] = $gender;
        $this->actingAs($user)->post(route('employees.wizard.details'), $payload)->assertRedirect();

        $this->actingAs($user)->post(route('employees.store'), [
            'role_id' => $user->roles()->firstOrFail()->id,
        ])->assertRedirect();

        return Employee::query()->latest('employee_id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(?Employee $employee = null): array
    {
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();
        $payType = PayType::query()->firstOrFail();
        $basicComputation = BasicComputation::query()->firstOrFail();
        $rateGroup = RateGroup::query()->firstOrFail();

        return [
            'employee_number' => $employee?->employee_number ?? Employee::generateEmployeeNumber(),
            'first_name' => $employee?->first_name ?? 'Anna',
            'middle_name' => $employee?->middle_name ?? 'Marie',
            'last_name' => $employee?->last_name ?? 'Test',
            'email' => $employee?->email ?? 'gender.test@example.com',
            'phone' => $employee?->phone ?? '09171234567',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_hybrid' => false,
            'gender' => $employee?->gender ?? Employee::GENDER_FEMALE,
            'employment_informations' => [[
                'user_type' => 'staff',
                'position' => 'Clerk',
                'date_effective_from' => '2026-01-01',
            ]],
            'employee_salaries' => [[
                'date_effective_from' => '2026-01-01',
                'basic_computation_id' => $basicComputation->basic_computation_id,
                'pay_type_id' => $payType->pay_type_id,
                'rate_group_id' => $rateGroup->rate_group_id,
            ]],
            'campus_assignments' => [[
                'campus_id' => $campus->campus_id,
                'biometric_id' => '8376',
                'college' => 'College of Engineering',
                'department' => 'HR Department',
                'program' => 'BSIT',
            ]],
        ];
    }
}
