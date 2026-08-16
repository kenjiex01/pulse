<?php

namespace Tests\Unit;

use Tests\TestCase;

class DesktopInstallerUpdateConfigTest extends TestCase
{
    public function test_artifact_patterns_match_pulse_dist_filenames(): void
    {
        $patterns = config('desktop_updater.artifacts');

        $this->assertMatchesRegularExpression($patterns['win-x64'], 'Pulse-0.1.46-setup.exe');
        $this->assertMatchesRegularExpression($patterns['mac-arm64'], 'Pulse-0.1.46-arm64.dmg');
        $this->assertMatchesRegularExpression($patterns['mac-x64'], 'Pulse-0.1.46-x64.dmg');

        $this->assertSame(1, preg_match($patterns['win-x64'], 'Pulse-0.1.46-setup.exe', $m));
        $this->assertSame('0.1.46', $m[1]);
    }

    public function test_default_prefix_is_payroll_installer(): void
    {
        $this->assertSame('payroll_installer', config('desktop_updater.s3_prefix'));
    }
}
