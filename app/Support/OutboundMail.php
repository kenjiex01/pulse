<?php

namespace App\Support;

class OutboundMail
{
    public static function defaultMailer(): string
    {
        return (string) config('mail.default', 'log');
    }

    public static function deliversToInbox(): bool
    {
        $mailer = static::defaultMailer();

        if ($mailer === 'ses' || $mailer === 'ses-v2') {
            return filled(config('services.ses.key')) && filled(config('services.ses.secret'));
        }

        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password'));
        }

        return false;
    }

    public static function setupHint(): string
    {
        if (static::deliversToInbox()) {
            return '';
        }

        $mailer = static::defaultMailer();

        if (in_array($mailer, ['log', 'array'], true)) {
            return 'Email is using the "'.$mailer.'" driver — nothing is delivered to real inboxes. Set MAIL_MAILER=smtp (or ses) and mail credentials in pulse/.env, then restart the app.';
        }

        if ($mailer === 'smtp') {
            return 'Set MAIL_USERNAME and MAIL_PASSWORD (App Password) in pulse/.env, then restart the app.';
        }

        return 'Configure outbound email (MAIL_MAILER and credentials) in pulse/.env, then restart the app.';
    }
}
