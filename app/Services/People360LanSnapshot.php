<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

class People360LanSnapshot
{
    public function __construct(private readonly People360LanIdentity $identity) {}

    public function exportTo(string $destination): void
    {
        $source = $this->identity->databasePath();

        if (! is_file($source)) {
            throw new RuntimeException('This People360 database file was not found.');
        }

        File::ensureDirectoryExists(dirname($destination));

        if (is_file($destination)) {
            File::delete($destination);
        }

        $pdo = new PDO('sqlite:'.$source, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $quoted = str_replace("'", "''", $destination);
        $pdo->exec("VACUUM INTO '{$quoted}'");
        $pdo = null;

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Could not copy the People360 database.');
        }
    }

    public function replaceDatabase(string $uploadedPath): void
    {
        if (! is_file($uploadedPath) || filesize($uploadedPath) === 0) {
            throw new RuntimeException('The uploaded database file is empty.');
        }

        $destination = $this->identity->databasePath();
        File::ensureDirectoryExists(dirname($destination));

        $safety = $destination.'.lan-safety-'.now()->format('YmdHis');
        if (is_file($destination)) {
            if (! File::copy($destination, $safety)) {
                throw new RuntimeException('Could not save a safety copy of the current database.');
            }
        }

        $temporary = $destination.'.lan-incoming';
        if (! File::copy($uploadedPath, $temporary)) {
            throw new RuntimeException('Could not stage the incoming database.');
        }

        if (is_file($destination) && ! File::delete($destination)) {
            File::delete($temporary);
            throw new RuntimeException('The database file is in use on that computer. Close other work and try again.');
        }

        if (! File::move($temporary, $destination)) {
            throw new RuntimeException('Could not replace the database file.');
        }

        foreach ([$destination.'-wal', $destination.'-shm'] as $sidecar) {
            if (is_file($sidecar)) {
                File::delete($sidecar);
            }
        }
    }
}
