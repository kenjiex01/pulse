<?php

namespace Tests\Feature;

use App\Models\BiometricS3PulledFile;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\User;
use App\Services\BiometricS3AutoPullService;
use App\Services\BiometricS3PullSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BiometricS3AutoPullTest extends TestCase
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
            'biometric_logs.s3.bucket' => 'test-bucket',
            'filesystems.disks.backup-s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-s3-auto'),
                'throw' => true,
            ],
        ]);

        Storage::fake('backup-s3');

        $lock = storage_path('app/settings/biometric-s3-auto-pull.lock');

        if (is_file($lock)) {
            unlink($lock);
        }
    }

    public function test_user_can_enable_auto_pull_from_time_logs(): void
    {
        $user = User::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('timekeeping.time-logs.s3-auto-pull'), [
                'enabled' => '1',
                'tab' => 'time-in-out',
            ])
            ->assertRedirect(route('timekeeping.time-logs.tab', ['tab' => 'time-in-out']))
            ->assertSessionHas('success');

        $this->assertTrue(app(BiometricS3PullSettings::class)->isAutoPullEnabled());
    }

    public function test_auto_pull_becomes_due_again_after_the_interval(): void
    {
        $settings = app(BiometricS3PullSettings::class);
        $settings->setAutoPullEnabled(true);
        $settings->markRunFinished();

        $this->assertFalse($settings->shouldRunNow());

        $path = storage_path('app/settings/biometric-s3-auto-pull.json');
        file_put_contents($path, json_encode([
            'enabled' => true,
            'last_run_at' => now()->subMinutes(10)->toIso8601String(),
        ]));

        $this->assertTrue($settings->shouldRunNow());
    }

    public function test_auto_pull_tick_works_from_any_authenticated_page(): void
    {
        $user = User::query()->firstOrFail();
        app(BiometricS3PullSettings::class)->setAutoPullEnabled(true);

        $this->actingAs($user)
            ->post(route('biometric-s3-auto-pull.tick'))
            ->assertNoContent();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_auto_pull_imports_only_new_s3_files(): void
    {
        $user = User::query()->firstOrFail();
        $settings = app(BiometricS3PullSettings::class);
        $settings->setAutoPullEnabled(true);

        $campus = Campus::query()->create([
            'campus_code' => 'AP',
            'campus_name' => 'ICCT Colleges Antipolo Campus',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'AUTO-PULL-001',
            'first_name' => 'Auto',
            'last_name' => 'Pull',
            'email' => 'auto.pull@example.com',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $campus->campus_id,
            'biometric_id' => '88',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $payload = [
            'kind' => 'attendance',
            'campus_code' => 'AP',
            'logs' => [[
                'user_id' => '88',
                'punched_at' => now()->format('Y-m-d H:i:s'),
                'punch_state' => 'CheckIn',
            ]],
        ];

        $oldKey = 'biometric_logs/'.now()->format('Y/m').'/Antipolo/Antipolo_old.json.gzip';
        $newKey = 'biometric_logs/'.now()->format('Y/m').'/Antipolo/Antipolo_new.json.gzip';

        Storage::disk('backup-s3')->put($oldKey, gzencode(json_encode($payload)));
        Storage::disk('backup-s3')->put($newKey, gzencode(json_encode($payload)));

        BiometricS3PulledFile::query()->create([
            's3_key' => $oldKey,
            'pulled_at' => now()->subHour(),
            'pulled_by_user_id' => $user->id,
            'status' => BiometricS3PulledFile::STATUS_IMPORTED,
        ]);

        $summary = app(BiometricS3AutoPullService::class)->runAutoPull($user);

        $this->assertNotNull($summary);
        $this->assertSame(1, $summary['files_imported']);
        $this->assertGreaterThanOrEqual(1, $summary['files_already_pulled']);
        $this->assertDatabaseHas('tbl_biometric_s3_pulled_files', ['s3_key' => $newKey]);
    }
}
