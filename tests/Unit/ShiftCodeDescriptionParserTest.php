<?php

namespace Tests\Unit;

use App\Support\ShiftCodeDescriptionParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftCodeDescriptionParserTest extends TestCase
{
    #[Test]
    public function it_parses_standard_am_to_pm_descriptions(): void
    {
        $this->assertSame(
            ['time_in' => '07:00', 'time_out' => '16:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('7:00 am to 4:00 pm duty'),
        );

        $this->assertSame(
            ['time_in' => '08:00', 'time_out' => '17:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('8:00 am to 5:00 pm duty'),
        );

        $this->assertSame(
            ['time_in' => '09:00', 'time_out' => '18:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('9:00am - 6:00pm duty'),
        );

        $this->assertSame(
            ['time_in' => '10:00', 'time_out' => '19:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('10:00am - 7:00pm duty'),
        );
    }

    #[Test]
    public function it_parses_afternoon_and_night_descriptions(): void
    {
        $this->assertSame(
            ['time_in' => '11:00', 'time_out' => '20:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('11:00am - 8:00pm duty'),
        );

        $this->assertSame(
            ['time_in' => '12:00', 'time_out' => '21:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('12:00nn to 9:00pm duty'),
        );

        $this->assertSame(
            ['time_in' => '13:00', 'time_out' => '22:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('1:00pm - 10:00pm duty'),
        );

        $this->assertSame(
            ['time_in' => '14:00', 'time_out' => '23:00'],
            ShiftCodeDescriptionParser::parseDutySchedule('2:00pm - 11:00pm duty'),
        );
    }

    #[Test]
    public function it_skips_flexi_descriptions(): void
    {
        $this->assertNull(ShiftCodeDescriptionParser::parseDutySchedule('Flexi-time (8 hrs/day)'));
    }
}
