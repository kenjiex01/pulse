<?php

namespace Tests\Unit;

use App\Services\DesktopGitHubReleaseService;
use Tests\TestCase;

class DesktopGitHubReleaseServiceTest extends TestCase
{
    public function test_writes_electron_updater_feeds_for_exe_and_mac_zip(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'people360-gh-'.uniqid('', true);
        mkdir($dir);

        try {
            file_put_contents($dir.'/People360-1.0.0-setup.exe', 'windows-installer');
            file_put_contents($dir.'/People360-1.0.0-arm64.zip', 'mac-zip');

            $written = app(DesktopGitHubReleaseService::class)->writeFeedFiles($dir, '1.0.0');

            $this->assertCount(2, $written);
            $this->assertFileExists($dir.'/latest.yml');
            $this->assertFileExists($dir.'/latest-mac.yml');

            $win = (string) file_get_contents($dir.'/latest.yml');
            $mac = (string) file_get_contents($dir.'/latest-mac.yml');

            $this->assertStringContainsString('version: 1.0.0', $win);
            $this->assertStringContainsString('People360-1.0.0-setup.exe', $win);
            $this->assertStringContainsString('version: 1.0.0', $mac);
            $this->assertStringContainsString('People360-1.0.0-arm64.zip', $mac);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    public function test_github_updater_defaults_match_pulse_repo(): void
    {
        $this->assertSame('github', config('nativephp.updater.default'));
        $this->assertSame('kenjiex01', config('nativephp.updater.providers.github.owner'));
        $this->assertSame('pulse', config('nativephp.updater.providers.github.repo'));
        $this->assertSame('release', config('nativephp.updater.providers.github.releaseType'));
        $this->assertTrue((bool) config('nativephp.updater.providers.github.private'));
        $this->assertTrue((bool) config('nativephp.updater.providers.github.vPrefixedTagName'));
        $this->assertSame('v1.0.0', app(DesktopGitHubReleaseService::class)->tag('1.0.0'));
    }
}
