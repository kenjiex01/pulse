<?php

namespace Tests;

use App\Support\DesktopConnectivity;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DesktopConnectivity::fake(null);
    }
}
