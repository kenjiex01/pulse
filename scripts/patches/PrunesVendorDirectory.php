<?php

/**
 * TODO: When more drivers/adapters are added, this should be relocated
 */

namespace Native\Electron\Traits;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Filesystem\Filesystem;

trait PrunesVendorDirectory
{
    abstract protected function buildPath(string $path = ''): string;

    abstract protected function sourcePath(string $path = ''): string;

    protected function pruneVendorDirectory()
    {
        $filesystem = new Filesystem;

        // Avoid `composer install --no-dev` here: NativePHP excludes php-bin from the
        // app copy, then Composer would re-download ~350MB only for us to delete it.
        // Instead, remove require-dev packages from the copied vendor tree and rebuild
        // the production autoloader (no network).
        $lockPath = $this->buildPath('/composer.lock');
        if (is_file($lockPath)) {
            $lock = json_decode((string) file_get_contents($lockPath), true);
            foreach ($lock['packages-dev'] ?? [] as $package) {
                $name = $package['name'] ?? '';
                if ($name === '') {
                    continue;
                }
                $filesystem->remove($this->buildPath('/vendor/'.$name));
            }
        }

        // Dev-only providers must not remain in cached manifests (desktop app would crash on boot).
        $filesystem->remove([
            $this->buildPath('/bootstrap/cache/packages.php'),
            $this->buildPath('/bootstrap/cache/services.php'),
            $this->buildPath('/bootstrap/cache/config.php'),
        ]);

        Process::path($this->buildPath())
            ->timeout(120)
            ->run('composer dump-autoload --no-dev --optimize --no-interaction --no-scripts', function (string $type, string $output) {
                echo $output;
            });

        $rebuildManifest = $this->sourcePath('scripts/rebuild-desktop-package-manifest.php');
        if (is_file($rebuildManifest)) {
            Process::path($this->sourcePath())
                ->timeout(120)
                ->run(
                    'php '.escapeshellarg($rebuildManifest).' '.escapeshellarg($this->buildPath()),
                    function (string $type, string $output) {
                        echo $output;
                    }
                );
        }

        $filesystem->remove([
            $this->buildPath('/vendor/bin'),
            $this->buildPath('/vendor/nativephp/php-bin'),
        ]);

        // Remove custom php binary package directory
        $binaryPackageDirectory = $this->binaryPackageDirectory();
        if (! empty($binaryPackageDirectory) && $filesystem->exists($this->buildPath($binaryPackageDirectory))) {
            $filesystem->remove($this->buildPath($binaryPackageDirectory));
        }
    }
}
