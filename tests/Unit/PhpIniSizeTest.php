<?php

namespace Tests\Unit;

use App\Support\PhpIniSize;
use Tests\TestCase;

class PhpIniSizeTest extends TestCase
{
    public function test_to_bytes_parses_suffixed_values(): void
    {
        $this->assertSame(20 * 1024 * 1024, PhpIniSize::toBytes('20M'));
        $this->assertSame(256 * 1024 * 1024, PhpIniSize::toBytes('256M'));
    }

    public function test_effective_sql_restore_max_kb_respects_php_ini(): void
    {
        config(['uploads.sql_restore_max_kb' => 524288]);

        $postBytes = PhpIniSize::postMaxBytes();
        $uploadBytes = PhpIniSize::uploadMaxBytes();

        if ($postBytes > 0 && $uploadBytes > 0) {
            $expectedKb = (int) floor(min($postBytes, $uploadBytes) / 1024);
            $this->assertSame(max(1, min(524288, $expectedKb)), PhpIniSize::effectiveSqlRestoreMaxKb());
        } else {
            $this->assertSame(524288, PhpIniSize::effectiveSqlRestoreMaxKb());
        }
    }
}
