<?php

namespace Tests\Unit;

use App\Support\OutboundMail;
use Tests\TestCase;

class OutboundMailTest extends TestCase
{
    public function test_log_driver_does_not_deliver_to_inbox(): void
    {
        config(['mail.default' => 'log']);

        $this->assertFalse(OutboundMail::deliversToInbox());
        $this->assertStringContainsString('log', OutboundMail::setupHint());
    }

    public function test_smtp_requires_credentials(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        $this->assertFalse(OutboundMail::deliversToInbox());
    }

    public function test_smtp_with_credentials_delivers_to_inbox(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'user@example.com',
            'mail.mailers.smtp.password' => 'secret',
        ]);

        $this->assertTrue(OutboundMail::deliversToInbox());
    }
}
