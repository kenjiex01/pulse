<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class EncryptedEnv
{
    public const PREFIX = 'enc:';

    /**
     * Env keys that should be stored encrypted at rest in .env.
     *
     * @return list<string>
     */
    public static function secretEnvKeys(): array
    {
        return [
            'SKOLARIS_PULSE_API_KEY',
            'DB_BACKUP_S3_KEY',
            'DB_BACKUP_S3_SECRET',
            'BIOMETRIC_LOGS_S3_KEY',
            'BIOMETRIC_LOGS_S3_SECRET',
        ];
    }

    /**
     * Config paths rewritten with plaintext after boot (decrypt-on-use for services).
     *
     * @return list<string>
     */
    public static function secretConfigPaths(): array
    {
        return [
            'skolaris.pulse_api_key',
            'filesystems.disks.backup-s3.key',
            'filesystems.disks.backup-s3.secret',
            'backup.cloud.key',
            'backup.cloud.secret',
            'biometric_logs.s3.key',
            'biometric_logs.s3.secret',
        ];
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function seal(string $plain): string
    {
        return self::PREFIX.Crypt::encryptString($plain);
    }

    public static function reveal(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! self::isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unable to decrypt an encrypted env secret. Confirm APP_KEY matches the key used to encrypt it.',
                0,
                $exception,
            );
        }
    }

    /**
     * Replace encrypted config values with plaintext in memory only (never written back to .env).
     */
    public static function revealConfiguredSecrets(): void
    {
        foreach (self::secretConfigPaths() as $path) {
            $current = config($path);

            if (! is_string($current) || $current === '') {
                continue;
            }

            config([$path => self::reveal($current)]);
        }
    }
}
