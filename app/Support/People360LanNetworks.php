<?php

namespace App\Support;

class People360LanNetworks
{
    /**
     * Local IPv4 addresses and subnet masks, from PHP when available and from ipconfig on Windows.
     *
     * @return list<array{address: string, netmask: string}>
     */
    public static function localAddresses(): array
    {
        $found = [];

        if (function_exists('net_get_interfaces')) {
            foreach (net_get_interfaces() ?: [] as $info) {
                foreach ($info['unicast'] ?? [] as $unicast) {
                    $address = (string) ($unicast['address'] ?? '');
                    $netmask = (string) ($unicast['netmask'] ?? '');
                    if (self::isUsableIpv4($address, $netmask)) {
                        $found[] = ['address' => $address, 'netmask' => $netmask];
                    }
                }
            }
        }

        if ($found === [] && PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('ipconfig');
            if (is_string($output) && $output !== '') {
                $found = self::fromIpconfig($output);
            }
        }

        return $found;
    }

    /**
     * @return list<array{address: string, netmask: string}>
     */
    public static function fromIpconfig(string $output): array
    {
        $found = [];
        $address = null;

        foreach (preg_split("/\r\n|\n|\r/", $output) ?: [] as $line) {
            if (preg_match('/IPv4 Address[^:]*:\s*([0-9.]+)/i', $line, $matches) === 1) {
                $address = $matches[1];

                continue;
            }

            if ($address !== null && preg_match('/Subnet Mask[^:]*:\s*([0-9.]+)/i', $line, $matches) === 1) {
                if (self::isUsableIpv4($address, $matches[1])) {
                    $found[] = ['address' => $address, 'netmask' => $matches[1]];
                }
                $address = null;
            }
        }

        return $found;
    }

    /**
     * Broadcast address plus each other host on small subnets (up to $maxHosts).
     * Larger masks stay broadcast-only so a /8 is not scanned host by host.
     *
     * @param  list<array{address: string, netmask: string}>  $addresses
     * @return list<array{broadcast: string, hosts: list<string>}>
     */
    public static function probeTargets(array $addresses, int $maxHosts = 512): array
    {
        $targets = [];

        foreach ($addresses as $row) {
            $ip = self::unsignedIp((string) ($row['address'] ?? ''));
            $mask = self::unsignedIp((string) ($row['netmask'] ?? ''));

            if ($ip === null || $mask === null || ! self::isUsableIpv4((string) $row['address'], (string) $row['netmask'])) {
                continue;
            }

            $network = $ip & $mask;
            $broadcast = $network | (($mask ^ 0xFFFFFFFF) & 0xFFFFFFFF);
            $usable = $broadcast - $network - 1;
            $hosts = [];

            if ($usable >= 1 && $usable <= $maxHosts) {
                for ($host = $network + 1; $host < $broadcast; $host++) {
                    if ($host === $ip) {
                        continue;
                    }
                    $hosts[] = long2ip($host);
                }
            }

            $targets[] = [
                'broadcast' => long2ip($broadcast),
                'hosts' => $hosts,
            ];
        }

        return $targets;
    }

    public static function isUsableIpv4(string $address, string $netmask): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! filter_var($netmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ip = self::unsignedIp($address);
        if ($ip === null) {
            return false;
        }

        $loopback = ($ip & 0xFF000000) === 0x7F000000;
        $linkLocal = ($ip & 0xFFFF0000) === 0xA9FE0000;

        return ! $loopback && ! $linkLocal && People360LanProtocol::isPrivateIpv4($address);
    }

    private static function unsignedIp(string $address): ?int
    {
        $value = ip2long($address);

        if ($value === false) {
            return null;
        }

        return $value & 0xFFFFFFFF;
    }
}
