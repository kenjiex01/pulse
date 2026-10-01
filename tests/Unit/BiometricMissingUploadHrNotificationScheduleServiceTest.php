<?php

namespace Tests\Unit;

use App\Services\BiometricMissingUploadHrNotificationScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BiometricMissingUploadHrNotificationScheduleServiceTest extends TestCase
{
    private BiometricMissingUploadHrNotificationScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BiometricMissingUploadHrNotificationScheduleService::class);
        $this->deleteMarker();
    }

    protected function tearDown(): void
    {
        $this->deleteMarker();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_should_not_run_before_five_pm_manila(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 16:59:00', 'Asia/Manila'));

        $this->assertFalse($this->service->shouldRunNow());
    }

    public function test_should_run_at_five_pm_manila_when_not_yet_sent_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 17:00:00', 'Asia/Manila'));

        $this->assertTrue($this->service->shouldRunNow());
    }

    public function test_should_not_run_again_after_marker_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 18:00:00', 'Asia/Manila'));

        $this->service->markRanToday(Carbon::now('Asia/Manila'), [
            'sent' => true,
            'missing_campus_count' => 2,
            'active_campus_count' => 14,
        ]);

        $this->assertTrue($this->service->hasRunToday());
        $this->assertFalse($this->service->shouldRunNow());
    }

    private function deleteMarker(): void
    {
        $path = storage_path('app/.biometric-missing-upload-hr-notification-last-run.json');

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
