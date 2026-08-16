<?php

namespace Tests\Unit;

use App\Models\PayrollCalendar;
use App\Services\Reports\PayrollRegisterRowBuilder;
use Tests\TestCase;

class PayrollRegisterRowBuilderTest extends TestCase
{
    public function test_format_period_label_matches_icct_sample_style(): void
    {
        $builder = new PayrollRegisterRowBuilder();
        $calendar = new PayrollCalendar([
            'dt_from' => '2026-03-16',
            'dt_to' => '2026-03-31',
            'pay_period' => 1,
        ]);

        $this->assertSame('March 16 - 31, 2026', $builder->formatPeriodLabel($calendar));
    }

    public function test_build_layout_table_uses_icct_headers_and_mapped_values(): void
    {
        $builder = new PayrollRegisterRowBuilder();

        $table = $builder->buildLayoutTable([
            [
                'index' => 1,
                'employee_name' => 'Jane Doe',
                'basc_hours' => 87.97,
                'basc_amount' => 6597.5,
                'overtime_hours' => 2.5,
                'overtime_amount' => 187.5,
            ],
        ]);

        $this->assertSame('#', $table['headers'][0]);
        $this->assertSame('NAME OF FACULTY', $table['headers'][1]);
        $this->assertSame([], $table['highlight_indices']);
        $headerText = implode(' ', $table['headers']);
        $this->assertStringNotContainsString('Jan 16 - 31', $headerText);
        $this->assertStringNotContainsString('May 8-15', $headerText);

        $this->assertSame('1', $table['rows'][0][0]);
        $this->assertSame('Jane Doe', $table['rows'][0][1]);
        $this->assertSame('87.97', $table['rows'][0][7]);
        $this->assertSame('6597.5', $table['rows'][0][8]);
        $this->assertSame('Rate per hour', $table['headers'][3]);
        $this->assertSame('Daily Rate', $table['headers'][4]);
        $this->assertSame('NET PAY', $table['headers'][array_key_last($table['headers'])]);
        $this->assertLessThan(50, count($table['headers']));
        $this->assertNotContains('Payroll Period', $table['headers']);
        $this->assertNotContains('Total Deductions', $table['headers']);
    }

    public function test_sort_register_rows_reindexes_and_sorts_by_employee_number(): void
    {
        $builder = new PayrollRegisterRowBuilder();

        $sorted = $builder->sortRegisterRows([
            ['index' => 1, 'employee_number' => 'B-002', 'employee_name' => 'Bravo'],
            ['index' => 2, 'employee_number' => 'A-001', 'employee_name' => 'Alpha'],
        ], 'employee_number');

        $this->assertSame('A-001', $sorted[0]['employee_number']);
        $this->assertSame(1, $sorted[0]['index']);
        $this->assertSame('B-002', $sorted[1]['employee_number']);
        $this->assertSame(2, $sorted[1]['index']);
    }

    public function test_build_layout_table_uses_staff_headers_when_configured(): void
    {
        $builder = new PayrollRegisterRowBuilder();

        $table = $builder->buildLayoutTable([
            [
                'index' => 1,
                'employee_name' => 'Jane Doe',
                'basic_rate' => 600,
                'days' => 12,
                'total_pay' => 7200,
                'gross_philhealth' => 7200,
            ],
        ], 'payroll_register_staff_layout');

        $this->assertSame('#', $table['headers'][0]);
        $this->assertSame('NAME OF STAFF', $table['headers'][1]);
        $this->assertSame('BASIC', $table['headers'][2]);
        $this->assertSame('GROSS', $table['headers'][20]);
        $this->assertSame([], $table['highlight_indices']);
        $this->assertSame('Jane Doe', $table['rows'][0][1]);
        $this->assertSame('600', $table['rows'][0][2]);
        $this->assertSame('12', $table['rows'][0][3]);
        $this->assertSame('NET PAY', $table['headers'][array_key_last($table['headers'])]);
    }

    public function test_period_sheet_key_matches_staff_workbook_tabs(): void
    {
        $builder = new PayrollRegisterRowBuilder();
        $calendar = new PayrollCalendar([
            'dt_from' => '2026-07-27',
            'dt_to' => '2026-08-10',
        ]);

        $this->assertSame('27-10', $builder->periodSheetKey($calendar));
    }

    public function test_resolve_campus_sheet_maps_named_campuses_and_defaults_to_cainta(): void
    {
        $builder = new PayrollRegisterRowBuilder();

        $antipolo = new \App\Models\Employee;
        $antipolo->setRelation('campus', new \App\Models\Campus([
            'campus_code' => 'UA',
            'campus_name' => 'ICCT Colleges Antipolo Campus',
        ]));

        $angono = new \App\Models\Employee;
        $angono->setRelation('campus', new \App\Models\Campus([
            'campus_code' => 'AG',
            'campus_name' => 'ICCT Colleges Angono Campus',
        ]));

        $this->assertSame('Antipolo', $builder->resolveCampusSheet($antipolo));
        $this->assertSame('Cainta', $builder->resolveCampusSheet($angono));
        $this->assertSame('Cainta', $builder->resolveCampusSheet(null));
    }
}
