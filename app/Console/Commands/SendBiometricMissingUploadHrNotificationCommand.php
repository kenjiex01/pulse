<?php

namespace App\Console\Commands;

use App\Services\BiometricMissingUploadHrNotificationScheduleService;
use App\Services\BiometricMissingUploadHrNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBiometricMissingUploadHrNotificationCommand extends Command
{
    protected $signature = 'hr:send-biometric-missing-upload-notification {--force : Send even if before 5 PM or already marked for today}';

    protected $description = 'Email HR the list of campuses with no biometric S3 upload today (dashboard “No upload today”)';

    public function handle(
        BiometricMissingUploadHrNotificationService $service,
        BiometricMissingUploadHrNotificationScheduleService $schedule,
    ): int {
        $now = Carbon::now('Asia/Manila');

        if (! $this->option('force')) {
            if ($schedule->hasRunToday($now)) {
                $this->warn('Already ran for today (Asia/Manila). Use --force to send again.');

                return self::SUCCESS;
            }

            if (! $schedule->isAtOrAfterSendTime($now)) {
                $this->warn('Before configured send time (default 5:00 PM Asia/Manila). Use --force to send now.');

                return self::SUCCESS;
            }
        }

        $result = $service->sendDailyReport($now);

        if ($result['sent']) {
            $schedule->markRanToday($now, $result);
            $this->info(sprintf(
                'Sent HR email to %s (%d missing of %d active campus(es)).',
                $result['hr_email'],
                $result['missing_campus_count'],
                $result['active_campus_count'],
            ));

            return self::SUCCESS;
        }

        $reason = $result['skipped_reason'] ?? 'unknown';
        $this->warn('Email was not sent: '.$reason);

        if ($this->option('force') && $reason !== 'mail_failed') {
            $schedule->markRanToday($now, $result);
        }

        return $reason === 'mail_failed' ? self::FAILURE : self::SUCCESS;
    }
}
