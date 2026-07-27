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

class EmployeeNameValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_wizard_details_requires_middle_name_when_toggle_is_off(): void
    {
        $user = User::query()->firstOrFail();
        $payload = $this->employeePayload();
        unset($payload['middle_name']);

        $this->actingAs($user)
            ->post(route('employees.wizard.campus'), [
                'campus_id' => $payload['campus_assignments'][0]['campus_id'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('employees.wizard.details'), $payload)
            ->assertSessionHasErrors('middle_name');
    }

    public function test_wizard_details_allows_empty_middle_name_when_toggle_is_on(): void
    {
        $user = User::query()->firstOrFail();
        $payload = $this->employeePayload();
        unset($payload['middle_name']);
        $payload['no_middle_name'] = true;

        $this->actingAs($user)
            ->post(route('employees.wizard.campus'), [
                'campus_id' => $payload['campus_assignments'][0]['campus_id'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('employees.wizard.details'), $payload)
            ->assertRedirect(route('employees.create', ['step' => 2]));
    }

    public function test_update_clears_middle_name_when_no_middle_name_is_selected(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['no_middle_name'] = true;
        unset($payload['middle_name']);

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'personal']));

        $employee->refresh();

        $this->assertNull($employee->middle_name);
    }

    public function test_update_requires_middle_name_when_toggle_is_off(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['middle_name'] = '';

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertSessionHasErrors('middle_name');
    }

    private function createEmployee(User $user): Employee
    {
        $this->actingAs($user)->post(route('employees.wizard.campus'), [
            'campus_id' => Campus::query()->where('campus_code', 'CA')->firstOrFail()->campus_id,
        ]);

        $payload = $this->employeePayload();
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
            'email' => $employee?->email ?? 'name.test@example.com',
            'phone' => $employee?->phone ?? '09171234567',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_hybrid' => false,
            'employment_informations' => [[
                'user_type' => 'staff',
                'position' => 'Clerk',
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
