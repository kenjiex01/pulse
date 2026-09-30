<?php

namespace Tests\Unit;

use App\Services\TimekeepingEmployeeProfileUploadService;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class TimekeepingEmployeeProfileUploadServiceTest extends TestCase
{
    public function test_utf8_bom_on_first_header_row_does_not_fail_alias_match(): void
    {
        $service = app(TimekeepingEmployeeProfileUploadService::class);

        $csv = "\xEF\xBB\xBF".implode(',', $service->fieldAliases())."\n"
            .implode(',', $service->fieldHeaders())."\n"
            .implode(',', array_fill(0, count($service->fieldAliases()), 'note'))."\n";

        $path = tempnam(sys_get_temp_dir(), 'profile-upload-');
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'profile.csv', 'text/csv', null, true);

        try {
            $service->parseUploadedFile($file);
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString(
                'Fields from the uploaded file do not match the template.',
                $exception->getMessage(),
            );

            return;
        }

        $this->assertTrue(true);
    }
}
