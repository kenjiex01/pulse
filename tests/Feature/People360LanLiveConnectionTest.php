<?php

namespace Tests\Feature;

use App\Database\People360LanPdo;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\File;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class People360LanLiveConnectionTest extends TestCase
{
    #[Test]
    public function it_reads_and_writes_the_other_computers_database_without_copying_it(): void
    {
        $directory = storage_path('framework/testing/lan-live');
        File::ensureDirectoryExists($directory);
        $database = $directory.'/host.sqlite';
        if (is_file($database)) {
            unlink($database);
        }

        $host = new PDO('sqlite:'.$database);
        $host->exec('create table lan_probe (id integer primary key, label text not null)');
        $host->exec("insert into lan_probe (id, label) values (1, 'pc-value')");
        $host = null;

        $udp = 47000 + (getmypid() % 800);
        $http = $udp + 1;
        File::put(storage_path('app/people360-lan.heartbeat'), (string) time());

        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'people360:lan-serve'],
            base_path(),
            array_merge(getenv() ?: [], [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $database,
                'PEOPLE360_LAN_UDP_PORT' => (string) $udp,
                'PEOPLE360_LAN_HTTP_PORT' => (string) $http,
            ]),
        );
        $process->setTimeout(20);
        $process->start();

        try {
            $process->waitUntil(function (string $type, string $output): bool {
                return str_contains($output, 'visible on this network');
            });

            $connection = new SQLiteConnection(
                new People360LanPdo('127.0.0.1', $http),
                ':memory:',
                '',
                ['name' => 'people360_lan'],
            );

            $before = $connection->select('select label from lan_probe where id = ?', [1]);
            $this->assertSame('pc-value', $before[0]->label ?? null);

            $connection->update('update lan_probe set label = ? where id = ?', ['saved-on-pc', 1]);

            $check = new PDO('sqlite:'.$database);
            $this->assertSame('saved-on-pc', $check->query('select label from lan_probe where id = 1')->fetchColumn());
        } finally {
            $process->stop(0);
        }
    }
}
