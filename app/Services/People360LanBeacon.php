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

        File::put(storage_path('app/people360-lan.heartbeat'), (string) time());

        if (in_array('people360:lan-serve', $_SERVER['argv'] ?? [], true)) {
            return;
        }

        $port = (int) config('people360_lan.http_port');
        $probe = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.2);
        if (is_resource($probe)) {
            fclose($probe);

            return;
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

    public static function allowWindowsFirewall(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $udp = (int) config('people360_lan.udp_port');
        $http = (int) config('people360_lan.http_port');
        $rules = [
            'People360 LAN' => 'protocol=UDP localport='.$udp,
            'People360 LAN HTTP' => 'protocol=TCP localport='.$http,
        ];

        foreach ($rules as $name => $spec) {
            exec('netsh advfirewall firewall show rule name="'.$name.'"', $output, $code);

            if ($code === 0) {
                continue;
            }

            exec('netsh advfirewall firewall add rule name="'.$name.'" dir=in action=allow '.$spec.' profile=any enable=yes');
        }
    }
}
