<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class DesktopGitHubReleaseService
{
    public function repository(): string
    {
        $owner = trim((string) config('nativephp.updater.providers.github.owner', ''));
        $repo = trim((string) config('nativephp.updater.providers.github.repo', ''));

        if ($owner !== '' && $repo !== '') {
            return $owner.'/'.$repo;
        }

        return 'kenjiex01/pulse';
    }

    public function tag(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        return 'v'.$version;
    }

    /**
     * Write electron-updater feed files next to the built installers.
     *
     * @return list<string> Absolute paths written
     */
    public function writeFeedFiles(string $dist, string $version): array
    {
        $version = ltrim(trim($version), 'vV');
        $basename = (string) config('desktop_updater.installer_basename', 'People360');
        $written = [];

        $exe = $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-setup.exe';
        $zipArm = $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-arm64.zip';
        $zipX64 = $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-x64.zip';
        $macZip = is_file($zipArm) ? $zipArm : (is_file($zipX64) ? $zipX64 : null);

        if (is_file($exe)) {
            $path = $dist.DIRECTORY_SEPARATOR.'latest.yml';
            file_put_contents($path, $this->electronLatestYml($version, basename($exe), $exe));
            $written[] = $path;
        }

        if (is_string($macZip)) {
            $path = $dist.DIRECTORY_SEPARATOR.'latest-mac.yml';
            file_put_contents($path, $this->electronLatestYml($version, basename($macZip), $macZip));
            $written[] = $path;
        }

        return $written;
    }

    /**
     * Installers + feed files to attach to the GitHub release.
     *
     * @return list<string>
     */
    public function releaseAssets(string $dist, string $version): array
    {
        $version = ltrim(trim($version), 'vV');
        $basename = (string) config('desktop_updater.installer_basename', 'People360');
        $names = [
            $basename.'-'.$version.'-setup.exe',
            $basename.'-'.$version.'-setup.exe.blockmap',
            $basename.'-'.$version.'-arm64.zip',
            $basename.'-'.$version.'-arm64.zip.blockmap',
            $basename.'-'.$version.'-arm64.dmg',
            $basename.'-'.$version.'-x64.zip',
            $basename.'-'.$version.'-x64.dmg',
            'latest.yml',
            'latest-mac.yml',
        ];

        $assets = [];
        foreach ($names as $name) {
            $path = $dist.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                $assets[] = $path;
            }
        }

        return $assets;
    }

    /**
     * Create or update the GitHub Release for this version (Skolaris-style feed).
     *
     * @param  list<string>  $assets
     * @return array{repository: string, tag: string, created: bool, assets: list<string>}
     */
    public function publish(string $version, array $assets, bool $dryRun = false): array
    {
        $version = ltrim(trim($version), 'vV');
        $tag = $this->tag($version);
        $repository = $this->repository();
        $title = (string) config('app.name', 'People360').' '.$version;

        if ($assets === []) {
            throw new \RuntimeException('No desktop installer assets found to publish.');
        }

        if ($dryRun) {
            return [
                'repository' => $repository,
                'tag' => $tag,
                'created' => ! $this->releaseExists($repository, $tag),
                'assets' => $assets,
            ];
        }

        $exists = $this->releaseExists($repository, $tag);

        if (! $exists) {
            $this->runGh([
                'release', 'create', $tag,
                '--repo', $repository,
                '--title', $title,
                '--notes', $this->releaseNotes($version),
                ...$assets,
            ]);
        } else {
            $this->runGh([
                'release', 'upload', $tag,
                '--repo', $repository,
                '--clobber',
                ...$assets,
            ]);
        }

        return [
            'repository' => $repository,
            'tag' => $tag,
            'created' => ! $exists,
            'assets' => $assets,
        ];
    }

    public function electronLatestYml(string $version, string $filename, string $localPath): string
    {
        $size = (int) filesize($localPath);
        $sha512 = base64_encode((string) hash_file('sha512', $localPath, true));
        $releaseDate = now()->toIso8601String();

        return implode("\n", [
            'version: '.$version,
            'files:',
            '  - url: '.$filename,
            '    sha512: '.$sha512,
            '    size: '.$size,
            'path: '.$filename,
            'sha512: '.$sha512,
            "releaseDate: '".$releaseDate."'",
            '',
        ]);
    }

    private function releaseNotes(string $version): string
    {
        $name = (string) config('app.name', 'People360');

        return $name.' '.$version."\n\nDesktop auto-update release. Installed apps check this GitHub release on launch.";
    }

    private function releaseExists(string $repository, string $tag): bool
    {
        $result = Process::timeout(60)->run([
            'gh', 'release', 'view', $tag, '--repo', $repository,
        ]);

        return $result->successful();
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runGh(array $arguments): void
    {
        $result = Process::timeout(600)->run(['gh', ...$arguments]);

        if ($result->failed()) {
            throw new \RuntimeException(
                'GitHub release failed: '.trim($result->errorOutput() ?: $result->output())
            );
        }
    }
}
