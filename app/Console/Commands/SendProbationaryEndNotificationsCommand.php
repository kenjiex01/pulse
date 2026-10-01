<?php

namespace App\Console\Commands;

use App\Services\ProbationaryEndNotificationScheduleService;
use App\Services\ProbationaryEndNotificationService;
use Illuminate\Console\Command;

class SendProbationaryEndNotificationsCommand extends Command
{
    protected $signature = 'hr:send-probationary-end-notifications';

    protected $description = 'Send probationary end reminder emails to employees and HR on configured day offsets';

    public function handle(
        ProbationaryEndNotificationService $service,
        ProbationaryEndNotificationScheduleService $schedule,
    ): int {
        $stats = $service->sendDueNotifications();
        $schedule->markRanToday(null, $stats);

        $this->info(sprintf(
            'Checked %d employee(s); sent %d HR email(s); skipped %d already sent.',
            $stats['employees_checked'],
            $stats['hr_emails_sent'],
            $stats['skipped_already_sent'],
        ));

        return self::SUCCESS;
    }
}
