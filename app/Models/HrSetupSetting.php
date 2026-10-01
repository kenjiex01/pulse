<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrSetupSetting extends Model
{
    protected $table = 'tbl_hr_setup_settings';

    protected $primaryKey = 'hr_setup_setting_id';

    protected $fillable = [
        'hr_email',
        'probationary_end_notification_days',
        'probationary_end_email_subject',
        'probationary_end_email_body',
    ];

    public static function settings(): self
    {
        return static::query()->firstOrCreate([], [
            'hr_email' => null,
            'probationary_end_notification_days' => null,
            'probationary_end_email_subject' => null,
            'probationary_end_email_body' => null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function hrEmails(): array
    {
        return static::parseHrEmails(static::settings()->hr_email);
    }

    /** @deprecated Prefer hrEmails(); comma-separated string when multiple are configured. */
    public static function hrEmail(): ?string
    {
        $emails = static::hrEmails();

        return $emails === [] ? null : implode(', ', $emails);
    }

    /**
     * @return array<int, string>
     */
    public static function parseHrEmails(mixed $raw): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', trim((string) $raw)) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim($part));

            if ($email === '' || in_array($email, $emails, true)) {
                continue;
            }

            $emails[] = $email;
        }

        return $emails;
    }

    public static function normalizeHrEmailInput(?string $raw): ?string
    {
        $emails = static::parseHrEmails($raw);

        return $emails === [] ? null : implode(', ', $emails);
    }

    public static function hrEmailInputIsValid(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return true;
        }

        foreach (preg_split('/\s*,\s*/', trim($raw)) ?: [] as $part) {
            if ($part === '' || ! filter_var(trim($part), FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    public static function probationaryEndNotificationDayList(): array
    {
        return static::parseProbationaryNotificationDays(
            static::settings()->probationary_end_notification_days,
        );
    }

    /**
     * @return array<int, int>
     */
    public static function parseProbationaryNotificationDays(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
            return [(int) $raw];
        }

        $parts = preg_split('/\s*,\s*/', trim((string) $raw)) ?: [];
        $days = [];

        foreach ($parts as $part) {
            if ($part === '' || ! ctype_digit($part)) {
                continue;
            }

            $value = (int) $part;

            if ($value < 0 || $value > 365) {
                continue;
            }

            if (! in_array($value, $days, true)) {
                $days[] = $value;
            }
        }

        sort($days);

        return $days;
    }

    public static function formatProbationaryNotificationDays(array $days): ?string
    {
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);
        $days = array_values(array_filter($days, fn (int $day) => $day >= 0 && $day <= 365));

        return $days === [] ? null : implode(', ', $days);
    }

    public static function normalizeProbationaryNotificationDaysInput(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];

        if ($parts === []) {
            return null;
        }

        return static::formatProbationaryNotificationDays(
            array_map('intval', $parts),
        );
    }
}
