<?php

namespace Tests\Unit;

use App\Support\SkolarisJwtCredentials;
use Tests\TestCase;

class SkolarisJwtCredentialsTest extends TestCase
{
    public function test_is_configured_when_identifier_and_password_present(): void
    {
        config([
            'skolaris.identifier' => 'admin@example.com',
            'skolaris.password' => 'secret',
        ]);

        $this->assertTrue(SkolarisJwtCredentials::isConfigured());
        $this->assertSame([
            'identifier' => 'admin@example.com',
            'password' => 'secret',
        ], SkolarisJwtCredentials::loginPayload());
    }

    public function test_is_not_configured_when_password_missing(): void
    {
        config([
            'skolaris.identifier' => 'admin@example.com',
            'skolaris.password' => '',
        ]);

        $this->assertFalse(SkolarisJwtCredentials::isConfigured());
    }
}
