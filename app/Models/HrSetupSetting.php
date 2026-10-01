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

    public static function hrEmail(): ?string
    {
        $email = trim((string) (static::settings()->hr_email ?? ''));

        return $email !== '' ? $email : null;
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
