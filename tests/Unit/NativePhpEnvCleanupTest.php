<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use Tests\TestCase;

class NativePhpEnvCleanupTest extends TestCase
{
    public function test_desktop_backup_secret_is_not_removed_by_cleanup_patterns(): void
    {
        $patterns = config('nativephp.cleanup_env_keys', []);

        $this->assertNotContains('*_SECRET', $patterns);

        foreach ($patterns as $pattern) {
            $this->assertFalse(
                Str::is($pattern, 'DB_BACKUP_S3_SECRET'),
                "Pattern [{$pattern}] must not strip DB_BACKUP_S3_SECRET from packaged desktop .env.",
            );
            $this->assertFalse(
                Str::is($pattern, 'SKOLARIS_PULSE_API_KEY'),
                "Pattern [{$pattern}] must not strip SKOLARIS_PULSE_API_KEY from packaged desktop .env.",
            );
            $this->assertFalse(
                Str::is($pattern, 'SKOLARIS_PULSE_API_BASE_URL'),
                "Pattern [{$pattern}] must not strip SKOLARIS_PULSE_API_BASE_URL from packaged desktop .env.",
            );
            $this->assertFalse(
                Str::is($pattern, 'SKOLARIS_API_PASSWORD'),
                "Pattern [{$pattern}] must not strip SKOLARIS_API_PASSWORD from packaged desktop .env.",
            );
            $this->assertFalse(
                Str::is($pattern, 'SKOLARIS_API_IDENTIFIER'),
                "Pattern [{$pattern}] must not strip SKOLARIS_API_IDENTIFIER from packaged desktop .env.",
            );
        }
    }
}
