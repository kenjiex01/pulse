<?php

namespace App\Services;

use App\Support\OutboundMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class ProbationaryEndNotificationScheduleService
{
    private const RUN_MARKER = 'app/.probationary-end-notification-last-run.json';

    /** @deprecated Legacy plain-date marker; still read for upgrades */
    private const DATE_MARKER = 'app/.probationary-end-notification-date';

    private static bool $dispatchQueued = false;

    public function runIfNeeded(): void
    {
        if (app()->runningUnitTests() || self::$dispatchQueued) {
            return;
        }

        if (! $this->shouldRunNow()) {
            return;
        }

        self::$dispatchQueued = true;

        dispatch(function (): void {
            $stats = app(ProbationaryEndNotificationService::class)->sendDueNotifications();
            app(self::class)->markRanToday(null, $stats);
        })->afterResponse();
    }

    public function shouldRunNow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now('Asia/Manila');

        return ! $this->hasRunToday($now);
    }

    public function hasRunToday(?Carbon $now = null): bool
    {
        $now ??= Carbon::now('Asia/Manila');
        $marker = $this->readRunMarker();

        if ($marker === null) {
            return false;
        }

        return ($marker['calendar_date'] ?? '') === $now->toDateString();
    }

    /**
     * @param  array{employees_checked?: int, employee_emails_sent?: int, hr_emails_sent?: int, skipped_already_sent?: int}|null  $stats
     */
    public function markRanToday(?Carbon $now = null, ?array $stats = null): void
    {
        $now ??= Carbon::now('Asia/Manila');
        $path = storage_path(self::RUN_MARKER);
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'calendar_date' => $now->toDateString(),
            'completed_at' => $now->toIso8601String(),
            'employee_emails_sent' => (int) ($stats['employee_emails_sent'] ?? 0),
            'hr_emails_sent' => (int) ($stats['hr_emails_sent'] ?? 0),
            'employees_checked' => (int) ($stats['employees_checked'] ?? 0),
            'skipped_already_sent' => (int) ($stats['skipped_already_sent'] ?? 0),
            'mail_mailer' => OutboundMail::defaultMailer(),
            'mail_delivered_to_inbox' => OutboundMail::deliversToInbox(),
        ];

        File::put($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $legacyPath = storage_path(self::DATE_MARKER);
        if (File::exists($legacyPath)) {
            File::delete($legacyPath);
        }
    }

    /**
     * @return array{
     *     sent_today: bool,
     *     calendar_date: string|null,
     *     completed_at: string|null,
     *     employee_emails_sent: int,
     *     hr_emails_sent: int,
     *     employees_checked: int,
     *     mail_mailer: string|null,
     *     mail_delivered_to_inbox: bool,
     * }
     */
    public function todayRunStatus(?Carbon $now = null): array
    {
        $now ??= Carbon::now('Asia/Manila');
        $marker = $this->readRunMarker();
        $sentToday = $marker !== null && ($marker['calendar_date'] ?? '') === $now->toDateString();

        return [
            'sent_today' => $sentToday,
            'calendar_date' => $sentToday ? (string) ($marker['calendar_date'] ?? null) : null,
            'completed_at' => $sentToday ? ($marker['completed_at'] ?? null) : null,
            'employee_emails_sent' => $sentToday ? (int) ($marker['employee_emails_sent'] ?? 0) : 0,
            'hr_emails_sent' => $sentToday ? (int) ($marker['hr_emails_sent'] ?? 0) : 0,
            'employees_checked' => $sentToday ? (int) ($marker['employees_checked'] ?? 0) : 0,
            'mail_mailer' => $sentToday ? (string) ($marker['mail_mailer'] ?? OutboundMail::defaultMailer()) : null,
            'mail_delivered_to_inbox' => $sentToday
                ? (bool) ($marker['mail_delivered_to_inbox'] ?? false)
                : false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRunMarker(): ?array
    {
        $path = storage_path(self::RUN_MARKER);

        if (File::exists($path)) {
            $decoded = json_decode((string) File::get($path), true);

            if (is_array($decoded) && filled($decoded['calendar_date'] ?? null)) {
                return $decoded;
            }
        }

        $legacyPath = storage_path(self::DATE_MARKER);

        if (! File::exists($legacyPath)) {
            return null;
        }

        $date = trim((string) File::get($legacyPath));

        if ($date === '') {
            return null;
        }

        return [
            'calendar_date' => $date,
            'completed_at' => null,
            'employee_emails_sent' => 0,
            'hr_emails_sent' => 0,
            'employees_checked' => 0,
            'skipped_already_sent' => 0,
        ];
    }
}
