<?php

namespace App\Support;

class People360LanProtocol
{
    public const DISCOVER = 'P360DISCOVER';

    public const HELLO_PREFIX = 'P360HELLO ';

    /**
     * @param  array<string, mixed>  $identity
     */
    public static function helloPacket(array $identity): string
    {
        return self::HELLO_PREFIX.json_encode($identity, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseHello(string $packet): ?array
    {
        if (! str_starts_with($packet, self::HELLO_PREFIX)) {
            return null;
        }

        $decoded = json_decode(substr($packet, strlen(self::HELLO_PREFIX)), true);

        return is_array($decoded) ? $decoded : null;
    }

    public static function isPrivateIpv4(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
