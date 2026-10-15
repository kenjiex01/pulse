<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\PayType;
use App\Models\Role;
use App\Services\EmployeeUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeUploadTemplateExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function master_template_includes_existing_employee_fields(): void
    {
        $campus = Campus::query()->create([
            'campus_code' => 'AG',
            'campus_name' => 'Angono',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff',
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'TPL-100',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@example.com',
            'phone' => '09170000001',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
            'extended_profile' => ['role_id' => $role->id],
        ]);
        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $campus->campus_id,
            'biometric_id' => '88',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => 'staff',
            'position' => 'Registrar',
            'sort_order' => 0,
        ]);

        $sheet = $this->sheet('master-file');

        $this->assertSame('employee_number', $sheet[0][0] ?? null);
        $this->assertSame('TPL-100', $sheet[3][0] ?? null);
        $this->assertSame('Maria', $sheet[3][1] ?? null);
        $this->assertSame('Santos', $sheet[3][3] ?? null);
        $this->assertSame('AG', $this->cell($sheet, 3, 'campus_code'));
        $this->assertSame('88', $this->cell($sheet, 3, 'biometric_id'));
        $this->assertSame('staff', $this->cell($sheet, 3, 'user_type'));
        $this->assertSame('Registrar', $this->cell($sheet, 3, 'position'));
        $this->assertSame('maria.santos@example.com', $this->cell($sheet, 3, 'email'));
        $this->assertSame('staff', $this->cell($sheet, 3, 'role'));
    }

    #[Test]
    public function blank_master_template_has_headers_only(): void
    {
        Employee::query()->create([
            'employee_number' => 'TPL-BLANK',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@example.com',
            'phone' => '09170000002',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        $sheet = $this->sheet('master-file', true);

        $this->assertSame('employee_number', $sheet[0][0] ?? null);
        $this->assertSame('Employee Number', $sheet[1][0] ?? null);
        $this->assertTrue(blank($sheet[2][0] ?? null));
        $this->assertTrue(blank($sheet[3][0] ?? null));
    }

    #[Test]
    public function salary_template_includes_current_salary_for_each_employee(): void
    {
        $payType = PayType::query()->create(['pay_type' => 'Daily']);
        $employee = Employee::query()->create([
            'employee_number' => 'TPL-SAL',
            'first_name' => 'Pedro',
            'middle_name' => 'Jose',
            'last_name' => 'Cruz',
            'email' => 'pedro.cruz@example.com',
            'phone' => '09170000003',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);
        $employment = EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => 'staff',
            'sort_order' => 0,
        ]);
        EmployeeSalary::query()->create([
            'employment_info_id' => $employment->employment_info_id,
            'date_effective_from' => '2026-01-15',
            'pay_type_id' => $payType->pay_type_id,
            'hours_per_day' => 8,
        ]);

        $sheet = $this->sheet('employee-salary');

        $this->assertSame('employee_name', $sheet[0][0] ?? null);
        $this->assertSame('Cruz, Pedro Jose', $sheet[3][0] ?? null);
        $this->assertSame('TPL-SAL', $this->cell($sheet, 3, 'employee_number'));
        $this->assertSame('1', $this->cell($sheet, 3, 'employment_slot'));
        $this->assertSame('2026-01-15', $this->cell($sheet, 3, 'date_effective_from'));
        $this->assertSame('Daily', $this->cell($sheet, 3, 'pay_type'));
        $this->assertSame('8', $this->cell($sheet, 3, 'hours_per_day'));
    }

    #[Test]
    public function salary_template_category_filter_includes_only_the_selected_user_type(): void
    {
        $this->makeSalaryEmployee('TPL-STAFF', 'Ana', 'Reyes', 'Liza', 'staff');
        $this->makeSalaryEmployee('TPL-FAC', 'Juan', 'Santos', 'Miguel', 'faculty');

        $sheet = $this->sheet('employee-salary', false, 'staff');
        $numbers = array_column(array_slice($sheet, 3), 1);

        $this->assertContains('TPL-STAFF', $numbers);
        $this->assertNotContains('TPL-FAC', $numbers);
        $this->assertSame('Reyes, Ana Liza', $sheet[3][0] ?? null);
    }

    #[Test]
    public function blank_assignment_template_omits_employees(): void
    {
        $campus = Campus::query()->create([
            'campus_code' => 'CA',
            'campus_name' => 'Cainta',
            'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'TPL-ASN',
            'first_name' => 'Liza',
            'last_name' => 'Garcia',
            'email' => 'liza.garcia@example.com',
            'phone' => '09170000004',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);
        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $campus->campus_id,
            'biometric_id' => '1',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $sheet = $this->sheet('employee-assignment', true);

        $this->assertSame('employee_number', $sheet[0][0] ?? null);
        $this->assertTrue(blank($sheet[2][0] ?? null));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function makeSalaryEmployee(string $number, string $first, string $last, string $middle, string $userType): void
    {
        $employee = Employee::query()->create([
            'employee_number' => $number,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'email' => strtolower($number).'@example.com',
            'phone' => '09170000099',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => $userType,
            'sort_order' => 0,
        ]);
    }

    private function sheet(string $uploadType, bool $blank = false, ?string $category = null): array
    {
        $binary = app(EmployeeUploadService::class)->buildTemplateBinary($uploadType, $blank, $category);
        $temp = tempnam(sys_get_temp_dir(), 'emp-tpl').'.xlsx';
        file_put_contents($temp, $binary);
        $sheet = IOFactory::load($temp)->getActiveSheet()->toArray(null, true, true, false);
        unlink($temp);

        return $sheet;
    }

    /**
     * @param  array<int, array<int, mixed>>  $sheet
     */
    private function cell(array $sheet, int $row, string $alias): ?string
    {
        $headers = $sheet[0] ?? [];
        $index = array_search($alias, $headers, true);

        if ($index === false) {
            return null;
        }

        $value = $sheet[$row][$index] ?? null;

        return $value === null ? null : (string) $value;
    }
}
