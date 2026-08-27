<?php

namespace Tests\Unit;

use App\Support\EncryptedEnv;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EncryptedEnvTest extends TestCase
{
    #[Test]
    public function reveal_returns_plaintext_unchanged(): void
    {
        $this->assertSame('plain-key', EncryptedEnv::reveal('plain-key'));
        $this->assertSame('', EncryptedEnv::reveal(null));
        $this->assertSame('', EncryptedEnv::reveal(''));
    }

    #[Test]
    public function seal_and_reveal_round_trip(): void
    {
        $sealed = EncryptedEnv::seal('skp_test_secret');

        $this->assertTrue(EncryptedEnv::isEncrypted($sealed));
        $this->assertStringStartsWith(EncryptedEnv::PREFIX, $sealed);
        $this->assertSame('skp_test_secret', EncryptedEnv::reveal($sealed));
    }

    #[Test]
    public function reveal_configured_secrets_decrypts_in_memory_only(): void
    {
        $sealed = EncryptedEnv::seal('AKIATEST');

        config(['filesystems.disks.backup-s3.key' => $sealed]);

        EncryptedEnv::revealConfiguredSecrets();

        $this->assertSame('AKIATEST', config('filesystems.disks.backup-s3.key'));
        $this->assertFalse(EncryptedEnv::isEncrypted((string) config('filesystems.disks.backup-s3.key')));
    }

    #[Test]
    public function sealed_payload_is_not_the_plain_secret(): void
    {
        $plain = 'super-secret-value';
        $sealed = EncryptedEnv::seal($plain);

        $this->assertStringNotContainsString($plain, $sealed);
        $this->assertNotSame($plain, Crypt::encryptString($plain));
    }
}
