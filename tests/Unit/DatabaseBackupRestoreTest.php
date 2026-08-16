<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
        $service->finalizeRestoredDatabase();

        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertGreaterThan(0, User::query()->count());
        $this->assertFileExists(storage_path('app/backups/'.$result['safety_backup']));

        File::delete($backup['path']);
    }

    public function test_downloadable_sql_dump_omits_module_catalog_tables(): void
    {
        $service = app(DatabaseBackupService::class);
        $backup = $service->create();

        $sql = File::get($backup['path']);

        $this->assertStringNotContainsString('INSERT INTO "tbl_modules"', $sql);
        $this->assertStringNotContainsString('INSERT INTO tbl_modules', $sql);
        $this->assertStringNotContainsString('INSERT INTO "sys_sub_modules"', $sql);
        $this->assertStringNotContainsString('INSERT INTO "tbl_role_modules"', $sql);

        File::delete($backup['path']);
    }

    public function test_finalize_recreates_module_catalog_tables_when_missing_after_restore(): void
    {
        $service = app(DatabaseBackupService::class);

        foreach ([
            'tbl_role_sub_modules',
            'tbl_role_modules',
            'sys_sub_modules',
            'tbl_modules',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $service->finalizeRestoredDatabase();

        $this->assertTrue(Schema::hasTable('tbl_modules'));
        $this->assertTrue(Schema::hasTable('sys_sub_modules'));
        $this->assertGreaterThan(0, DB::table('tbl_modules')->count());
        $this->assertGreaterThan(0, DB::table('sys_sub_modules')->count());
    }

    public function test_finalize_seeds_report_catalog_when_tables_are_empty(): void
    {
        DB::table('lu_report_classifications')->delete();
        DB::table('tbl_reports')->delete();
        DB::table('tbl_report_groups')->delete();

        app(DatabaseBackupService::class)->ensureReportCatalogIfMissing();

        $this->assertGreaterThan(0, DB::table('lu_report_classifications')->count());
        $this->assertGreaterThan(0, DB::table('tbl_reports')->count());
    }
}
