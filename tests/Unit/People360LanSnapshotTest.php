<?php

namespace Tests\Unit;

use App\Services\People360LanIdentity;
use App\Services\People360LanSnapshot;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class People360LanSnapshotTest extends TestCase
{
    #[Test]
    public function it_exports_a_consistent_sqlite_copy(): void
    {
        $source = storage_path('framework/testing/lan-source.sqlite');
        $destination = storage_path('framework/testing/lan-copy.sqlite');
        @unlink($source);
        @unlink($destination);

        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('create table sample (name text)');
        $pdo->exec("insert into sample (name) values ('People360')");
        $pdo = null;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $source,
        ]);

        (new People360LanSnapshot(new People360LanIdentity))->exportTo($destination);

        $copy = new PDO('sqlite:'.$destination);
        $name = $copy->query('select name from sample')->fetchColumn();

        $this->assertSame('People360', $name);

        @unlink($source);
        @unlink($destination);
    }
}
