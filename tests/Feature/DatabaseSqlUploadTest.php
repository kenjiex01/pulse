<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseSqlUploadTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/database-upload-'.uniqid('', true).'.sqlite');

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

    public function test_admin_can_restore_database_from_uploaded_sql_file(): void
    {
        $user = User::query()->firstOrFail();

        $backupService = app(DatabaseBackupService::class);
        $backup = $backupService->createGzipped();

        $response = $this->actingAs($user)->post(route('database.upload-sql'), [
            'sql_file' => UploadedFile::fake()->createWithContent('restore.sql', File::get($backup['path'])),
            'confirm_replace' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/database?', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('restore=ok', (string) $response->headers->get('Location'));

        $this->assertTrue(File::exists($this->databasePath));
        $this->assertGreaterThan(0, File::size($this->databasePath));
        $this->assertGreaterThan(0, User::query()->count());

        File::delete($backup['path']);
        File::delete($backup['gz_path']);
    }

    public function test_invalid_sql_file_does_not_wipe_the_database(): void
    {
        $user = User::query()->firstOrFail();
        $usersBefore = User::query()->count();

        // A MySQL-style dump / garbage file must not leave the live DB empty.
        $response = $this->actingAs($user)->post(route('database.upload-sql'), [
            'sql_file' => UploadedFile::fake()->createWithContent(
                'bad.sql',
                "-- MySQL dump\nLOCK TABLES `foo` WRITE;\nINSERT INTO `foo` VALUES (1);\nUNLOCK TABLES;\n",
            ),
            'confirm_replace' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('restore=fail', (string) $response->headers->get('Location'));

        // DB is still intact — no cascading 500 on the next page load.
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->assertSame($usersBefore, User::query()->count());
    }

    public function test_sql_upload_requires_confirmation(): void
    {
        $user = User::query()->firstOrFail();

        $response = $this->actingAs($user)->post(route('database.upload-sql'), [
            'sql_file' => UploadedFile::fake()->create('restore.sql', 10, 'application/sql'),
        ]);

        $response->assertSessionHasErrors('confirm_replace');
    }
}
