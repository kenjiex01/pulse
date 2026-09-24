<?php

namespace App\Console\Commands;

use App\Services\People360LanBeacon;
use App\Services\People360LanIdentity;
use App\Services\People360LanSnapshot;
use App\Support\People360LanProtocol;
use App\Support\People360LanUdp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class People360LanServeCommand extends Command
{
    protected $signature = 'people360:lan-serve';

    protected $description = 'Announce this People360 desktop on the local network and serve its database to a chosen admin computer';

    public function handle(People360LanIdentity $identity, People360LanSnapshot $snapshot): int
    {
        if (! config('people360_lan.enabled', true)) {
            $this->info('People360 network discovery is disabled.');

            return self::SUCCESS;
        }

        $udpPort = (int) config('people360_lan.udp_port');
        $httpPort = (int) config('people360_lan.http_port');
        People360LanBeacon::allowWindowsFirewall();

        try {
            $udp = People360LanUdp::bind($udpPort);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $tcp = stream_socket_server('tcp://0.0.0.0:'.$httpPort, $tcpError, $tcpMessage);

        if ($tcp === false) {
            $udp->close();
            $this->error($tcpMessage ?: 'Could not listen for People360 network connections.');

            return self::FAILURE;
        }

        stream_set_blocking($tcp, false);
        $this->info('People360 is visible on this network (UDP '.$udpPort.', HTTP '.$httpPort.').');

        while (true) {
            if ($this->heartbeatExpired()) {
                $udp->close();

                return self::SUCCESS;
            }

            if ($udp->wait(1, 0)) {
                $this->replyToDiscover($udp, $identity);
            }

            $read = [$tcp];
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 200000) > 0) {
                $client = @stream_socket_accept($tcp, 0);
                if (is_resource($client)) {
                    $this->handleHttp($client, $identity, $snapshot);
                    fclose($client);
                }
            }
        }
    }

    private function heartbeatExpired(): bool
    {
        $heartbeat = storage_path('app/people360-lan.heartbeat');

        if (! is_file($heartbeat)) {
            return true;
        }

        return time() - (int) File::get($heartbeat) > 180;
    }

    private function replyToDiscover(People360LanUdp $udp, People360LanIdentity $identity): void
    {
        $packet = $udp->receive();

        if ($packet === null || $packet['payload'] !== People360LanProtocol::DISCOVER) {
            return;
        }

        $udp->send(
            People360LanProtocol::helloPacket($identity->payload()),
            $packet['host'],
            $packet['port'],
        );
    }

    /**
     * @param  resource  $client
     */
    private function handleHttp($client, People360LanIdentity $identity, People360LanSnapshot $snapshot): void
    {
        stream_set_timeout($client, 120);
        $raw = $this->readHttp($client);
        if ($raw === null) {
            $this->respond($client, 400, 'Bad request');

            return;
        }

        [$requestLine, $headers, $body] = $raw;
        $parts = explode(' ', $requestLine);
        $method = $parts[0] ?? '';
        $path = $parts[1] ?? '';

        if (! hash_equals((string) config('people360_lan.key'), (string) ($headers['x-people360-lan-key'] ?? ''))) {
            $this->respond($client, 401, 'Unauthorized');

            return;
        }

        if ($method === 'GET' && $path === '/v1/identity') {
            $this->respond($client, 200, json_encode($identity->payload(), JSON_THROW_ON_ERROR), 'application/json');

            return;
        }

        if (! $identity->isSqlite()) {
            $this->respond($client, 409, 'This People360 computer is not using a desktop database file.');

            return;
        }

        if ($method === 'GET' && $path === '/v1/database') {
            $temporary = storage_path('app/lan-databases/serve-'.bin2hex(random_bytes(6)).'.sqlite');
            try {
                $snapshot->exportTo($temporary);
                $this->respondFile($client, $temporary);
            } catch (\Throwable $exception) {
                $this->respond($client, 500, $exception->getMessage());
            } finally {
                if (is_file($temporary)) {
                    File::delete($temporary);
                }
            }

            return;
        }

        if ($method === 'PUT' && $path === '/v1/database') {
            $temporary = storage_path('app/lan-databases/receive-'.bin2hex(random_bytes(6)).'.sqlite');
            File::ensureDirectoryExists(dirname($temporary));
            File::put($temporary, $body);
            try {
                $snapshot->replaceDatabase($temporary);
                $this->respond($client, 200, 'ok');
            } catch (\Throwable $exception) {
                $this->respond($client, 500, $exception->getMessage());
            } finally {
                if (is_file($temporary)) {
                    File::delete($temporary);
                }
            }

            return;
        }

        $this->respond($client, 404, 'Not found');
    }

    /**
     * @param  resource  $client
     * @return array{0: string, 1: array<string, string>, 2: string}|null
     */
    private function readHttp($client): ?array
    {
        $header = '';
        while (! str_contains($header, "\r\n\r\n")) {
            $chunk = fread($client, 8192);
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $header .= $chunk;
            if (strlen($header) > 16384) {
                return null;
            }
        }

        [$head, $body] = explode("\r\n\r\n", $header, 2);
        $lines = explode("\r\n", $head);
        $requestLine = array_shift($lines) ?: '';
        $headers = [];
        foreach ($lines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        $length = (int) ($headers['content-length'] ?? 0);
        $max = (int) config('people360_lan.max_database_bytes');
        if ($length > $max) {
            return null;
        }

        while (strlen($body) < $length) {
            $chunk = fread($client, min(65536, $length - strlen($body)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        if (strlen($body) < $length) {
            return null;
        }

        return [$requestLine, $headers, substr($body, 0, $length)];
    }

    /**
     * @param  resource  $client
     */
    private function respond($client, int $status, string $body, string $type = 'text/plain'): void
    {
        $text = match ($status) {
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            404 => 'Not Found',
            409 => 'Conflict',
            default => 'Error',
        };
        $payload = 'HTTP/1.1 '.$status.' '.$text."\r\n".
            'Content-Type: '.$type."\r\n".
            'Content-Length: '.strlen($body)."\r\n".
            "Connection: close\r\n\r\n".
            $body;
        fwrite($client, $payload);
    }

    /**
     * @param  resource  $client
     */
    private function respondFile($client, string $path): void
    {
        $size = filesize($path);
        $head = "HTTP/1.1 200 OK\r\n".
            "Content-Type: application/octet-stream\r\n".
            'Content-Length: '.$size."\r\n".
            "Connection: close\r\n\r\n";
        fwrite($client, $head);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }
        stream_copy_to_stream($handle, $client);
        fclose($handle);
    }
}
