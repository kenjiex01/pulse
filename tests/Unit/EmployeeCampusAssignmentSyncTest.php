<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Services\EmployeeCampusAssignmentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeCampusAssignmentSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_uses_checked_main_assignment_not_first_row(): void
    {
        [$employee, $cainta, $antipolo] = $this->employeeWithTwoCampuses();

        EmployeeCampusAssignmentSync::sync($employee, [
            [
                'campus_id' => $cainta->campus_id,
                'biometric_id' => '100',
                'is_primary' => false,
            ],
            [
                'campus_id' => $antipolo->campus_id,
                'biometric_id' => '200',
                'is_primary' => true,
            ],
        ]);

        $employee->refresh()->load('campusAssignments.campus');

        $this->assertTrue((bool) $employee->campusAssignments->firstWhere('campus_id', $antipolo->campus_id)?->is_primary);
        $this->assertFalse((bool) $employee->campusAssignments->firstWhere('campus_id', $cainta->campus_id)?->is_primary);
        $this->assertSame($antipolo->campus_id, $employee->campus_id);
        $this->assertSame('UA', $employee->campus);
    }

    #[Test]
    public function set_main_campus_unchecks_the_previous_main(): void
    {
        [$employee, $cainta, $antipolo] = $this->employeeWithTwoCampuses();

        EmployeeCampusAssignmentSync::setMainCampus($employee, $antipolo->campus_id);

        $employee->refresh()->load('campusAssignments');

        $this->assertTrue((bool) $employee->campusAssignments->firstWhere('campus_id', $antipolo->campus_id)?->is_primary);
        $this->assertFalse((bool) $employee->campusAssignments->firstWhere('campus_id', $cainta->campus_id)?->is_primary);
        $this->assertSame($antipolo->campus_id, $employee->campus_id);
    }

    /**
     * @return array{0: Employee, 1: Campus, 2: Campus}
     */
    private function employeeWithTwoCampuses(): array
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
            'employee_number' => 'ASN-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.asn@example.com',
            'phone' => '09171234567',
            'campus_id' => $cainta->campus_id,
            'campus' => 'CA',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $cainta->campus_id,
            'biometric_id' => '100',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $antipolo->campus_id,
            'biometric_id' => '200',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        return [$employee->fresh(['campusAssignments']), $cainta, $antipolo];
    }
}
