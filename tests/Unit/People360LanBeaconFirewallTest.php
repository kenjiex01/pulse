<?php

namespace Tests\Unit;

use App\Services\People360LanBeacon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class People360LanBeaconFirewallTest extends TestCase
{
    protected function tearDown(): void
    {
        $path = storage_path('app/settings/people360-lan-firewall.json');
        if (is_file($path)) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_windows_firewall_ready_is_true_on_non_windows(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Non-Windows only.');
        }

        $this->assertTrue(People360LanBeacon::windowsFirewallReady());
    }

    public function test_allow_without_prompt_does_not_retrigger_elevation_after_prior_prompt_marker(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows-only firewall behavior.');
        }

        $markerPath = storage_path('app/settings/people360-lan-firewall.json');
        File::ensureDirectoryExists(dirname($markerPath));
        File::put($markerPath, json_encode([
            'status' => 'elevation_prompted',
            'elevation_prompted_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        People360LanBeacon::allowWindowsFirewall(promptIfNeeded: false);

        $marker = json_decode((string) File::get($markerPath), true);
        $this->assertSame('elevation_prompted', $marker['status'] ?? null);
    }
}
