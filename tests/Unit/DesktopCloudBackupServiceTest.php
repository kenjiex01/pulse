<?php

namespace Tests\Unit;

use App\Services\DesktopCloudBackupService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DesktopCloudBackupServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (File::exists(storage_path('app/.desktop-cloud-backup-date'))) {
            File::delete(storage_path('app/.desktop-cloud-backup-date'));
        }

        parent::tearDown();
    }

    public function test_should_not_run_before_scheduled_time(): void
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

        Carbon::setTestNow(Carbon::parse('2026-07-16 09:30:00', 'Asia/Manila'));

        $service = app(DesktopCloudBackupService::class);

        $this->assertFalse($service->shouldRunNow());
    }

    public function test_should_run_after_scheduled_time_when_not_uploaded_today(): void
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

        Carbon::setTestNow(Carbon::parse('2026-07-16 10:05:00', 'Asia/Manila'));

        $service = app(DesktopCloudBackupService::class);

        $this->assertFalse($service->hasUploadedToday());
        $this->assertTrue($service->shouldRunNow());
    }

    public function test_should_not_run_after_scheduled_time_when_already_uploaded_today(): void
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

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/.desktop-cloud-backup-date'), '2026-07-16');

        Carbon::setTestNow(Carbon::parse('2026-07-16 14:00:00', 'Asia/Manila'));

        $service = app(DesktopCloudBackupService::class);

        $this->assertTrue($service->hasUploadedToday());
        $this->assertFalse($service->shouldRunNow());
    }

    public function test_upload_path_uses_year_and_month_folders(): void
    {
        config([
            'backup.cloud.s3_prefix' => 'payroll-backups',
            'backup.cloud.timezone' => 'Asia/Manila',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Manila'));

        $service = app(DesktopCloudBackupService::class);

        $this->assertSame(
            'payroll-backups/2026/08/pulse-db-2026-08-12-10-00-00.sql.gz',
            $service->uploadPath('pulse-db-2026-08-12-10-00-00.sql.gz'),
        );
    }

    public function test_cloud_filename_includes_desktop_name(): void
    {
        config([
            'backup.cloud.s3_prefix' => 'payroll-backups',
            'backup.cloud.timezone' => 'Asia/Manila',
        ]);

        $service = app(DesktopCloudBackupService::class);
        $desktop = $service->desktopName();

        $this->assertNotSame('', $desktop);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $desktop);

        $this->assertSame(
            'pulse-db-'.$desktop.'-2026-07-16-10-00-00.sql.gz',
            $service->cloudFilename('pulse-db-2026-07-16-10-00-00.sql.gz'),
        );

        Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00', 'Asia/Manila'));

        $this->assertSame(
            'payroll-backups/2026/07/pulse-db-'.$desktop.'-2026-07-16-10-00-00.sql.gz',
            $service->uploadPath($service->cloudFilename('pulse-db-2026-07-16-10-00-00.sql.gz')),
        );
    }

    public function test_clear_today_marker_removes_date_and_log_files(): void
    {
        config([
            'backup.cloud.timezone' => 'Asia/Manila',
        ]);

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/.desktop-cloud-backup-date'), '2026-07-16');
        File::put(storage_path('app/.desktop-cloud-backup-last.json'), json_encode([
            'date' => '2026-07-16',
            'uploaded_at' => '2026-07-16T21:08:49+08:00',
            's3_key' => 'payroll-backups/test.sql.gz',
        ]));

        Carbon::setTestNow(Carbon::parse('2026-07-16 23:30:00', 'Asia/Manila'));

        $service = app(DesktopCloudBackupService::class);

        $this->assertTrue($service->hasUploadedToday());
        $this->assertTrue($service->clearTodayMarker());
        $this->assertFalse($service->hasUploadedToday());
        $this->assertFalse(File::exists(storage_path('app/.desktop-cloud-backup-date')));
        $this->assertFalse(File::exists(storage_path('app/.desktop-cloud-backup-last.json')));
    }
}
