<?php

namespace Tests\Unit;

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupServiceGzipTest extends TestCase
{
    public function test_create_gzipped_backup_produces_valid_gzip_file(): void
    {
        $databasePath = database_path('database.sqlite');

        if (! File::exists($databasePath)) {
            File::put($databasePath, '');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $service = app(DatabaseBackupService::class);
        $backup = $service->createGzipped();

        $this->assertFileExists($backup['path']);
        $this->assertFileExists($backup['gz_path']);
        $this->assertStringEndsWith('.sql', $backup['filename']);
        $this->assertStringEndsWith('.sql.gz', $backup['gz_filename']);
        $this->assertMatchesRegularExpression('/^pulse-db-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-[0-9a-f.]+\.sql$/', $backup['filename']);

        $decoded = gzdecode(File::get($backup['gz_path']));
        $this->assertNotFalse($decoded);
        $this->assertSame(File::get($backup['path']), $decoded);

        File::delete($backup['path']);
        File::delete($backup['gz_path']);
    }
}
