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

class EmployeeEmploymentEffectivityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_effectivity_to_cannot_be_before_effectivity_from(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['employment_informations'][0]['date_effective_from'] = '2024-10-01';
        $payload['employment_informations'][0]['date_effective_to'] = '2024-09-30';

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertSessionHasErrors('employment_informations.0.date_effective_to');
    }

    private function createEmployee(User $user): Employee
    {
        $this->actingAs($user)->post(route('employees.wizard.campus'), [
            'campus_id' => Campus::query()->where('campus_code', 'CA')->firstOrFail()->campus_id,
        ]);

        $this->actingAs($user)->post(route('employees.wizard.details'), $this->employeePayload())->assertRedirect();

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
            'first_name' => $employee?->first_name ?? 'Effectivity',
            'middle_name' => $employee?->middle_name ?? 'Range',
            'last_name' => $employee?->last_name ?? 'Test',
            'email' => $employee?->email ?? 'effectivity.test@example.com',
            'phone' => $employee?->phone ?? '09171234567',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_hybrid' => false,
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
                'is_primary' => true,
            ]],
        ];
    }
}
