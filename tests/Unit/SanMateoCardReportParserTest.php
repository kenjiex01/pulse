<?php

namespace Tests\Unit;

use App\Services\TimeLogsDtr\SanMateoCardReportParser;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SanMateoCardReportParserTest extends TestCase
{
    #[Test]
    public function att_log_report_sheet_parses_every_punch_in_a_day_cell(): void
    {
        $parser = new SanMateoCardReportParser;
        $matrix = [
            ['Attendance Record Report'],
            [],
            ['Att. Time', null, '2026-04-01 ~ 2026-04-30'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
            ['ID:', null, '2', null, null, null, null, null, 'Name:', null, 'Penarubia R'],
            [
                null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null,
                '07:2411:5113:0016:33',
                '07:2312:0013:0016:37',
            ],
        ];

        $rows = $this->invokePrivate($parser, 'parseAttLogReportSheet', [$matrix]);

        $this->assertCount(8, $rows);
        $this->assertSame([
            ['biometric_id' => '2', 'actual_date' => '2026-04-17', 'punch_time' => '07:24:00', 'is_in' => true],
            ['biometric_id' => '2', 'actual_date' => '2026-04-17', 'punch_time' => '11:51:00', 'is_in' => false],
            ['biometric_id' => '2', 'actual_date' => '2026-04-17', 'punch_time' => '13:00:00', 'is_in' => true],
            ['biometric_id' => '2', 'actual_date' => '2026-04-17', 'punch_time' => '16:33:00', 'is_in' => false],
            ['biometric_id' => '2', 'actual_date' => '2026-04-18', 'punch_time' => '07:23:00', 'is_in' => true],
            ['biometric_id' => '2', 'actual_date' => '2026-04-18', 'punch_time' => '12:00:00', 'is_in' => false],
            ['biometric_id' => '2', 'actual_date' => '2026-04-18', 'punch_time' => '13:00:00', 'is_in' => true],
            ['biometric_id' => '2', 'actual_date' => '2026-04-18', 'punch_time' => '16:37:00', 'is_in' => false],
        ], $rows);
    }

    #[Test]
    public function att_log_punches_within_five_minutes_keep_same_in_out_tag(): void
    {
        $parser = new SanMateoCardReportParser;
        $matrix = [
            ['Attendance Record Report'],
            [],
            ['Att. Time', null, '2026-04-01 ~ 2026-04-30'],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
            ['ID:', null, '99', null, null, null, null, null, 'Name:', null, 'Test Employee'],
            [
                null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null,
                '08:0208:0412:0012:02',
            ],
        ];

        $rows = $this->invokePrivate($parser, 'parseAttLogReportSheet', [$matrix]);

        $this->assertSame([
            ['biometric_id' => '99', 'actual_date' => '2026-04-17', 'punch_time' => '08:02:00', 'is_in' => true],
            ['biometric_id' => '99', 'actual_date' => '2026-04-17', 'punch_time' => '08:04:00', 'is_in' => true],
            ['biometric_id' => '99', 'actual_date' => '2026-04-17', 'punch_time' => '12:00:00', 'is_in' => false],
            ['biometric_id' => '99', 'actual_date' => '2026-04-17', 'punch_time' => '12:02:00', 'is_in' => false],
        ], $rows);
    }

    #[Test]
    public function att_log_punches_skip_duplicates_already_parsed_from_card_report_sheets(): void
    {
        $fixture = '/Users/kentordillos/Downloads/1_StandardReport.xls';

        if (! is_readable($fixture)) {
            $this->markTestSkipped('StandardReport.xls fixture is not available on this machine.');
        }

        $format = config('time_logs_dtr.campuses.SA');
        $file = new UploadedFile($fixture, '1_StandardReport.xls', null, null, true);
        $parser = new SanMateoCardReportParser;

        $result = $parser->parse($file, $format);
        $fingerprints = [];

        foreach ($result['rows'] as $row) {
            if (array_key_exists('punch_time', $row)) {
                $fingerprint = implode('|', [
                    $row['biometric_id'],
                    $row['actual_date'],
                    $row['punch_time'],
                    $row['is_in'] ? '1' : '0',
                ]);
            } else {
                $fingerprint = implode('|', [
                    $row['biometric_id'],
                    $row['actual_date'],
                    $row['time_in'],
                    $row['time_out'],
                ]);
            }

            $this->assertArrayNotHasKey($fingerprint, $fingerprints, "Duplicate row detected: {$fingerprint}");
            $fingerprints[$fingerprint] = true;
        }

        $day17Punches = array_values(array_filter(
            $result['rows'],
            fn (array $row) => ($row['biometric_id'] ?? '') === '2'
                && ($row['actual_date'] ?? '') === '2026-04-17'
                && array_key_exists('punch_time', $row),
        ));

        $this->assertCount(2, $day17Punches);
        $this->assertSame('11:51:00', $day17Punches[0]['punch_time']);
        $this->assertFalse($day17Punches[0]['is_in']);
        $this->assertSame('13:00:00', $day17Punches[1]['punch_time']);
        $this->assertTrue($day17Punches[1]['is_in']);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$arguments);
    }
}
