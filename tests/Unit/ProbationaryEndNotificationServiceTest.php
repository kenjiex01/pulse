<?php

namespace Tests\Unit;

use App\Mail\ProbationaryEndNotificationMail;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\HrProbationaryEndNotificationLog;
use App\Models\HrSetupSetting;
use App\Services\ProbationaryEndNotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProbationaryEndNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        config(['mail.default' => 'array']);
    }

    public function test_sends_hr_email_only_once_per_offset_and_employee(): void
    {
        Mail::fake();

        $today = Carbon::parse('2026-10-01', 'Asia/Manila')->startOfDay();
        Carbon::setTestNow($today);

        HrSetupSetting::settings()->update([
            'hr_email' => 'hr@example.com',
            'probationary_end_notification_days' => '10, 5, 1',
            'probationary_end_email_subject' => 'Reminder — {{employee_full_name}}',
            'probationary_end_email_body' => 'Ends {{probationary_end_date}} ({{days_before_end}} days notice).',
        ]);

        $employee = $this->createProbationaryEmployee(
            endDate: '2026-10-11',
            email: 'employee@example.com',
        );

        $service = app(ProbationaryEndNotificationService::class);

        $first = $service->sendDueNotifications($today);
        $this->assertSame(1, $first['employees_checked']);
        $this->assertSame(0, $first['employee_emails_sent']);
        $this->assertSame(1, $first['hr_emails_sent']);

        $second = $service->sendDueNotifications($today);
        $this->assertSame(1, $second['employees_checked']);
        $this->assertSame(0, $second['hr_emails_sent']);
        $this->assertSame(1, $second['skipped_already_sent']);

        Mail::assertSent(ProbationaryEndNotificationMail::class, 1);

        $this->assertTrue(HrProbationaryEndNotificationLog::wasSent(
            (int) $employee->employee_id,
            '2026-10-11',
            10,
            HrProbationaryEndNotificationLog::RECIPIENT_HR,
        ));

        Carbon::setTestNow();
    }

    public function test_sends_separate_hr_emails_for_each_day_offset(): void
    {
        Mail::fake();

        HrSetupSetting::settings()->update([
            'hr_email' => 'hr@example.com',
            'probationary_end_notification_days' => '10, 5',
            'probationary_end_email_subject' => 'Subject',
            'probationary_end_email_body' => 'Body',
        ]);

        $employee = $this->createProbationaryEmployee(
            endDate: '2026-10-11',
            email: 'employee@example.com',
        );

        $service = app(ProbationaryEndNotificationService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-01', 'Asia/Manila'));
        $service->sendDueNotifications(Carbon::parse('2026-10-01', 'Asia/Manila')->startOfDay());

        Carbon::setTestNow(Carbon::parse('2026-10-06', 'Asia/Manila'));
        $service->sendDueNotifications(Carbon::parse('2026-10-06', 'Asia/Manila')->startOfDay());

        Mail::assertSent(ProbationaryEndNotificationMail::class, 2);

        $this->assertTrue(HrProbationaryEndNotificationLog::wasSent(
            (int) $employee->employee_id,
            '2026-10-11',
            10,
            HrProbationaryEndNotificationLog::RECIPIENT_HR,
        ));
        $this->assertTrue(HrProbationaryEndNotificationLog::wasSent(
            (int) $employee->employee_id,
            '2026-10-11',
            5,
            HrProbationaryEndNotificationLog::RECIPIENT_HR,
        ));

        Carbon::setTestNow();
    }

    public function test_does_not_send_when_hr_email_missing(): void
    {
        Mail::fake();

        HrSetupSetting::settings()->update([
            'hr_email' => null,
            'probationary_end_notification_days' => '10',
            'probationary_end_email_subject' => 'Subject',
            'probationary_end_email_body' => 'Body',
        ]);

        $this->createProbationaryEmployee(endDate: '2026-10-11', email: 'employee@example.com');

        Carbon::setTestNow(Carbon::parse('2026-10-01', 'Asia/Manila'));
        $stats = app(ProbationaryEndNotificationService::class)->sendDueNotifications();

        $this->assertSame(0, $stats['hr_emails_sent']);
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    private function createProbationaryEmployee(string $endDate, string $email): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PROB-'.uniqid(),
            'first_name' => 'Prob',
            'last_name' => 'Employee',
            'email' => $email,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'employment_type' => 'Probationary',
            'date_effective_from' => '2026-04-01',
            'probationary_end_date' => $endDate,
            'hire_date' => '2026-04-01',
            'sort_order' => 0,
        ]);

        return $employee->fresh(['employmentInformations']);
    }
}
