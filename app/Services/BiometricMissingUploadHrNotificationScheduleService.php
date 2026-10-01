<?php

namespace App\Services;

use App\Support\OutboundMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class BiometricMissingUploadHrNotificationScheduleService
{
    private const RUN_MARKER = 'app/.biometric-missing-upload-hr-notification-last-run.json';

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
            $result = app(BiometricMissingUploadHrNotificationService::class)->sendDailyReport();
            app(self::class)->markRanToday(null, $result);
        })->afterResponse();
    }

    public function shouldRunNow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now('Asia/Manila');

        if ($this->hasRunToday($now)) {
            return false;
        }

        return $this->isAtOrAfterSendTime($now);
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

    public function isAtOrAfterSendTime(?Carbon $now = null): bool
    {
        $now ??= Carbon::now('Asia/Manila');
        $hour = (int) config('biometric_logs.missing_upload_hr_notification.hour', 17);
        $minute = (int) config('biometric_logs.missing_upload_hr_notification.minute', 0);
        $timezone = (string) config('biometric_logs.missing_upload_hr_notification.timezone', 'Asia/Manila');

        $local = $now->copy()->timezone($timezone);
        $sendAtMinutes = ($hour * 60) + $minute;
        $nowMinutes = ((int) $local->format('G') * 60) + (int) $local->format('i');

        return $nowMinutes >= $sendAtMinutes;
    }

    /**
     * @param  array{
     *     sent?: bool,
     *     skipped_reason?: string|null,
     *     missing_campus_count?: int,
     *     active_campus_count?: int,
     *     hr_email?: string|null,
     * }|null  $result
     */
    public function markRanToday(?Carbon $now = null, ?array $result = null): void
    {
        $now ??= Carbon::now('Asia/Manila');
        $path = storage_path(self::RUN_MARKER);
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'calendar_date' => $now->toDateString(),
            'completed_at' => $now->toIso8601String(),
            'sent' => (bool) ($result['sent'] ?? false),
            'skipped_reason' => $result['skipped_reason'] ?? null,
            'missing_campus_count' => (int) ($result['missing_campus_count'] ?? 0),
            'active_campus_count' => (int) ($result['active_campus_count'] ?? 0),
            'hr_email' => $result['hr_email'] ?? null,
            'mail_mailer' => OutboundMail::defaultMailer(),
            'mail_delivered_to_inbox' => OutboundMail::deliversToInbox(),
        ];

        File::put($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readRunMarker(): ?array
    {
        $path = storage_path(self::RUN_MARKER);

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) && filled($decoded['calendar_date'] ?? null)
            ? $decoded
            : null;
    }
}
