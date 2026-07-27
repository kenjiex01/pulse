<?php

namespace App\Console\Commands;

use App\Services\DesktopCloudBackupService;
use Illuminate\Console\Command;

class UploadDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:upload-cloud {--force : Upload even if today\'s backup already exists or it is before the scheduled time}';

    protected $description = 'Dump the database to SQL, gzip it, and upload to the configured S3 backup bucket';

    public function handle(DesktopCloudBackupService $backupService): int
    {
        if ($this->option('force')) {
            $result = $backupService->runBackupAndUpload(force: true);

            if ($result === null) {
                $this->error('Cloud backup failed. Check logs for details.');

                return self::FAILURE;
            }

            $this->info('Uploaded '.$result['filename'].' to '.$result['s3_key']);

            return self::SUCCESS;
        }

        if (! $backupService->shouldRunNow()) {
            $this->info('No cloud backup needed right now.');

            return self::SUCCESS;
        }

        $result = $backupService->runBackupAndUpload();

        if ($result === null) {
            $this->error('Cloud backup failed. Check logs for details.');

            return self::FAILURE;
        }

        $this->info('Uploaded '.$result['filename'].' to '.$result['s3_key']);

        return self::SUCCESS;
    }
}
