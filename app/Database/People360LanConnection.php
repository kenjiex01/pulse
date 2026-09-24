<?php

namespace App\Database;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;

class People360LanConnection
{
    private static ?string $local = null;

    public static function rememberLocal(): string
    {
        $current = (string) config('database.default');

        if (self::$local === null) {
            self::$local = $current === 'people360_lan' ? 'sqlite' : $current;
        }

        return self::$local;
    }

    public static function useRemote(string $address, int $port): void
    {
        self::rememberLocal();

        config([
            'database.connections.people360_lan' => [
                'driver' => 'people360_lan',
                'address' => $address,
                'port' => $port,
                'database' => ':memory:',
                'prefix' => '',
                'name' => 'people360_lan',
            ],
        ]);

        DB::purge('people360_lan');
        DB::setDefaultConnection('people360_lan');
    }

    public static function useLocal(): void
    {
        $local = self::rememberLocal();

        if ((string) config('database.default') === $local) {
            return;
        }

        DB::setDefaultConnection($local);
        DB::purge('people360_lan');
    }

    public static function register(): void
    {
        DB::extend('people360_lan', function (array $config, string $name) {
            $config['name'] = $name;
            $pdo = new People360LanPdo((string) $config['address'], (int) $config['port']);

            return new SQLiteConnection($pdo, ':memory:', (string) ($config['prefix'] ?? ''), $config);
        });
    }
}
