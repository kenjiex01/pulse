#!/usr/bin/env php
<?php

/**
 * Rebuild Laravel package manifest for a pruned desktop app copy (no dev packages in vendor).
 *
 * Usage: php scripts/rebuild-desktop-package-manifest.php /path/to/build/app
 */

declare(strict_types=1);

$buildPath = $argv[1] ?? '';

if ($buildPath === '' || ! is_dir($buildPath)) {
    fwrite(STDERR, "Usage: php scripts/rebuild-desktop-package-manifest.php /path/to/build/app\n");
    exit(1);
}

$buildPath = rtrim($buildPath, '/');
$lockPath = $buildPath.'/composer.lock';
$installedPath = $buildPath.'/vendor/composer/installed.json';

if (! is_file($lockPath) || ! is_file($installedPath)) {
    fwrite(STDERR, "Missing composer.lock or vendor/composer/installed.json in build app.\n");
    exit(1);
}

$lock = json_decode((string) file_get_contents($lockPath), true);
$devNames = array_flip(array_map(
    static fn (array $package): string => (string) ($package['name'] ?? ''),
    $lock['packages-dev'] ?? []
));

$installed = json_decode((string) file_get_contents($installedPath), true);
$packages = $installed['packages'] ?? $installed;
$packages = array_values(array_filter(
    $packages,
    static fn (array $package): bool => ! isset($devNames[(string) ($package['name'] ?? '')])
));

if (isset($installed['packages'])) {
    $installed['packages'] = $packages;
} else {
    $installed = $packages;
}

file_put_contents($installedPath, json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

$cacheDir = $buildPath.'/bootstrap/cache';
foreach (['packages.php', 'services.php', 'config.php'] as $file) {
    $path = $cacheDir.'/'.$file;
    if (is_file($path)) {
        unlink($path);
    }
}

require $buildPath.'/vendor/autoload.php';

$manifest = new Illuminate\Foundation\PackageManifest(
    new Illuminate\Filesystem\Filesystem,
    $buildPath,
    $cacheDir.'/packages.php'
);

$manifest->build();

echo "Rebuilt {$cacheDir}/packages.php\n";
