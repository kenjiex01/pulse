<?php

namespace App\Console\Commands;

use App\Services\DesktopInstallerUpdateService;
use Illuminate\Console\Command;

class UploadDesktopInstallersCommand extends Command
{
    protected $signature = 'desktop:upload-installers
                            {--app-version= : App version (defaults to NATIVEPHP_APP_VERSION)}
                            {--dist= : Dist directory (defaults to base_path/dist)}
                            {--dry-run : List planned uploads without writing to S3}';

    protected $description = 'Upload People360 desktop installers (EXE/DMG/ZIP) plus latest.json / latest.yml to S3 payroll_installer/';

    public function handle(DesktopInstallerUpdateService $updater): int
    {
        $bucket = trim((string) config('backup.cloud.bucket', ''));
        $key = trim((string) config('backup.cloud.key', ''));
        $secret = trim((string) config('backup.cloud.secret', ''));

        if ($bucket === '' || $key === '' || $secret === '') {
            $this->error('S3 is not configured. Set DB_BACKUP_S3_BUCKET / DB_BACKUP_S3_KEY / DB_BACKUP_S3_SECRET.');

            return self::FAILURE;
        }

        $version = trim((string) ($this->option('app-version') ?: config('nativephp.version', '')));
        $version = ltrim($version, 'vV');

        if ($version === '') {
            $this->error('Missing version. Pass --app-version= or set NATIVEPHP_APP_VERSION.');

            return self::FAILURE;
        }

        $dist = (string) ($this->option('dist') ?: base_path('dist'));
        $basename = (string) config('desktop_updater.installer_basename', 'People360');
        $candidates = [
            $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-setup.exe',
            $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-arm64.dmg',
            $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-arm64.zip',
            $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-x64.dmg',
            $dist.DIRECTORY_SEPARATOR.$basename.'-'.$version.'-x64.zip',
            $dist.DIRECTORY_SEPARATOR.'Pulse-'.$version.'-setup.exe',
            $dist.DIRECTORY_SEPARATOR.'Pulse-'.$version.'-arm64.dmg',
            $dist.DIRECTORY_SEPARATOR.'Pulse-'.$version.'-arm64.zip',
            $dist.DIRECTORY_SEPARATOR.'Pulse-'.$version.'-x64.dmg',
            $dist.DIRECTORY_SEPARATOR.'Pulse-'.$version.'-x64.zip',
        ];

        $existing = array_values(array_filter($candidates, 'is_file'));

        if ($existing === []) {
            $this->error('No installers found in '.$dist.' for version '.$version);
            foreach ($candidates as $path) {
                $this->line('  missing: '.$path);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $prefix = trim((string) config('desktop_updater.s3_prefix', 'payroll_installer'), '/');

        $this->info(($dryRun ? '[dry-run] ' : '').'Uploading People360 '.$version.' → s3://'.$bucket.'/'.$prefix.'/');

        try {
            $result = $updater->uploadInstallers($version, $existing, $dryRun);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['uploaded'] as $uploadedKey) {
            $this->line('  '.($dryRun ? 'would upload' : 'uploaded').': '.$uploadedKey);
        }

        foreach ($result['deleted'] ?? [] as $deletedKey) {
            $this->line('  '.($dryRun ? 'would delete' : 'deleted').': '.$deletedKey);
        }

        if (($result['deleted'] ?? []) === []) {
            $this->line('  '.($dryRun ? 'would delete' : 'deleted').': (none — no older installers under '.$prefix.'/)');
        }

        $this->line('  '.($dryRun ? 'would write' : 'wrote').': '.$result['latest_key']);
        $this->info('Done. S3 keeps only People360 '.$result['version'].' under '.$prefix.'/');

        return self::SUCCESS;
    }
}
