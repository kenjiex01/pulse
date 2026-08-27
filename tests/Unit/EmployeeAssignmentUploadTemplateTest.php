<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Services\EmployeeUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeAssignmentUploadTemplateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function assignment_template_includes_all_employees_and_current_main_campus(): void
    {
        $cainta = Campus::query()->create([
            'campus_code' => 'CA',
            'campus_name' => 'ICCT Colleges Cainta Main Campus',
            'is_active' => true,
        ]);
        $antipolo = Campus::query()->create([
            'campus_code' => 'UA',
            'campus_name' => 'ICCT Colleges Antipolo Campus',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'ASN-TPL-1',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.tpl@example.com',
            'phone' => '09171234567',
            'campus_id' => $cainta->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $cainta->campus_id,
            'biometric_id' => '1',
            'is_primary' => false,
            'sort_order' => 0,
        ]);
        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $antipolo->campus_id,
            'biometric_id' => '2',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $binary = app(EmployeeUploadService::class)->buildTemplateBinary('employee-assignment');
        $temp = tempnam(sys_get_temp_dir(), 'asn').'.xlsx';
        file_put_contents($temp, $binary);

        $sheet = IOFactory::load($temp)->getActiveSheet()->toArray(null, true, true, false);
        unlink($temp);

        $this->assertSame('employee_number', $sheet[0][0] ?? null);
        $this->assertSame('campus_code', $sheet[0][2] ?? null);
        $this->assertSame('ASN-TPL-1', $sheet[2][0] ?? null);
        $this->assertSame('Dela Cruz, Juan Santos', $sheet[2][1] ?? null);
        $this->assertSame('UA', $sheet[2][2] ?? null);
    }
}
