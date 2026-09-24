<?php

namespace App\Services;

class People360LanIdentity
{
    /**
     * @return array{app: string, hostname: string, machine_id: string, version: string, http_port: int, udp_port: int, database: string}
     */
    public function payload(): array
    {
        $databasePath = $this->databasePath();

        return [
            'app' => 'People360',
            'hostname' => (string) (gethostname() ?: 'People360'),
            'machine_id' => substr(hash('sha256', (gethostname() ?: 'People360').'|'.$databasePath), 0, 32),
            'version' => (string) config('nativephp.version', env('NATIVEPHP_APP_VERSION', '0.0.0')),
            'http_port' => (int) config('people360_lan.http_port'),
            'udp_port' => (int) config('people360_lan.udp_port'),
            'database' => $this->isSqlite() ? 'sqlite' : (string) config('database.default'),
        ];
    }

    public function isSqlite(): bool
    {
        return config('database.default') === 'sqlite' && is_file($this->databasePath());
    }

    public function databasePath(): string
    {
        $configured = (string) config('database.connections.sqlite.database');

        if ($configured !== '' && $configured !== ':memory:') {
            return $configured;
        }

        return storage_path('app/pulse.sqlite');
    }
}
