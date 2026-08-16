<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\User;
use App\Services\BiometricLogsS3PullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricLogsS3PullServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pull_imports_attendance_json_gzip_matched_by_campus_biometric_id(): void
    {
        config([
            'biometric_logs.s3.disk' => 'backup-s3',
            'biometric_logs.s3.prefix' => 'biometric_logs',
            'biometric_logs.s3.key' => 'test-key',
            'biometric_logs.s3.secret' => 'test-secret',
            'biometric_logs.s3.bucket' => 'test-bucket',
            'filesystems.disks.backup-s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-s3'),
                'throw' => true,
            ],
        ]);

        Storage::fake('backup-s3');

        $user = User::query()->create([
            'name' => 'S3 Pull Tester',
            'email' => 's3-pull@example.com',
            'password' => bcrypt('password'),
        ]);

        $campus = Campus::query()->create([
            'campus_code' => 'CA',
            'campus_name' => 'ICCT Colleges Cainta Main Campus',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'BIO-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'campus_id' => $campus->campus_id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
            'is_hybrid' => false,
        ]);

        EmployeeCampusAssignment::query()->create([
            'employee_id' => $employee->employee_id,
            'campus_id' => $campus->campus_id,
            'biometric_id' => '15',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $payload = [
            'kind' => 'attendance',
            'collector_name' => 'Cainta Main Campus',
            'biometric_name' => 'Cainta-Main-Campus',
            'campus_code' => 'CA',
            'logs' => [
                [
                    'user_id' => '15',
                    'user_name' => 'Juan Dela Cruz',
                    'punched_at' => '2026-08-13 08:01:22',
                    'punch_state' => 'CheckIn',
                ],
                [
                    'user_id' => '15',
                    'user_name' => 'Juan Dela Cruz',
                    'punched_at' => '2026-08-13 17:05:10',
                    'punch_state' => 'CheckOut',
                ],
                [
                    'user_id' => '999',
                    'user_name' => 'Unknown',
                    'punched_at' => '2026-08-13 09:00:00',
                    'punch_state' => 'CheckIn',
                ],
            ],
        ];

        $key = 'biometric_logs/2026/08/Cainta-Main-Campus/Cainta-Main-Campus_20260813103045.json.gzip';
        Storage::disk('backup-s3')->put($key, gzencode(json_encode($payload)));

        $summary = app(BiometricLogsS3PullService::class)->pull(
            user: $user,
            year: 2026,
            month: 8,
            campusId: (int) $campus->campus_id,
            collectorFolder: 'Cainta-Main-Campus',
        );

        $this->assertSame(1, $summary['files_scanned']);
        $this->assertSame(1, $summary['files_imported']);
        $this->assertSame(2, $summary['punches_inserted']);
        $this->assertSame(1, $summary['punches_unmatched']);

        $this->assertDatabaseHas('raw_timekeeping_transactions', [
            'filename' => $key,
            'campus_id' => $campus->campus_id,
            'uploaded_by_id' => $user->id,
        ]);

        $this->assertSame(2, RawTimekeepingInandout::query()->where('employee_id', $employee->employee_id)->count());
        $this->assertTrue(
            RawTimekeepingInandout::query()
                ->where('employee_id', $employee->employee_id)
                ->where('is_in', true)
                ->exists()
        );
        $this->assertTrue(
            RawTimekeepingInandout::query()
                ->where('employee_id', $employee->employee_id)
                ->where('is_in', false)
                ->exists()
        );

        // Second pull skips the same S3 object.
        $second = app(BiometricLogsS3PullService::class)->pull(
            user: $user,
            year: 2026,
            month: 8,
            campusId: (int) $campus->campus_id,
            collectorFolder: 'Cainta-Main-Campus',
        );

        $this->assertSame(1, $second['files_skipped']);
        $this->assertSame(0, $second['files_imported']);
        $this->assertSame(1, RawTimekeepingTransaction::query()->where('filename', $key)->count());
    }

    #[Test]
    public function list_collector_folders_returns_month_directories(): void
    {
        config([
            'biometric_logs.s3.disk' => 'backup-s3',
            'biometric_logs.s3.prefix' => 'biometric_logs',
            'biometric_logs.s3.key' => 'test-key',
            'biometric_logs.s3.secret' => 'test-secret',
            'biometric_logs.s3.bucket' => 'test-bucket',
            'filesystems.disks.backup-s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/backup-s3'),
                'throw' => true,
            ],
        ]);

        Storage::fake('backup-s3');
        Storage::disk('backup-s3')->put(
            'biometric_logs/2026/08/Cainta-Main-Campus/file.json.gzip',
            gzencode('{"kind":"attendance","logs":[]}'),
        );
        Storage::disk('backup-s3')->put(
            'biometric_logs/2026/08/San-Mateo-Gate/file.json.gzip',
            gzencode('{"kind":"attendance","logs":[]}'),
        );

        $folders = app(BiometricLogsS3PullService::class)->listCollectorFolders(2026, 8);

        $this->assertSame(['Cainta-Main-Campus', 'San-Mateo-Gate'], $folders);
    }
}
