<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EmployeeUploadTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_authenticated_user_downloads_master_file_template_as_xlsx(): void
    {
        $response = $this->actingAs(User::query()->firstOrFail())
            ->get(route('employees.upload.template', ['type' => 'master-file']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('employee_upload_template.xlsx', $disposition);
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_salary_template_download_filters_by_category_and_puts_name_first(): void
    {
        $this->makeEmployee('DL-STAFF', 'Ana', 'Liza', 'Reyes', EmployeeEmploymentInformation::TYPE_STAFF);
        $this->makeEmployee('DL-FAC', 'Juan', 'Miguel', 'Santos', EmployeeEmploymentInformation::TYPE_FACULTY);

        $response = $this->actingAs(User::query()->firstOrFail())
            ->get(route('employees.upload.template', [
                'type' => 'employee-salary',
                'category' => EmployeeEmploymentInformation::TYPE_STAFF,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('employee_salary_upload_template.xlsx', (string) $response->headers->get('content-disposition'));

        $temp = tempnam(sys_get_temp_dir(), 'sal-dl').'.xlsx';
        file_put_contents($temp, $response->streamedContent());
        $sheet = IOFactory::load($temp)->getActiveSheet()->toArray(null, true, true, false);
        unlink($temp);

        $this->assertSame('employee_name', $sheet[0][0] ?? null);
        $this->assertSame('Employee Name (Last Name, First Name Middle Name)', $sheet[1][0] ?? null);
        $this->assertSame('Reyes, Ana Liza', $sheet[3][0] ?? null);
        $this->assertSame('DL-STAFF', $sheet[3][1] ?? null);
        $this->assertNotContains('DL-FAC', array_column($sheet, 1));
    }

    public function test_guest_is_redirected_to_login_not_html_template(): void
    {
        $response = $this->get(route('employees.upload.template', ['type' => 'master-file']));

        $response->assertRedirect(route('login'));
    }

    private function makeEmployee(string $number, string $first, string $middle, string $last, string $userType): void
    {
        $employee = Employee::query()->create([
            'employee_number' => $number,
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'email' => strtolower($number).'@example.com',
            'phone' => '09170000011',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        $employee->employmentInformations()->create([
            'user_type' => $userType,
            'sort_order' => 1,
        ]);
    }
}
