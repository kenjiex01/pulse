<?php

namespace Tests\Unit;

use App\Support\People360LanNetworks;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class People360LanNetworksTest extends TestCase
{
    #[Test]
    public function it_lists_other_hosts_on_a_24_bit_subnet(): void
    {
        $targets = People360LanNetworks::probeTargets([
            ['address' => '10.0.3.63', 'netmask' => '255.255.255.0'],
        ]);

        $this->assertCount(1, $targets);
        $this->assertSame('10.0.3.255', $targets[0]['broadcast']);
        $this->assertCount(253, $targets[0]['hosts']);
        $this->assertContains('10.0.3.142', $targets[0]['hosts']);
        $this->assertNotContains('10.0.3.63', $targets[0]['hosts']);
        $this->assertNotContains('10.0.3.0', $targets[0]['hosts']);
        $this->assertNotContains('10.0.3.255', $targets[0]['hosts']);
    }

    #[Test]
    public function it_does_not_scan_a_subnet_larger_than_512_hosts(): void
    {
        $targets = People360LanNetworks::probeTargets([
            ['address' => '10.1.2.3', 'netmask' => '255.0.0.0'],
        ]);

        $this->assertSame('10.255.255.255', $targets[0]['broadcast']);
        $this->assertSame([], $targets[0]['hosts']);
    }

    #[Test]
    public function it_skips_loopback_and_link_local_addresses(): void
    {
        $this->assertFalse(People360LanNetworks::isUsableIpv4('127.0.0.1', '255.0.0.0'));
        $this->assertFalse(People360LanNetworks::isUsableIpv4('169.254.12.8', '255.255.0.0'));
        $this->assertSame([], People360LanNetworks::probeTargets([
            ['address' => '127.0.0.1', 'netmask' => '255.0.0.0'],
        ]));
    }

    #[Test]
    public function it_reads_ipv4_rows_from_windows_ipconfig(): void
    {
        $output = <<<'TXT'
Ethernet adapter Ethernet:

   IPv4 Address. . . . . . . . . . . : 10.0.3.63
   Subnet Mask . . . . . . . . . . . : 255.255.255.0

Wireless LAN adapter Wi-Fi:

   Autoconfiguration IPv4 Address. . : 169.254.1.20
   Subnet Mask . . . . . . . . . . . : 255.255.0.0
TXT;

        $this->assertSame([
            ['address' => '10.0.3.63', 'netmask' => '255.255.255.0'],
        ], People360LanNetworks::fromIpconfig($output));
    }
}
