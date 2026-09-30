<?php

namespace Tests\Unit;

use App\Mail\TimekeepingMemoMail;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentNteCase;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use App\Services\TimekeepingMemoAttendanceService;
use App\Services\TimekeepingMemoSendService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TimekeepingMemoSendServiceNteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config(['mail.default' => 'array']);
    }

    public function test_send_attaches_single_pdf_when_memo_expects_web_nte_and_opens_case(): void
    {
        Mail::fake();

        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-NTE-001',
            'first_name' => 'Carla',
            'last_name' => 'Dizon',
            'email' => 'carla.dizon@example.com',
        ]);

        $memoForm = $this->createMemoForm('absent_with_web_nte', 'Absent Memo Web NTE', expectsWebNte: true);

        TimekeepingMemoSetup::query()->where('violation_type', 'absent')->update([
            'company_document_form_id' => $memoForm->company_document_form_id,
            'email_subject' => 'Absent memo for {{employee_full_name}}',
            'email_body' => 'Please review the attached memo.',
        ]);

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('normalizeViolationType')->andReturn('absent');
            $mock->shouldReceive('violationDaysForEmployee')->andReturn([
                [
                    'work_date' => '2026-09-05',
                    'time_in' => null,
                    'time_out' => null,
                    'minutes' => 0,
                    'memo_sent' => false,
                ],
            ]);
        });

        app(TimekeepingMemoSendService::class)->sendForEmployee(
            $employee,
            '2026-09-01',
            '2026-09-07',
            'absent',
            null,
            User::query()->firstOrFail(),
        );

        Mail::assertSent(TimekeepingMemoMail::class, function (TimekeepingMemoMail $mail): bool {
            $attachments = $mail->attachments();

            return count($attachments) === 1
                && str_ends_with(strtolower((string) $attachments[0]->as), '.pdf');
        });

        $this->assertDatabaseHas('tbl_company_document_nte_cases', [
            'company_document_form_id' => $memoForm->company_document_form_id,
            'employee_id' => $employee->employee_id,
            'status' => CompanyDocumentNteCase::STATUS_PENDING,
        ]);
    }

    private function createMemoForm(
        string $code,
        string $name,
        bool $expectsWebNte = false,
        bool $isNte = false,
    ): CompanyDocumentForm {
        $form = CompanyDocumentForm::query()->create([
            'code' => $code,
            'name' => $name,
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'requires_nte' => false,
            'expects_web_nte_response' => $expectsWebNte,
            'nte_response_days' => 7,
            'is_nte' => $isNte,
            'is_active' => true,
            'version' => 1,
        ]);

        CompanyDocumentElement::query()->create([
            'company_document_form_id' => $form->company_document_form_id,
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => 'Employee: {{employee_full_name}} · Count: {{violation_count}}',
            'sort_order' => 1,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 16, 'pos_y' => 16],
        ]);

        return $form;
    }
}
