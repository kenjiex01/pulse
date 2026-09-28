<?php

namespace App\Console\Commands;

use App\Services\BiometricS3AutoPullService;
use Illuminate\Console\Command;

class PullBiometricLogsFromS3Command extends Command
{
    protected $signature = 'biometric:pull-s3 {--auto : Pull only when auto-pull is enabled}';

    protected $description = 'Pull new biometric attendance logs from S3';

    public function handle(BiometricS3AutoPullService $autoPullService): int
    {
        if ($this->option('auto')) {
            $summary = $autoPullService->runAutoPull();

            if ($summary === null) {
                $this->info('Biometric S3 auto-pull is disabled or S3 is not configured.');

                return self::SUCCESS;
            }

            $this->info(sprintf(
                'Auto-pull finished: %d imported, %d already pulled, %d punch(es) inserted.',
                $summary['files_imported'] ?? 0,
                $summary['files_already_pulled'] ?? 0,
                $summary['punches_inserted'] ?? 0,
            ));

            return self::SUCCESS;
        }

        $this->error('Manual month/folder pulls use the Time Logs screen.');

        return self::FAILURE;
    }
}
