<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\People360LanClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PDO;
use Tests\TestCase;

class People360LanDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_database_page_lists_people360_computers_on_the_network(): void
    {
        $client = Mockery::mock(People360LanClient::class);
        $client->shouldReceive('discover')->once()->andReturn([[
            'hostname' => 'PAYROLL-PC',
            'machine_id' => str_repeat('b', 32),
            'version' => '1.0.8',
            'address' => '192.168.1.20',
            'http_port' => 47837,
            'database' => 'sqlite',
            'is_self' => false,
        ]]);
        $this->app->instance(People360LanClient::class, $client);

        $response = $this->actingAs(User::query()->firstOrFail())->get(route('database.index'));

        $response->assertOk();
        $response->assertSee('People360 on this network');
        $response->assertSee('PAYROLL-PC');
        $response->assertSee('192.168.1.20');
    }

    public function test_admin_can_connect_to_a_private_network_desktop_database(): void
    {
        $source = storage_path('framework/testing/lan-download.sqlite');
        @unlink($source);
        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('create table sample (id integer)');
        $pdo = null;

        $client = Mockery::mock(People360LanClient::class);
        $client->shouldReceive('downloadDatabase')->once()->andReturn($source);
        $this->app->instance(People360LanClient::class, $client);

        $response = $this->actingAs(User::query()->firstOrFail())->post(route('database.lan.connect'), [
            'machine_id' => str_repeat('c', 32),
            'hostname' => 'PAYROLL-PC',
            'address' => '192.168.1.20',
            'http_port' => 47837,
            'version' => '1.0.8',
        ]);

        $response->assertRedirect(route('database.index'));
        $response->assertSessionHas('people360_lan_database.hostname', 'PAYROLL-PC');
        $this->assertFileExists(storage_path('app/lan-databases/'.str_repeat('c', 32).'.sqlite'));
    }

    public function test_connect_rejects_a_public_address(): void
    {
        $client = Mockery::mock(People360LanClient::class);
        $client->shouldReceive('downloadDatabase')->never();
        $this->app->instance(People360LanClient::class, $client);

        $response = $this->actingAs(User::query()->firstOrFail())->post(route('database.lan.connect'), [
            'machine_id' => str_repeat('d', 32),
            'hostname' => 'Outside',
            'address' => '8.8.8.8',
            'http_port' => 47837,
            'version' => '1.0.8',
        ]);

        $response->assertRedirect(route('database.index'));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('people360_lan_database');
    }
}
