<?php

namespace Tests\Unit;

use App\Support\People360LanSql;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class People360LanSqlTest extends TestCase
{
    #[Test]
    public function it_round_trips_sql_bindings(): void
    {
        $encoded = People360LanSql::encode([1, 'Ana', null]);
        $json = json_decode(json_encode($encoded), true);

        $this->assertSame([1, 'Ana', null], People360LanSql::decode($json));
    }
}
