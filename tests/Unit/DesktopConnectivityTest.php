<?php

namespace Tests\Unit;

use App\Services\DesktopCloudBackupService;
use App\Services\DesktopInstallerUpdateService;
use App\Support\DesktopConnectivity;
use Carbon\Carbon;
use Tests\TestCase;

class DesktopConnectivityTest extends TestCase
{
    protected function tearDown(): void
    {
        DesktopConnectivity::fake(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fake_offline_and_online(): void
    {
        DesktopConnectivity::fake(false);
        $this->assertFalse(app(DesktopConnectivity::class)->isOnline());

        DesktopConnectivity::fake(true);
        $this->assertTrue(app(DesktopConnectivity::class)->isOnline());
    }

    public function test_installer_update_skips_s3_when_offline(): void
    {
        config([
            'desktop_updater.enabled' => true,
            'nativephp-internal.running' => true,
            'backup.cloud.bucket' => 'test-bucket',
            'backup.cloud.key' => 'test-key',
            'backup.cloud.secret' => 'test-secret',
        ]);

        DesktopConnectivity::fake(false);

        $service = app(DesktopInstallerUpdateService::class);
        $check = $service->checkIfNeeded(true);

        $this->assertIsArray($check);
        $this->assertFalse($check['available']);
        $this->assertSame('offline', $check['error']);
        $this->assertNull($service->pendingUpdateForUi());
    }

    public function test_cloud_backup_skips_upload_when_offline(): void
    {
        config([
            'backup.cloud.enabled' => true,
            'backup.cloud.key' => 'test-key',
            'backup.cloud.secret' => 'test-secret',
            'backup.cloud.bucket' => 'test-bucket',
            'backup.cloud.schedule_hour' => 10,
            'backup.cloud.schedule_minute' => 0,
            'backup.cloud.timezone' => 'Asia/Manila',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-14 11:00:00', 'Asia/Manila'));
        DesktopConnectivity::fake(false);

        $service = app(DesktopCloudBackupService::class);

        $this->assertTrue($service->shouldRunNow());
        $this->assertNull($service->runBackupAndUpload());
        $this->assertFalse($service->hasUploadedToday());
    }
}
