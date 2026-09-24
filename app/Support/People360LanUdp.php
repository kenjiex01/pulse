<?php

namespace App\Support;

use RuntimeException;

/**
 * UDP socket for People360 discovery.
 * stream_socket_server('udp://0.0.0.0:0') fails on Windows, so discovery uses the sockets extension.
 */
class People360LanUdp
{
    /**
     * @param  resource|\Socket  $socket
     */
    private function __construct(private $socket) {}

    public static function bind(int $port): self
    {
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket === false) {
            throw new RuntimeException('Could not scan the network: '.self::lastError());
        }

        @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        @socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);

        if (! @socket_bind($socket, '0.0.0.0', $port)) {
            $message = socket_strerror(socket_last_error($socket));
            socket_close($socket);

            throw new RuntimeException('Could not scan the network: '.$message);
        }

        socket_set_nonblock($socket);

        return new self($socket);
    }

    public function port(): int
    {
        socket_getsockname($this->socket, $address, $port);

        return (int) $port;
    }

    public function send(string $payload, string $host, int $port): void
    {
        @socket_sendto($this->socket, $payload, strlen($payload), 0, $host, $port);
    }

    /**
     * @return array{payload: string, host: string, port: int}|null
     */
    public function receive(): ?array
    {
        $buffer = '';
        $host = '';
        $port = 0;
        $bytes = @socket_recvfrom($this->socket, $buffer, 8192, 0, $host, $port);

        if ($bytes === false) {
            socket_clear_error($this->socket);

            return null;
        }

        if ($bytes < 1 || ! is_string($host) || $host === '') {
            return null;
        }

        return [
            'payload' => $buffer,
            'host' => $host,
            'port' => (int) $port,
        ];
    }

    public function wait(int $seconds, int $microseconds): bool
    {
        $read = [$this->socket];
        $write = null;
        $except = null;
        $result = @socket_select($read, $write, $except, $seconds, max(0, $microseconds));

        return $result > 0;
    }

    public function close(): void
    {
        socket_close($this->socket);
    }

    private static function lastError(): string
    {
        $code = socket_last_error();

        return $code ? socket_strerror($code) : 'Unknown error';
    }
}
