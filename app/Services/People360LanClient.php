<?php

namespace App\Services;

use App\Support\People360LanNetworks;
use App\Support\People360LanProtocol;
use App\Support\People360LanSql;
use App\Support\People360LanUdp;
use RuntimeException;

class People360LanClient
{
    public function __construct(private readonly People360LanIdentity $identity) {}

    /**
     * @return list<array{hostname: string, machine_id: string, version: string, address: string, http_port: int, database: string, is_self: bool}>
     */
    public function discover(): array
    {
        $timeout = (float) config('people360_lan.discover_timeout_seconds', 1.5);
        $udpPort = (int) config('people360_lan.udp_port');
        $socket = People360LanUdp::bind(0);

        foreach ($this->broadcastTargets($udpPort) as $target) {
            [$host, $port] = explode(':', $target, 2);
            $socket->send(People360LanProtocol::DISCOVER, $host, (int) $port);
        }

        $selfId = $this->identity->payload()['machine_id'];
        $peers = [];
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                break;
            }

            $seconds = (int) $remaining;
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);

            if (! $socket->wait($seconds, $microseconds)) {
                continue;
            }

            $packet = $socket->receive();
            $identity = People360LanProtocol::parseHello((string) ($packet['payload'] ?? ''));

            if ($identity === null || ($identity['app'] ?? '') !== 'People360') {
                continue;
            }

            $address = $this->peerAddress((string) ($packet['host'] ?? ''));
            $machineId = (string) ($identity['machine_id'] ?? '');

            if ($machineId === '' || $address === null) {
                continue;
            }

            $existing = $peers[$machineId]['address'] ?? null;
            if ($existing !== null && $existing !== '127.0.0.1' && $address === '127.0.0.1') {
                continue;
            }

            $peers[$machineId] = [
                'hostname' => (string) ($identity['hostname'] ?? 'People360'),
                'machine_id' => $machineId,
                'version' => (string) ($identity['version'] ?? ''),
                'address' => $address,
                'http_port' => (int) ($identity['http_port'] ?? config('people360_lan.http_port')),
                'database' => (string) ($identity['database'] ?? ''),
                'is_self' => $machineId === $selfId,
            ];
        }

        $socket->close();

        $list = array_values($peers);
        usort($list, function (array $left, array $right): int {
            return [$left['is_self'] ? 0 : 1, strtolower($left['hostname'])]
                <=> [$right['is_self'] ? 0 : 1, strtolower($right['hostname'])];
        });

        return $list;
    }

    public function ping(string $address, int $port): void
    {
        $this->runSql($address, $port, 'SELECT 1', []);
    }

    public function release(string $address, int $port): void
    {
        try {
            $this->request('POST', $address, $port, '/v1/release');
        } catch (RuntimeException) {
            // Disconnect still returns this computer to its own database.
        }
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array{rows: list<array<string, mixed>>, row_count: int, last_insert_id: string, in_transaction: bool}
     */
    public function runSql(string $address, int $port, string $sql, array $bindings): array
    {
        $this->assertPeer($address, $port);
        $payload = json_encode([
            'sql' => $sql,
            'bindings' => People360LanSql::encode($bindings),
        ], JSON_THROW_ON_ERROR);

        $body = $this->request('POST', $address, $port, '/v1/sql', $payload, 'application/json');
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            throw new RuntimeException('That People360 computer sent an invalid database response.');
        }

        return [
            'rows' => array_values(array_filter($decoded['rows'], 'is_array')),
            'row_count' => (int) ($decoded['row_count'] ?? 0),
            'last_insert_id' => (string) ($decoded['last_insert_id'] ?? '0'),
            'in_transaction' => (bool) ($decoded['in_transaction'] ?? false),
        ];
    }

    public function downloadDatabase(string $address, int $port): string
    {
        $this->assertPeer($address, $port);
        $directory = storage_path('app/lan-databases');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create a folder for the remote database.');
        }

        $destination = $directory.DIRECTORY_SEPARATOR.'incoming-'.bin2hex(random_bytes(8)).'.sqlite';
        $body = $this->request('GET', $address, $port, '/v1/database');
        if (file_put_contents($destination, $body) === false || filesize($destination) === 0) {
            throw new RuntimeException('The remote database download was empty.');
        }

        return $destination;
    }

    public function uploadDatabase(string $address, int $port, string $path): void
    {
        $this->assertPeer($address, $port);

        if (! is_file($path)) {
            throw new RuntimeException('The connected database file is missing.');
        }

        $this->request('PUT', $address, $port, '/v1/database', (string) file_get_contents($path));
    }

    private function request(string $method, string $address, int $port, string $path, string $body = '', string $contentType = 'application/octet-stream'): string
    {
        $socket = @stream_socket_client('tcp://'.$address.':'.$port, $errno, $error, 5);

        if ($socket === false) {
            throw new RuntimeException('Could not reach that People360 computer: '.$error);
        }

        stream_set_timeout($socket, 120);
        $key = (string) config('people360_lan.key');
        $header = $method.' '.$path." HTTP/1.1\r\n".
            'Host: '.$address."\r\n".
            'X-People360-Lan-Key: '.$key."\r\n".
            "Connection: close\r\n";

        if ($body !== '') {
            $header .= 'Content-Type: '.$contentType."\r\n".
                'Content-Length: '.strlen($body)."\r\n";
        }

        fwrite($socket, $header."\r\n".$body);

        $raw = stream_get_contents($socket);
        fclose($socket);

        if (! is_string($raw) || ! str_contains($raw, "\r\n\r\n")) {
            throw new RuntimeException('That People360 computer did not answer.');
        }

        [$head, $responseBody] = explode("\r\n\r\n", $raw, 2);
        if (! preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d+)#', $head, $matches)) {
            throw new RuntimeException('That People360 computer sent an invalid response.');
        }

        $status = (int) $matches[1];
        if ($status !== 200) {
            $message = trim($responseBody) !== '' ? trim($responseBody) : 'Request failed ('.$status.').';
            throw new RuntimeException($message);
        }

        return $responseBody;
    }

    private function assertPeer(string $address, int $port): void
    {
        if (! People360LanProtocol::isPrivateIpv4($address) || $port < 1 || $port > 65535) {
            throw new RuntimeException('That computer is not on this network.');
        }
    }

    /**
     * @return list<string>
     */
    private function broadcastTargets(int $port): array
    {
        $targets = ['127.0.0.1:'.$port, '255.255.255.255:'.$port];

        foreach (People360LanNetworks::probeTargets(People360LanNetworks::localAddresses()) as $network) {
            $targets[] = $network['broadcast'].':'.$port;

            foreach ($network['hosts'] as $host) {
                $targets[] = $host.':'.$port;
            }
        }

        return array_values(array_unique($targets));
    }

    private function peerAddress(string $peer): ?string
    {
        $address = str_starts_with($peer, '[') ? '' : explode(':', $peer)[0];

        return People360LanProtocol::isPrivateIpv4($address) ? $address : null;
    }
}
