<?php

namespace Tests\Unit;

use App\Mail\BiometricMissingUploadHrNotificationMail;
use App\Models\Campus;
use App\Models\HrSetupSetting;
use App\Services\BiometricMissingUploadHrNotificationService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BiometricMissingUploadHrNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        config([
            'biometric_logs.s3.disk' => 'backup-s3',
            'biometric_logs.s3.prefix' => 'biometric_logs',
            'biometric_logs.s3.key' => 'test-key',
            'biometric_logs.s3.secret' => 'test-secret',
            'biometric_logs.s3.bucket' => 'skolaris-payroll-backups-prod',
            'biometric_logs.s3.region' => 'ap-southeast-2',
            'filesystems.disks.backup-s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-s3'),
                'throw' => true,
            ],
        ]);

        Storage::fake('backup-s3');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_hr_email_listing_campuses_with_no_upload_today(): void
    {
        Mail::fake();

        Carbon::setTestNow(Carbon::parse('2026-10-01 17:05:00', 'Asia/Manila'));

        HrSetupSetting::settings()->update([
            'hr_email' => 'hr.notifications@icct.edu.ph',
        ]);

        $today = now()->format('Ymd');

        Campus::query()->firstOrCreate(
            ['campus_code' => 'CA'],
            ['campus_name' => 'ICCT Colleges Cainta Main Campus', 'is_active' => true],
        );

        Campus::query()->firstOrCreate(
            ['campus_code' => 'SA'],
            ['campus_name' => 'ICCT Colleges San Mateo Campus', 'is_active' => true],
        );

        Storage::disk('backup-s3')->put(
            'biometric_logs/'.now()->format('Y/m').'/Cainta-Main-Campus/Cainta-Main-Campus_'.$today.'103045.json.gzip',
            'test',
        );

        $result = app(BiometricMissingUploadHrNotificationService::class)->sendDailyReport();

        $this->assertTrue($result['sent']);
        $this->assertGreaterThanOrEqual(1, $result['missing_campus_count']);

        Mail::assertSent(BiometricMissingUploadHrNotificationMail::class, function (BiometricMissingUploadHrNotificationMail $mail): bool {
            return $mail->hasTo('hr.notifications@icct.edu.ph');
        });
    }

    public function test_skips_when_hr_email_not_configured(): void
    {
        Mail::fake();

        HrSetupSetting::settings()->update(['hr_email' => null]);

        $result = app(BiometricMissingUploadHrNotificationService::class)->sendDailyReport();

        $this->assertFalse($result['sent']);
        $this->assertSame('hr_email_not_configured', $result['skipped_reason']);
        Mail::assertNothingSent();
    }
}
