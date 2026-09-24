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
            $command = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' people360:lan-serve';
            pclose(popen($command, 'r'));

            return;
        }

        $command = escapeshellarg($php).' '.escapeshellarg($artisan).' people360:lan-serve >> '.escapeshellarg($log).' 2>&1 &';
        exec($command);
    }
}
