<?php

namespace Tests\Unit;

use App\Support\DesktopStartupProbe;
use Tests\TestCase;

class DesktopStartupProbeTest extends TestCase
{
    public function test_native_config_and_php_ini_are_startup_probes(): void
    {
        $this->withArgv(['artisan', 'native:config'], function () {
            $this->assertTrue(DesktopStartupProbe::isRunning());
        });

        $this->withArgv(['artisan', 'native:php-ini'], function () {
            $this->assertTrue(DesktopStartupProbe::isRunning());
        });
    }

    public function test_the_web_server_and_other_commands_are_not_startup_probes(): void
    {
        $this->withArgv(['artisan', 'migrate', '--force'], function () {
            $this->assertFalse(DesktopStartupProbe::isRunning());
        });

        $this->withArgv(['php', '-S', '127.0.0.1:8100', 'server.php'], function () {
            $this->assertFalse(DesktopStartupProbe::isRunning());
        });
    }

    private function withArgv(array $argv, callable $callback): void
    {
        $previous = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = $argv;

        try {
            $callback();
        } finally {
            $_SERVER['argv'] = $previous;
        }
    }
}
