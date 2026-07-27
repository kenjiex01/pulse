<?php

namespace Tests\Unit;

use App\Services\TimeLogsDtr\CaintaTimesheetReportParser;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaintaTimesheetReportParserTest extends TestCase
{
    #[Test]
    public function it_parses_cainta_timesheet_sample_into_individual_punches(): void
    {
        $parser = new CaintaTimesheetReportParser;
        $path = base_path('docs/samples/cainta-staff-dtr-june-2026-sample.xls');

        $this->assertFileExists($path);

        $file = new UploadedFile($path, 'cainta-staff-dtr-june-2026-sample.xls', null, null, true);
        $parsed = $parser->parse($file);

        $this->assertNotEmpty($parsed['rows']);

        $abiog = collect($parsed['rows'])
            ->where('biometric_id', '8376')
            ->where('actual_date', '2026-06-16')
            ->values()
            ->all();

        $this->assertCount(2, $abiog);
        $this->assertSame([
            ['biometric_id' => '8376', 'employee_name' => 'ABIOG, ANNA RUTH', 'actual_date' => '2026-06-16', 'punch_time' => '07:32:00', 'is_in' => true],
            ['biometric_id' => '8376', 'employee_name' => 'ABIOG, ANNA RUTH', 'actual_date' => '2026-06-16', 'punch_time' => '18:02:00', 'is_in' => false],
        ], $abiog);

        $employeeIds = collect($parsed['rows'])->pluck('biometric_id')->unique()->sort()->values();
        $this->assertGreaterThan(5, $employeeIds->count());
    }

    #[Test]
    public function it_skips_summary_rows_without_treating_them_as_punches(): void
    {
        $parser = new CaintaTimesheetReportParser;
        $path = base_path('docs/samples/cainta-staff-dtr-june-2026-sample.xls');
        $file = new UploadedFile($path, 'cainta-staff-dtr-june-2026-sample.xls', null, null, true);
        $parsed = $parser->parse($file);

        $this->assertFalse(
            collect($parsed['rows'])->contains(fn (array $row) => str_contains((string) ($row['actual_date'] ?? ''), 'Total')),
        );
    }
}
