<?php

namespace App\Console\Commands;

use App\Services\DesktopGitHubReleaseService;
use Illuminate\Console\Command;

class PublishDesktopGitHubReleaseCommand extends Command
{
    protected $signature = 'desktop:publish-github-release
                            {--app-version= : App version (defaults to NATIVEPHP_APP_VERSION)}
                            {--dist= : Dist directory (defaults to base_path/dist)}
                            {--dry-run : List planned GitHub release assets without uploading}';

    protected $description = 'Publish People360 installers + latest.yml to a GitHub Release for NativePHP auto-update';

    public function handle(DesktopGitHubReleaseService $releases): int
    {
        $version = trim((string) ($this->option('app-version') ?: config('nativephp.version', '')));
        $version = ltrim($version, 'vV');

        if ($version === '') {
            $this->error('Missing version. Pass --app-version= or set NATIVEPHP_APP_VERSION.');

            return self::FAILURE;
        }

        $dist = (string) ($this->option('dist') ?: base_path('dist'));
        $dryRun = (bool) $this->option('dry-run');

        $feeds = $releases->writeFeedFiles($dist, $version);
        foreach ($feeds as $feed) {
            $this->line('  wrote: '.$feed);
        }

        $assets = $releases->releaseAssets($dist, $version);

        if ($assets === []) {
            $this->error('No installers found in '.$dist.' for version '.$version);

            return self::FAILURE;
        }

        try {
            $result = $releases->publish($version, $assets, $dryRun);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').($result['created'] ? 'Creating' : 'Updating').' GitHub release '.$result['tag'].' on '.$result['repository']);
        foreach ($result['assets'] as $asset) {
            $this->line('  '.($dryRun ? 'would upload' : 'uploaded').': '.basename((string) $asset));
        }

        return self::SUCCESS;
    }
}
