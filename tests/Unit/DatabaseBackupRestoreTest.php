<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupRestoreTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/database-restore-'.uniqid('', true).'.sqlite');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->artisan('migrate', ['--force' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->databasePath)) {
            File::delete($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_restore_sqlite_from_file_roundtrip(): void
    {
        $this->assertGreaterThan(0, User::query()->count());

        $service = app(DatabaseBackupService::class);
        $backup = $service->create();

        File::delete($this->databasePath);
        File::put($this->databasePath, '');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $result = $service->restoreFromSqlFile($backup['path']);

        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertGreaterThan(0, User::query()->count());
        $this->assertFileExists(storage_path('app/backups/'.$result['safety_backup']));

        File::delete($backup['path']);
    }
}
