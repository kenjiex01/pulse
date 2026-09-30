<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentNteCase;
use App\Models\CompanyDocumentSendLog;
use App\Models\Employee;
use App\Services\CompanyDocumentNteSyncService;
use App\Services\SkolarisApiService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CompanyDocumentNteSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config(['skolaris.pulse_api_key' => 'skp_test_key']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_matches_skolaris_response_by_template_code_and_employee(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'NTE-SYNC-001',
            'first_name' => 'Leo',
            'last_name' => 'Mendoza',
            'email' => 'leo@example.com',
        ]);

        $form = CompanyDocumentForm::query()->create([
            'code' => 'hr_notice_to_explain_test',
            'name' => 'NTE Test',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'expects_web_nte_response' => true,
            'nte_response_days' => 7,
            'is_active' => true,
            'version' => 1,
        ]);

        $sentAt = now()->subDays(2);
        $sendLog = CompanyDocumentSendLog::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'submission_id' => null,
            'sent_by_user_id' => null,
            'sent_at' => $sentAt,
        ]);

        $case = CompanyDocumentNteCase::query()->create([
            'company_document_send_log_id' => $sendLog->company_document_send_log_id,
            'company_document_form_id' => $form->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'sent_at' => $sentAt,
            'due_at' => $sentAt->copy()->addDays(7)->endOfDay(),
            'status' => CompanyDocumentNteCase::STATUS_PENDING,
        ]);

        $submittedAt = $sentAt->copy()->addDay()->toIso8601String();

        $mock = Mockery::mock(SkolarisApiService::class);
        $mock->shouldReceive('pullCompanyDocumentNteResponsesSync')
            ->once()
            ->with([
                'template_code' => 'hr_notice_to_explain_test',
                'per_page' => 100,
            ])
            ->andReturn([
                'data' => [[
                    'template_code' => 'hr_notice_to_explain_test',
                    'employee_number' => 'NTE-SYNC-001',
                    'submitted_at' => $submittedAt,
                    'response_id' => 42,
                ]],
                'synced_at' => now()->toIso8601String(),
                'pagination' => null,
            ]);

        $this->app->instance(SkolarisApiService::class, $mock);

        $result = app(CompanyDocumentNteSyncService::class)->syncFromSkolaris($form);

        $this->assertSame(1, $result['matched']);
        $this->assertSame(CompanyDocumentNteCase::STATUS_RECEIVED, $case->fresh()->status);
        $this->assertSame(42, $case->fresh()->skolaris_request_id);
    }
}
