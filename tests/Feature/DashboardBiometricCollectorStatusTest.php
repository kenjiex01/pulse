<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\RawTimekeepingTransaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardBiometricCollectorStatusTest extends TestCase
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

    public function test_dashboard_shows_collected_and_missing_campuses_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 09:00:00', config('app.timezone')));

        $today = now()->format('Ymd');
        $user = User::query()->firstOrFail();

        $cainta = Campus::query()->firstOrCreate(
            ['campus_code' => 'CA'],
            ['campus_name' => 'ICCT Colleges Cainta Main Campus', 'is_active' => true],
        );

        $sanMateo = Campus::query()->firstOrCreate(
            ['campus_code' => 'SA'],
            ['campus_name' => 'ICCT Colleges San Mateo Campus', 'is_active' => true],
        );

        $sumulong = Campus::query()->firstOrCreate(
            ['campus_code' => 'SU'],
            ['campus_name' => 'ICCT Colleges Sumulong Campus', 'is_active' => true],
        );

        Storage::disk('backup-s3')->put(
            'biometric_logs/'.now()->format('Y/m').'/Cainta-Main-Campus/Cainta-Main-Campus_'.$today.'103045.json.gzip',
            'test',
        );

        Storage::disk('backup-s3')->put(
            'biometric_logs/'.now()->format('Y/m').'/Sumulong/Sumulong_20260904103423.json.gzip',
            'test',
        );

        RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => now()->subDay(),
            'dt_to' => now()->subDay(),
            'uploaded_by_id' => $user->id,
            'dt_uploaded' => now()->subDays(2),
            'batch_no' => 9001,
            'filename' => 'biometric_logs/2026/09/Cainta-Main-Campus/Cainta-Main-Campus_'.$today.'103045.json.gzip',
            'campus_id' => $cainta->campus_id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Biometric Collector — S3 status')
            ->assertSee('ICCT Colleges Cainta Main Campus')
            ->assertSee('ICCT Colleges San Mateo Campus')
            ->assertSee('Collected today')
            ->assertSee('No upload today')
            ->assertSee('Cainta-Main-Campus')
            ->assertSee('Last S3 upload (month)')
            ->assertSee('Last pulled into People360');

        $response->assertSee('ICCT Colleges Sumulong Campus')
            ->assertSee('Sep 4, 2026 10:34 AM');
    }
}
