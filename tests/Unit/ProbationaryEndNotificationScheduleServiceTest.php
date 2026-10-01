<?php

namespace Tests\Unit;

use App\Services\ProbationaryEndNotificationScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProbationaryEndNotificationScheduleServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->clearRunMarkers();
        parent::tearDown();
    }

    private function clearRunMarkers(): void
    {
        foreach ([
            storage_path('app/.probationary-end-notification-last-run.json'),
            storage_path('app/.probationary-end-notification-date'),
        ] as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function test_should_run_on_first_app_use_when_not_yet_ran_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 07:15:00', 'Asia/Manila'));

        $this->clearRunMarkers();

        $service = app(ProbationaryEndNotificationScheduleService::class);

        $this->assertTrue($service->shouldRunNow());
    }

    public function test_should_not_run_again_after_marked_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Manila'));

        $service = app(ProbationaryEndNotificationScheduleService::class);
        $service->markRanToday(null, [
            'employee_emails_sent' => 1,
            'hr_emails_sent' => 1,
            'employees_checked' => 1,
        ]);

        $this->assertFalse($service->shouldRunNow());
    }

    public function test_today_run_status_reflects_last_run(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Manila'));

        $service = app(ProbationaryEndNotificationScheduleService::class);
        $service->markRanToday(null, [
            'employee_emails_sent' => 2,
            'hr_emails_sent' => 2,
            'employees_checked' => 3,
        ]);

        $status = $service->todayRunStatus();

        $this->assertTrue($status['sent_today']);
        $this->assertSame(2, $status['employee_emails_sent']);
        $this->assertSame(2, $status['hr_emails_sent']);
        $this->assertSame(3, $status['employees_checked']);
    }
}
