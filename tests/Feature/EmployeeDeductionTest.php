<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_edit_page_shows_deductions_tab(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->employee();

        $this->actingAs($user)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('Deductions')
            ->assertSee('employee-deduction-create-modal', false);
    }

    public function test_store_update_and_delete_employee_deduction(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->employee();
        $typeId = $this->otherDeductionTypeId();

        $this->actingAs($user)
            ->post(route('employees.deductions.store', $employee), [
                'deduction_type_id' => $typeId,
                'dt_deduction' => '2026-06-01',
                'deduction_amount' => 5000,
                'amortization_amount' => 500,
                'deduction_interest' => 100,
            ])
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'deductions']));

        $deduction = EmployeeDeduction::query()->where('employee_id', $employee->employee_id)->firstOrFail();
        $this->assertSame(5100.0, $deduction->balance());

        $this->actingAs($user)
            ->put(route('employees.deductions.update', [$employee, $deduction]), [
                'deduction_type_id' => $typeId,
                'dt_deduction' => '2026-06-02',
                'deduction_amount' => 4000,
                'amortization_amount' => 400,
                'deduction_interest' => 0,
            ])
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'deductions']));

        $deduction->refresh();
        $this->assertSame('4000.00', $deduction->deduction_amount);
        $this->assertSame(4000.0, $deduction->balance());

        $this->actingAs($user)
            ->delete(route('employees.deductions.destroy', [$employee, $deduction]))
            ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'deductions']));

        $this->assertSoftDeleted('tbl_employee_deductions', [
            'employee_deduction_id' => $deduction->employee_deduction_id,
        ]);
    }

    public function test_government_and_attendance_deduction_types_are_rejected(): void
    {
        $user = User::query()->firstOrFail();
        $employee = $this->employee();

        foreach (['SSSP', 'LTDE'] as $code) {
            $typeId = DeductionType::query()->where('deduction_type_code', $code)->value('deduction_type_id');

            $this->actingAs($user)
                ->from(route('employees.edit', ['employee' => $employee, 'tab' => 'deductions']))
                ->post(route('employees.deductions.store', $employee), [
                    'form_context' => 'create-employee-deduction',
                    'deduction_type_id' => $typeId,
                    'dt_deduction' => '2026-06-01',
                    'deduction_amount' => 1000,
                    'amortization_amount' => 100,
                ])
                ->assertRedirect(route('employees.edit', ['employee' => $employee, 'tab' => 'deductions']))
                ->assertSessionHasErrors('deduction_type_id');
        }

        $this->assertSame(0, EmployeeDeduction::query()->count());
    }

    private function employee(): Employee
    {
        return Employee::query()->create([
            'employee_number' => 'EMP-DED-'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Deduct',
        ]);
    }

    private function otherDeductionTypeId(): int
    {
        return (int) DeductionType::query()->where('deduction_type_code', 'OTHR')->value('deduction_type_id');
    }
}
