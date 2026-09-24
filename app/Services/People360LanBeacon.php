<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class People360LanBeacon
{
    public function ensureRunning(): void
    {
        if (! config('people360_lan.enabled', true) || app()->runningUnitTests()) {
            return;
        }

        self::allowWindowsFirewall();

        File::put(storage_path('app/people360-lan.heartbeat'), (string) time());

        if (in_array('people360:lan-serve', $_SERVER['argv'] ?? [], true)) {
            return;
        }

        $port = (int) config('people360_lan.http_port');
        if ($this->listenerIsCurrent($port)) {
            return;
        }

        if ($this->portIsOpen($port)) {
            $this->stopListener($port);
            usleep(300000);
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = storage_path('logs/people360-lan.log');
        File::ensureDirectoryExists(dirname($log));

        if (PHP_OS_FAMILY === 'Windows') {
            $this->spawnHiddenWindowsLanServe($php, $artisan, $log);

            return;
        }

        $command = escapeshellarg($php).' '.escapeshellarg($artisan).' people360:lan-serve >> '.escapeshellarg($log).' 2>&1 &';
        exec($command);
    }

    private function spawnHiddenWindowsLanServe(string $php, string $artisan, string $log): void
    {
        $phpWin = dirname($php).DIRECTORY_SEPARATOR.'php-win.exe';
        if (is_file($phpWin)) {
            $php = $phpWin;
        }

        $inner = 'cd /d '.escapeshellarg(base_path())
            .' && '.escapeshellarg($php)
            .' '.escapeshellarg($artisan)
            .' people360:lan-serve >> '.escapeshellarg($log).' 2>&1';

        $vbs = storage_path('app/people360-lan-serve.vbs');
        File::put($vbs, "Set WshShell = CreateObject(\"WScript.Shell\")\r\n".
            'WshShell.Run "'.str_replace('"', '""', 'cmd /c '.$inner).'", 0, False'."\r\n");

        $cmdScript = storage_path('app/people360-lan-serve.cmd');
        if (is_file($cmdScript)) {
            File::delete($cmdScript);
        }

        pclose(popen('wscript.exe //B //Nologo '.escapeshellarg($vbs), 'r'));
    }

    private function listenerIsCurrent(int $port): bool
    {
        if (! $this->portIsOpen($port)) {
            return false;
        }

        $version = $this->listenerVersion($port);
        if ($version === null) {
            return true;
        }

        return $version === (string) config('nativephp.version', '0.0.0');
    }

    private function portIsOpen(int $port): bool
    {
        $probe = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.3);

        if (! is_resource($probe)) {
            return false;
        }

        fclose($probe);

        return true;
    }

    private function listenerVersion(int $port): ?string
    {
        $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.4);
        if (! is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 1);
        $key = (string) config('people360_lan.key');
        fwrite($socket, "GET /v1/identity HTTP/1.1\r\nHost: 127.0.0.1\r\nX-People360-Lan-Key: {$key}\r\nConnection: close\r\n\r\n");
        $raw = stream_get_contents($socket);
        fclose($socket);

        if (! is_string($raw) || ! str_contains($raw, "\r\n\r\n")) {
            return null;
        }

        [, $body] = explode("\r\n\r\n", $raw, 2);
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded['version'])) {
            return null;
        }

        return (string) $decoded['version'];
    }

    private function stopListener(int $port): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $lines = [];
            exec('netstat -ano -p TCP', $lines);
            $seen = [];
            foreach ($lines as $line) {
                if (! preg_match('/:'.$port.'\s+\S+\s+LISTENING\s+(\d+)/', $line, $matches)) {
                    continue;
                }
                $pid = (int) $matches[1];
                if ($pid < 1 || isset($seen[$pid])) {
                    continue;
                }
                $seen[$pid] = true;
                exec('taskkill /F /PID '.$pid);
            }

            return;
        }

        $lines = [];
        exec('lsof -nP -iTCP:'.$port.' -sTCP:LISTEN -t 2>/dev/null', $lines);
        foreach ($lines as $line) {
            $pid = (int) trim($line);
            if ($pid > 0) {
                exec('kill '.$pid);
            }
        }
    }

    public static function allowWindowsFirewall(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        static $attempted = false;
        if ($attempted || (self::firewallRuleExists('People360 LAN') && self::firewallRuleExists('People360 LAN HTTP'))) {
            return;
        }
        $attempted = true;

        $commands = self::windowsFirewallCommands();
        foreach ($commands as $command) {
            exec($command);
        }

        if (self::firewallRuleExists('People360 LAN') && self::firewallRuleExists('People360 LAN HTTP')) {
            return;
        }

        $script = storage_path('app/people360-lan-firewall.cmd');
        File::ensureDirectoryExists(dirname($script));
        File::put($script, "@echo off\r\n".implode("\r\n", $commands)."\r\n");

        $powershell = 'Start-Process -FilePath '.escapeshellarg($script).' -Verb RunAs -WindowStyle Hidden';
        exec('powershell.exe -NoProfile -ExecutionPolicy Bypass -Command '.escapeshellarg($powershell));
    }

    /**
     * @return list<string>
     */
    private static function windowsFirewallCommands(): array
    {
        $udp = (int) config('people360_lan.udp_port');
        $http = (int) config('people360_lan.http_port');
        $commands = [
            'netsh advfirewall firewall delete rule name="People360 LAN"',
            'netsh advfirewall firewall delete rule name="People360 LAN HTTP"',
            'netsh advfirewall firewall delete rule name="People360 LAN PHP"',
            'netsh advfirewall firewall add rule name="People360 LAN" dir=in action=allow protocol=UDP localport='.$udp.' profile=any enable=yes',
            'netsh advfirewall firewall add rule name="People360 LAN HTTP" dir=in action=allow protocol=TCP localport='.$http.' profile=any enable=yes',
        ];

        foreach (self::windowsPhpBinaries() as $binary) {
            $commands[] = 'netsh advfirewall firewall add rule name="People360 LAN PHP" dir=in action=allow program='.$binary.' protocol=UDP profile=any enable=yes';
            $commands[] = 'netsh advfirewall firewall add rule name="People360 LAN PHP" dir=in action=allow program='.$binary.' protocol=TCP profile=any enable=yes';
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    private static function windowsPhpBinaries(): array
    {
        $paths = [PHP_BINARY, dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'php-win.exe', dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'php.exe'];
        $quoted = [];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }
            $quoted[] = '"'.str_replace('"', '', $path).'"';
        }

        return array_values(array_unique($quoted));
    }

    private static function firewallRuleExists(string $name): bool
    {
        $output = [];
        $code = 1;
        exec('netsh advfirewall firewall show rule name="'.str_replace('"', '', $name).'"', $output, $code);

        return $code === 0;
    }
}
