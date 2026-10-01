<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\HrProbationaryEndNotificationLog;
use App\Models\HrSetupSetting;
use App\Models\User;
use App\Services\ProbationaryEndNotificationScheduleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_hr_setup_page_loads(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.setup.index'))
            ->assertOk()
            ->assertSee('HR Setup')
            ->assertSee('HR email')
            ->assertSee('name="hr_email"', false)
            ->assertSee('Notification days before probationary ends')
            ->assertSee('name="probationary_end_notification_days"', false)
            ->assertSee('Employee full name')
            ->assertSee('data-hr-setup-insert-tag="employee_full_name"', false)
            ->assertSee('Notification history')
            ->assertSee('data-hr-setup-tab="history"', false);
    }

    public function test_hr_setup_notification_history_tab_lists_sent_emails(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-HIST-001',
            'first_name' => 'History',
            'last_name' => 'Test',
            'email' => 'history.test@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        HrProbationaryEndNotificationLog::query()->create([
            'employee_id' => $employee->employee_id,
            'probationary_end_date' => '2026-10-15',
            'days_before' => 5,
            'recipient_type' => HrProbationaryEndNotificationLog::RECIPIENT_HR,
            'hr_email_to' => 'hr.notifications@icct.edu.ph',
            'email_subject' => 'Probation ending — History Test',
            'email_body' => 'Probation ends on Oct 15, 2026.',
            'sent_at' => now(),
        ]);

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.setup.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('History Test', false)
            ->assertSee('EMP-HIST-001', false)
            ->assertDontSee('View body', false);
    }

    public function test_hr_setup_saves_hr_email(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.setup.index'))
            ->put(route('hr.setup.update'), [
                'hr_email' => 'hr.notifications@icct.edu.ph',
            ])
            ->assertRedirect(route('hr.setup.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            'hr.notifications@icct.edu.ph',
            HrSetupSetting::settings()->fresh()->hr_email,
        );
    }

    public function test_hr_setup_saves_probationary_notification_days_as_comma_list(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.setup.index'))
            ->put(route('hr.setup.update'), [
                'hr_email' => 'hr.notifications@icct.edu.ph',
                'probationary_end_notification_days' => '30, 7, 14',
                'probationary_end_email_subject' => 'Probation ending — {{employee_full_name}}',
                'probationary_end_email_body' => 'Your probation ends on {{probationary_end_date}}.',
            ])
            ->assertRedirect(route('hr.setup.index'))
            ->assertSessionHas('success');

        $this->assertSame('7, 14, 30', HrSetupSetting::settings()->fresh()->probationary_end_notification_days);
        $this->assertSame([7, 14, 30], HrSetupSetting::probationaryEndNotificationDayList());
    }

    public function test_hr_setup_rejects_invalid_probationary_notification_days(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.setup.index'))
            ->put(route('hr.setup.update'), [
                'probationary_end_notification_days' => '7, abc',
            ])
            ->assertRedirect(route('hr.setup.index'))
            ->assertSessionHasErrors('probationary_end_notification_days');
    }

    public function test_hr_setup_shows_sent_for_today_indicator_when_job_ran(): void
    {
        HrSetupSetting::settings()->update([
            'probationary_end_notification_days' => '1, 5, 10',
        ]);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'hr@example.com',
            'mail.mailers.smtp.password' => 'app-password',
        ]);

        app(ProbationaryEndNotificationScheduleService::class)->markRanToday(null, [
            'employee_emails_sent' => 1,
            'hr_emails_sent' => 1,
            'employees_checked' => 2,
        ]);

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.setup.index'))
            ->assertOk()
            ->assertSee('Sent for today')
            ->assertSee('HR email(s) sent');
    }

    public function test_hr_setup_rejects_invalid_email(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.setup.index'))
            ->put(route('hr.setup.update'), [
                'hr_email' => 'not-an-email',
            ])
            ->assertRedirect(route('hr.setup.index'))
            ->assertSessionHasErrors('hr_email');
    }

    public function test_hr_setup_saves_comma_separated_hr_emails(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.setup.index'))
            ->put(route('hr.setup.update'), [
                'hr_email' => 'HR.One@Example.com, hr.two@example.com , HR.One@Example.com',
            ])
            ->assertRedirect(route('hr.setup.index'))
            ->assertSessionHas('success');

        $this->assertSame(
            'hr.one@example.com, hr.two@example.com',
            HrSetupSetting::settings()->fresh()->hr_email,
        );
        $this->assertSame(
            ['hr.one@example.com', 'hr.two@example.com'],
            HrSetupSetting::hrEmails(),
        );
    }
}
