<?php

namespace Tests\Unit;

use App\Support\People360LanProtocol;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class People360LanProtocolTest extends TestCase
{
    #[Test]
    public function it_round_trips_a_hello_packet(): void
    {
        $packet = People360LanProtocol::helloPacket([
            'app' => 'People360',
            'hostname' => 'PAYROLL-PC',
            'machine_id' => str_repeat('a', 32),
        ]);

        $parsed = People360LanProtocol::parseHello($packet);

        $this->assertSame('PAYROLL-PC', $parsed['hostname'] ?? null);
        $this->assertNull(People360LanProtocol::parseHello('nope'));
    }

    #[Test]
    public function it_accepts_only_private_ipv4_addresses(): void
    {
        $this->assertTrue(People360LanProtocol::isPrivateIpv4('192.168.1.20'));
        $this->assertTrue(People360LanProtocol::isPrivateIpv4('10.0.0.4'));
        $this->assertTrue(People360LanProtocol::isPrivateIpv4('127.0.0.1'));
        $this->assertFalse(People360LanProtocol::isPrivateIpv4('8.8.8.8'));
        $this->assertFalse(People360LanProtocol::isPrivateIpv4('not-an-ip'));
    }
}
