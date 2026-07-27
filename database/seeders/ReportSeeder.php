<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\ReportClassification;
use App\Models\ReportFileType;
use App\Models\ReportGroup;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $payroll = ReportClassification::query()->updateOrCreate(
            ['code' => ReportClassification::CODE_PAYROLL],
            ['name' => 'Payroll', 'sort_order' => 1, 'is_active' => true],
        );

        ReportClassification::query()->updateOrCreate(
            ['code' => ReportClassification::CODE_TIMEKEEPING],
            ['name' => 'Timekeeping', 'sort_order' => 2, 'is_active' => false],
        );

        ReportClassification::query()->updateOrCreate(
            ['code' => ReportClassification::CODE_HUMAN_RESOURCE],
            ['name' => 'Human Resource', 'sort_order' => 3, 'is_active' => false],
        );

        $currentBatch = ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'name' => 'Current Batch Reports',
            ],
            ['sort_order' => 1, 'is_active' => true],
        );

        ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'name' => 'Posted Batch Reports',
            ],
            ['sort_order' => 2, 'is_active' => true],
        );

        ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'name' => 'Others',
            ],
            ['sort_order' => 3, 'is_active' => true],
        );

        $html = ReportFileType::query()->updateOrCreate(
            ['code' => ReportFileType::CODE_HTML],
            [
                'label' => 'On-screen Preview',
                'extension' => 'html',
                'content_type' => 'text/html',
            ],
        );

        $excel = ReportFileType::query()->updateOrCreate(
            ['code' => ReportFileType::CODE_EXCEL],
            [
                'label' => 'Excel',
                'extension' => 'xlsx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );

        $payrollRegister = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'Payroll Register',
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'Detailed payroll register for processed batches.',
                'options_key' => 'payreg',
                'generator_key' => 'payreg',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $payrollRegister->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
        ]);

        $sssContribution = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'SSS Monthly Contribution',
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'SSS / EC / MPF monthly contribution report. Multi-select batches in the same pay month and year; amounts are summed per employee.',
                'options_key' => 'sss',
                'generator_key' => 'sss',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $sssContribution->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
        ]);
    }
}
