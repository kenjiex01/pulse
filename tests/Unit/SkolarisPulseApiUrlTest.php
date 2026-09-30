<?php

namespace Tests\Unit;

use App\Support\SkolarisPulseApiUrl;
use Tests\TestCase;

class SkolarisPulseApiUrlTest extends TestCase
{
    public function test_normalizes_skolaris_frontend_pulse_api_bridge(): void
    {
        $this->assertSame(
            SkolarisPulseApiUrl::DEFAULT_DIRECT_BASE,
            SkolarisPulseApiUrl::normalize('https://skolaris.icct.edu.ph/pulse/api'),
        );
    }

    public function test_preserves_direct_backend_url(): void
    {
        $direct = 'https://api-skolaris.icct.edu.ph/api/v1/pulse-api/v1';

        $this->assertSame($direct, SkolarisPulseApiUrl::normalize($direct));
        $this->assertSame($direct, SkolarisPulseApiUrl::normalize($direct.'/'));
    }

    public function test_empty_falls_back_to_default(): void
    {
        $this->assertSame(SkolarisPulseApiUrl::DEFAULT_DIRECT_BASE, SkolarisPulseApiUrl::normalize(''));
        $this->assertSame(SkolarisPulseApiUrl::DEFAULT_DIRECT_BASE, SkolarisPulseApiUrl::normalize(null));
    }
}
