<?php

namespace Tests\Unit;

use App\Services\DesktopUpdaterService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DesktopUpdaterServiceTest extends TestCase
{
    public function test_status_tracks_download_and_pending_update(): void
    {
        Cache::flush();

        config([
            'nativephp.updater.enabled' => true,
            'nativephp-internal.running' => true,
            'nativephp.version' => '1.0.0',
            'desktop_updater.force_install' => false,
        ]);

        $service = app(DesktopUpdaterService::class);

        $service->markAvailable('1.0.2');
        $this->assertSame('1.0.2', $service->status()['downloading']['version']);

        $service->markProgress(42.5);
        $this->assertSame(42.5, $service->status()['downloading']['percent']);

        $service->markDownloaded('1.0.2', 'People360 1.0.2');
        $status = $service->status();

        $this->assertNull($status['downloading']);
        $this->assertSame('1.0.2', $status['pending']['version']);
        $this->assertSame('People360 1.0.2', $status['pending']['release_name']);
        $this->assertTrue($status['enabled']);
        $this->assertSame('1.0.0', $status['version']);
    }

    public function test_status_clears_stale_pending_when_already_on_newer_version(): void
    {
        Cache::flush();

        config([
            'nativephp.updater.enabled' => true,
            'nativephp-internal.running' => true,
            'nativephp.version' => '1.0.3',
            'desktop_updater.force_install' => false,
        ]);

        Cache::put(DesktopUpdaterService::CACHE_PENDING, [
            'version' => '1.0.2',
            'release_name' => 'old',
        ], now()->addDay());

        $status = app(DesktopUpdaterService::class)->status();

        $this->assertNull($status['pending']);
        $this->assertNull($status['downloading']);
        $this->assertSame('1.0.3', $status['version']);
    }

    public function test_mark_downloaded_ignores_same_or_older_version(): void
    {
        Cache::flush();

        config([
            'nativephp.updater.enabled' => true,
            'nativephp-internal.running' => true,
            'nativephp.version' => '1.0.3',
            'desktop_updater.force_install' => true,
        ]);

        $service = app(DesktopUpdaterService::class);
        $service->markDownloaded('1.0.2', 'stale');

        $this->assertNull($service->status()['pending']);
    }

    public function test_disabled_outside_native_desktop(): void
    {
        config([
            'nativephp.updater.enabled' => true,
            'nativephp-internal.running' => false,
        ]);

        $this->assertFalse(app(DesktopUpdaterService::class)->enabled());
    }
}
