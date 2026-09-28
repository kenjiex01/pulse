<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class EmailAddressList
{
    /**
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(static fn (string $email) => trim($email))
            ->filter(static fn (string $email) => $email !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function parseValidated(?string $raw, string $field = 'additional_emails'): array
    {
        $emails = self::parse($raw);
        $invalid = [];

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
            }
        }

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                $field => 'These email addresses are invalid: '.implode(', ', $invalid),
            ]);
        }

        return $emails;
    }
}
