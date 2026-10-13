<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('employee_upload_template.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_guest_is_redirected_to_login_not_html_template(): void
    {
        $response = $this->get(route('employees.upload.template', ['type' => 'master-file']));

        $response->assertRedirect(route('login'));
    }
}
