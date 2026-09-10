<?php

namespace Tests\Feature;

use App\Models\BasicComputation;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\User;
use App\Services\EmployeeUploadRowMapper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_update_persists_bank_account_fields(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['bank_name'] = 'BDO Unibank';
        $payload['bank_account_number'] = '1234 5678 9012';
        $payload['bank_account_type'] = Employee::BANK_ACCOUNT_TYPE_SAVINGS;

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'personal']));

        $employee->refresh();

        $this->assertSame('BDO Unibank', $employee->bank_name);
        $this->assertSame('123456789012', $employee->bank_account_number);
        $this->assertSame(Employee::BANK_ACCOUNT_TYPE_SAVINGS, $employee->bank_account_type);
    }

    public function test_update_rejects_invalid_bank_account_type(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->createEmployee($user);
        $payload = $this->employeePayload($employee);
        $payload['bank_account_type'] = 'investment';

        $this->actingAs($user)
            ->put(route('employees.update', $employee), $payload)
            ->assertSessionHasErrors('bank_account_type');
    }

    public function test_master_upload_maps_bank_account_fields(): void
    {
        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $seenEmails = [];

        $result = $mapper->mapRow(
            array_merge($this->minimalUploadRow(), [
                'bank_name' => 'Metrobank',
                'bank_account_number' => '9876 5432 10',
                'bank_account_type' => 'payroll',
            ]),
            2,
            $seenNumbers,
            $seenEmails,
            true,
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame('Metrobank', $result['payload']['employee']['bank_name']);
        $this->assertSame('9876543210', $result['payload']['employee']['bank_account_number']);
        $this->assertSame('payroll', $result['payload']['employee']['bank_account_type']);
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
            'email' => $employee?->email ?? 'bank-account.test@example.com',
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
                'biometric_id' => '9001',
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function minimalUploadRow(): array
    {
        return [
            'employee_number' => '25-BANK001',
            'email' => 'bank.upload@example.com',
            'first_name' => 'Bank',
            'last_name' => 'Upload',
            'phone' => '',
            'role' => '',
            'campus_code' => '',
            'biometric_id' => '',
            'user_type' => '',
            'is_hybrid' => '',
        ];
    }
}
