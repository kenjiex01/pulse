<?php

namespace Tests\Unit;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\EmployeeCredentialsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCredentialsReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_selected_employees_without_document_type_columns(): void
    {
        $user = User::query()->create([
            'name' => 'Report Tester',
            'email' => 'credentials-report@example.com',
            'password' => bcrypt('password'),
        ]);

        $tin = DocumentType::query()->create([
            'type_code' => 'TIN_TEST',
            'type_name' => 'TIN ID',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $withTin = Employee::query()->create([
            'employee_number' => 'CR-002',
            'first_name' => 'Ana',
            'middle_name' => 'B',
            'last_name' => 'Santos',
            'email' => 'ana.santos.cred@example.com',
            'is_active' => true,
        ]);
        $without = Employee::query()->create([
            'employee_number' => 'CR-001',
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'email' => 'ben.cruz.cred@example.com',
            'is_active' => true,
        ]);

        EmployeeCredential::query()->create([
            'employee_id' => $withTin->employee_id,
            'document_type_id' => $tin->document_type_id,
            'description' => 'TIN',
            'original_filename' => 'tin.pdf',
            'stored_path' => 'employee-credentials/tin.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        $service = new EmployeeCredentialsReportService();
        $result = $service->generate(
            new Report(['title' => 'Employee']),
            ['employee_ids' => [$withTin->employee_id, $without->employee_id]],
            $user,
        );

        $this->assertContains('Employee No.', $result->headers);
        $this->assertContains('Employee Name', $result->headers);
        $this->assertContains('Assignments', $result->headers);
        $this->assertContains('Shift Code', $result->headers);
        $this->assertContains('Loans', $result->headers);
        $this->assertNotContains('TIN ID', $result->headers);
        $this->assertNotContains('SSS ID', $result->headers);
        $this->assertSame('Loans', $result->headers[array_key_last($result->headers)]);
        $this->assertCount(2, $result->rows);
        $this->assertSame('CR-001', $result->rows[0][0]);
        $this->assertSame('Cruz, Ben', $result->rows[0][1]);
        $this->assertSame('CR-002', $result->rows[1][0]);
        $this->assertSame('Santos, Ana B', $result->rows[1][1]);
        $this->assertCount(count($result->headers), $result->rows[0]);
        $this->assertCount(count($result->headers), $result->rows[1]);
        $this->assertStringNotContainsString('credential column', (string) ($result->meta['filter_summary'] ?? ''));
    }

    public function test_salary_columns_tag_user_type_only_when_hybrid(): void
    {
        $user = User::query()->create([
            'name' => 'Hybrid Tester',
            'email' => 'hybrid-report@example.com',
            'password' => bcrypt('password'),
        ]);

        $hybrid = Employee::query()->create([
            'employee_number' => 'HYB-001',
            'first_name' => 'Lina',
            'last_name' => 'Reyes',
            'email' => 'lina.reyes.hyb@example.com',
            'is_active' => true,
            'is_hybrid' => true,
        ]);
        $staffOnly = Employee::query()->create([
            'employee_number' => 'STF-001',
            'first_name' => 'Mark',
            'last_name' => 'Cruz',
            'email' => 'mark.cruz.stf@example.com',
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        $facultyInfo = \App\Models\EmployeeEmploymentInformation::query()->create([
            'employee_id' => $hybrid->employee_id,
            'user_type' => \App\Models\EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 0,
        ]);
        $staffInfo = \App\Models\EmployeeEmploymentInformation::query()->create([
            'employee_id' => $hybrid->employee_id,
            'user_type' => \App\Models\EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);
        $plainStaff = \App\Models\EmployeeEmploymentInformation::query()->create([
            'employee_id' => $staffOnly->employee_id,
            'user_type' => \App\Models\EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 0,
        ]);

        \App\Models\EmployeeSalary::query()->create([
            'employment_info_id' => $facultyInfo->employment_info_id,
            'days_per_period' => 11,
            'hours_per_day' => 8,
        ]);
        \App\Models\EmployeeSalary::query()->create([
            'employment_info_id' => $staffInfo->employment_info_id,
            'days_per_period' => 13,
            'hours_per_day' => 8,
        ]);
        \App\Models\EmployeeSalary::query()->create([
            'employment_info_id' => $plainStaff->employment_info_id,
            'days_per_period' => 13,
            'hours_per_day' => 8,
        ]);

        $service = new EmployeeCredentialsReportService();
        $result = $service->generate(
            new Report(['title' => 'Employee']),
            ['employee_ids' => [$hybrid->employee_id, $staffOnly->employee_id]],
            $user,
        );

        $daysIndex = array_search('Days / Period', $result->headers, true);
        $this->assertNotFalse($daysIndex);
        $this->assertSame('13', $result->rows[0][$daysIndex]);
        $this->assertSame('11 (Faculty) · 13 (Staff)', $result->rows[1][$daysIndex]);
    }
}
