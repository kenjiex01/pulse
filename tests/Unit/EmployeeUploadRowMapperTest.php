<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Services\EmployeeUploadRowMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeUploadRowMapperTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function disable_required_allows_partial_create_with_employee_number_and_email(): void
    {
        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $seenEmails = [];

        $result = $mapper->mapRow(
            [
                'employee_number' => '25-TEST001',
                'email' => 'partial.create@example.com',
                'first_name' => 'Partial',
                'last_name' => '',
                'phone' => '',
                'role' => '',
                'campus_code' => '',
                'biometric_id' => '',
                'user_type' => '',
                'is_hybrid' => '',
            ],
            4,
            $seenNumbers,
            $seenEmails,
            true,
        );

        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['payload']);
        $this->assertNull($result['payload']['existing_employee_id']);
        $this->assertSame('Create', $result['payload']['preview']['action']);
        $this->assertFalse($result['payload']['sync_campus']);
        $this->assertFalse($result['payload']['sync_salary']);
        $this->assertSame('25-TEST001', $result['payload']['employee']['employee_number']);
        $this->assertSame('partial.create@example.com', $result['payload']['employee']['email']);
    }

    #[Test]
    public function matching_employee_number_and_email_marks_row_as_update(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => '25-TEST002',
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'email' => 'existing.person@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
            'is_hybrid' => false,
            'is_confidential' => false,
            'country' => 'Philippines',
        ]);

        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $seenEmails = [];

        $result = $mapper->mapRow(
            [
                'employee_number' => '25-TEST002',
                'email' => 'existing.person@example.com',
                'first_name' => 'Updated',
                'last_name' => '',
                'phone' => '',
                'role' => '',
                'campus_code' => '',
                'biometric_id' => '',
                'user_type' => '',
                'is_hybrid' => '',
            ],
            5,
            $seenNumbers,
            $seenEmails,
            true,
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame($employee->employee_id, $result['payload']['existing_employee_id']);
        $this->assertSame('Update', $result['payload']['preview']['action']);
        $this->assertSame('Updated', $result['payload']['employee']['first_name']);
        $this->assertArrayNotHasKey('last_name', $result['payload']['employee']);
    }

    #[Test]
    public function employee_number_with_different_email_is_rejected(): void
    {
        Employee::query()->create([
            'employee_number' => '25-TEST003',
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'email' => 'original@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
            'is_hybrid' => false,
            'is_confidential' => false,
            'country' => 'Philippines',
        ]);

        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $seenEmails = [];

        $result = $mapper->mapRow(
            [
                'employee_number' => '25-TEST003',
                'email' => 'different@example.com',
            ],
            6,
            $seenNumbers,
            $seenEmails,
            true,
        );

        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['payload']);
        $this->assertTrue(
            collect($result['errors'])->contains(
                fn (string $error) => str_contains($error, 'different email')
            )
        );
    }

    #[Test]
    public function email_on_another_employee_is_rejected(): void
    {
        Employee::query()->create([
            'employee_number' => '25-TEST004',
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'email' => 'taken@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
            'is_hybrid' => false,
            'is_confidential' => false,
            'country' => 'Philippines',
        ]);

        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $seenEmails = [];

        $result = $mapper->mapRow(
            [
                'employee_number' => '25-TEST005',
                'email' => 'taken@example.com',
            ],
            7,
            $seenNumbers,
            $seenEmails,
            true,
        );

        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['payload']);
        $this->assertTrue(
            collect($result['errors'])->contains(
                fn (string $error) => str_contains($error, 'Email already exists')
            )
        );
    }

    #[Test]
    public function assignment_upload_switches_main_campus_payload(): void
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
            'employee_number' => 'ASN-UPLOAD-1',
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'email' => 'ana.asn@example.com',
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
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $antipolo->campus_id,
            'biometric_id' => '2',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $result = $mapper->mapAssignmentUploadRow(
            [
                'employee_number' => 'ASN-UPLOAD-1',
                'campus_code' => 'UA',
            ],
            3,
            $seenNumbers,
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame($antipolo->campus_id, $result['payload']['campus_id']);
        $this->assertSame('CA', $result['payload']['preview']['current_campus_code']);
        $this->assertSame('UA', $result['payload']['preview']['campus_code']);
    }

    #[Test]
    public function assignment_upload_rejects_campus_not_assigned_to_employee(): void
    {
        $cainta = Campus::query()->create([
            'campus_code' => 'CA',
            'campus_name' => 'ICCT Colleges Cainta Main Campus',
            'is_active' => true,
        ]);
        Campus::query()->create([
            'campus_code' => 'TA',
            'campus_name' => 'ICCT Colleges Taytay Campus',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'ASN-UPLOAD-2',
            'first_name' => 'Ben',
            'last_name' => 'Reyes',
            'email' => 'ben.asn@example.com',
            'phone' => '09171234567',
            'campus_id' => $cainta->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $cainta->campus_id,
            'biometric_id' => '9',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $mapper = new EmployeeUploadRowMapper;
        $seenNumbers = [];
        $result = $mapper->mapAssignmentUploadRow(
            [
                'employee_number' => 'ASN-UPLOAD-2',
                'campus_code' => 'TA',
            ],
            4,
            $seenNumbers,
        );

        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['payload']);
        $this->assertTrue(
            collect($result['errors'])->contains(
                fn (string $error) => str_contains($error, 'is not assigned')
            )
        );
    }
}
