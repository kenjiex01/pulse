<?php

namespace App\Console\Commands;

use App\Models\HrProbationaryEndNotificationLog;
use App\Services\ProbationaryEndNotificationScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class ResetProbationaryEndNotificationRunTodayCommand extends Command
{
    protected $signature = 'hr:reset-probationary-end-notification-run-today';

    protected $description = 'Clear today\'s probationary end daily-run marker and notification log rows so emails can be sent again';

    public function handle(ProbationaryEndNotificationScheduleService $schedule): int
    {
        $today = Carbon::now('Asia/Manila')->toDateString();

        foreach ([
            storage_path('app/.probationary-end-notification-last-run.json'),
            storage_path('app/.probationary-end-notification-date'),
        ] as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $deleted = HrProbationaryEndNotificationLog::query()
            ->whereDate('sent_at', $today)
            ->delete();

        $this->info("Cleared daily run marker and deleted {$deleted} notification log row(s) for {$today}.");

        if ($schedule->shouldRunNow()) {
            $this->line('The next app request or `php artisan hr:send-probationary-end-notifications` can send due reminders again.');
        }

        return self::SUCCESS;
    }
}
