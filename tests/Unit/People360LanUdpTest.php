<?php

namespace Tests\Unit;

use App\Support\People360LanProtocol;
use App\Support\People360LanUdp;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class People360LanUdpTest extends TestCase
{
    #[Test]
    public function it_binds_an_ephemeral_udp_port_and_receives_a_discover_packet(): void
    {
        $server = People360LanUdp::bind(0);
        $client = People360LanUdp::bind(0);

        try {
            $this->assertGreaterThan(0, $server->port());
            $client->send(People360LanProtocol::DISCOVER, '127.0.0.1', $server->port());

            $this->assertTrue($server->wait(1, 0));
            $packet = $server->receive();

            $this->assertSame(People360LanProtocol::DISCOVER, $packet['payload'] ?? null);
            $this->assertSame('127.0.0.1', $packet['host'] ?? null);
        } finally {
            $server->close();
            $client->close();
        }
    }
}
