<?php

namespace Tests\Unit;

use App\Services\Reports\PayrollRegisterExcelExporter;
use Tests\TestCase;

class PayrollRegisterExcelExporterTest extends TestCase
{
    public function test_group_rows_by_campus_sheet_keeps_all_sheets_and_defaults_unknown(): void
    {
        $exporter = new PayrollRegisterExcelExporter();

        $groups = $exporter->groupRowsByCampusSheet([
            ['employee_name' => 'A', 'campus_sheet' => 'Antipolo'],
            ['employee_name' => 'B', 'campus_sheet' => 'Taytay'],
            ['employee_name' => 'C', 'campus_sheet' => 'Unknown'],
            ['employee_name' => 'D'],
        ]);

        $this->assertSame([
            'Angono',
            'Antipolo',
            'Binangonan',
            'Cogeo',
            'San Mateo',
            'Sumulong',
            'Taytay',
            'Digital',
            'Greenhills',
            'N. Domingo',
            'Washington Residences',
            'Bldg 108',
            '225 6th Floor',
            'Cainta',
            'Unknown',
        ], array_keys($groups));

        $this->assertCount(1, $groups['Antipolo']);
        $this->assertCount(1, $groups['Taytay']);
        $this->assertCount(1, $groups['Unknown']);
        $this->assertCount(1, $groups['Cainta']);
        $this->assertSame(1, $groups['Cainta'][0]['index']);
        $this->assertSame('D', $groups['Cainta'][0]['employee_name']);
        $this->assertSame('C', $groups['Unknown'][0]['employee_name']);
        $this->assertSame([], $groups['Binangonan']);
        $this->assertSame([], $groups['Cogeo']);
    }

    public function test_stream_omits_empty_campus_worksheets(): void
    {
        $exporter = new PayrollRegisterExcelExporter();
        $groups = $exporter->groupRowsByCampusSheet([
            ['employee_name' => 'A', 'campus_sheet' => 'Antipolo', 'index' => 1],
            ['employee_name' => 'B', 'campus_sheet' => 'Taytay', 'index' => 1],
        ]);

        $nonEmpty = array_keys(array_filter($groups, fn (array $rows) => $rows !== []));

        $this->assertSame(['Antipolo', 'Taytay'], $nonEmpty);
    }

    public function test_group_rows_by_period_sheet_uses_period_keys(): void
    {
        $exporter = new PayrollRegisterExcelExporter();

        $groups = $exporter->groupRowsByPeriodSheet([
            ['employee_name' => 'A', 'period_sheet' => '27-10'],
            ['employee_name' => 'B', 'period_sheet' => '11-26'],
            ['employee_name' => 'C', 'period_sheet' => '27-10'],
            ['employee_name' => 'D'],
        ]);

        $this->assertSame(['27-10', '11-26', 'Register'], array_keys($groups));
        $this->assertCount(2, $groups['27-10']);
        $this->assertCount(1, $groups['11-26']);
        $this->assertCount(1, $groups['Register']);
        $this->assertSame(1, $groups['27-10'][0]['index']);
        $this->assertSame(2, $groups['27-10'][1]['index']);
    }
}
