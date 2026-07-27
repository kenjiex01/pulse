<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\BasicComputation;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeWizardSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWizardCampusAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_wizard_details_merges_campus_assignments_into_session(): void
    {
        $user = User::query()->firstOrFail();
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();

        $this->actingAs($user)->post(route('employees.wizard.campus'), [
            'campus_id' => $campus->campus_id,
        ])->assertRedirect(route('employees.create', ['step' => 1]));

        $payload = $this->minimalWizardPayload($campus->campus_id);

        $this->actingAs($user)->post(route('employees.wizard.details'), $payload)
            ->assertRedirect(route('employees.create', ['step' => 2]));

        $data = EmployeeWizardSession::data();

        $this->assertSame('8376', $data['campus_assignments'][0]['biometric_id'] ?? null);
        $this->assertSame('College of Engineering', $data['campus_assignments'][0]['college'] ?? null);
        $this->assertSame('HR Department', $data['campus_assignments'][0]['department'] ?? null);
        $this->assertSame('BSIT', $data['campus_assignments'][0]['program'] ?? null);
    }

    public function test_wizard_store_persists_campus_assignment_with_biometric_id(): void
    {
        $user = User::query()->firstOrFail();
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();
        $role = Role::query()->firstOrFail();

        EmployeeWizardSession::putCampus($campus->campus_id);
        EmployeeWizardSession::mergeData($this->minimalWizardPayload($campus->campus_id));

        $response = $this->actingAs($user)->post(route('employees.store'), [
            'role_id' => $role->id,
        ]);

        $employee = Employee::query()->latest('employee_id')->first();

        $this->assertNotNull($employee);
        $response->assertRedirect(route('employees.show', $employee));

        $assignment = $employee->campusAssignments()->first();
        $this->assertNotNull($assignment);
        $this->assertSame('8376', $assignment->biometric_id);
        $this->assertSame('College of Engineering', $assignment->college);
        $this->assertSame('HR Department', $assignment->department);
        $this->assertSame('BSIT', $assignment->program);
        $this->assertSame($campus->campus_id, $employee->campus_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalWizardPayload(int $campusId): array
    {
        $payType = PayType::query()->firstOrFail();
        $basicComputation = BasicComputation::query()->firstOrFail();
        $rateGroup = RateGroup::query()->firstOrFail();

        return [
            'employee_number' => Employee::generateEmployeeNumber(),
            'first_name' => 'Anna',
            'middle_name' => 'Marie',
            'last_name' => 'Test',
            'email' => 'anna.test@example.com',
            'phone' => '09171234567',
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
                'campus_id' => $campusId,
                'biometric_id' => '8376',
                'college' => 'College of Engineering',
                'department' => 'HR Department',
                'program' => 'BSIT',
            ]],
        ];
    }
}
