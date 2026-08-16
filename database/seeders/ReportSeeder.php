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

        $timekeeping = ReportClassification::query()->updateOrCreate(
            ['code' => ReportClassification::CODE_TIMEKEEPING],
            ['name' => 'Timekeeping', 'sort_order' => 2, 'is_active' => true],
        );

        ReportClassification::query()->updateOrCreate(
            ['code' => ReportClassification::CODE_HUMAN_RESOURCE],
            ['name' => 'Human Resource', 'sort_order' => 3, 'is_active' => true],
        );

        $humanResource = ReportClassification::query()
            ->where('code', ReportClassification::CODE_HUMAN_RESOURCE)
            ->firstOrFail();

        $hrReports = ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $humanResource->report_classification_id,
                'name' => 'Employee Reports',
            ],
            ['sort_order' => 1, 'is_active' => true],
        );

        $currentBatch = ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'name' => 'Current Batch Reports',
            ],
            ['sort_order' => 1, 'is_active' => true],
        );

        $postedBatch = ReportGroup::query()->updateOrCreate(
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

        $pdf = ReportFileType::query()->updateOrCreate(
            ['code' => ReportFileType::CODE_PDF],
            [
                'label' => 'PDF',
                'extension' => 'pdf',
                'content_type' => 'application/pdf',
            ],
        );

        $payrollRegister = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'Payroll Register',
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'Detailed payroll register for processed/posted batches. Choose Staff or Admin; Excel uses the ICCT days-based staff layout with one worksheet per pay period.',
                'options_key' => 'payreg',
                'generator_key' => 'payreg',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $payrollRegister->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
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
            $pdf->report_file_type_id,
        ]);

        $philhealthContribution = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'PhilHealth Contribution',
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'PhilHealth contribution payment return (ICCT layout). Multi-select batches in the same pay month and year; amounts are summed per employee.',
                'options_key' => 'phil',
                'generator_key' => 'phil',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        $philhealthContribution->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $pagibigContribution = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'Pag-IBIG Contribution',
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'Pag-IBIG fund contribution report (ICCT layout). Multi-select batches in the same pay month and year; amounts are summed per employee.',
                'options_key' => 'pagibig',
                'generator_key' => 'pagibig',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        $pagibigContribution->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $birTaxWithheld = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => "BIR Employees' Tax Withheld",
            ],
            [
                'report_group_id' => $currentBatch->report_group_id,
                'description' => 'Employees tax withheld worksheet (MWE / taxable income / deminimis). Multi-select batches in the same pay month and year.',
                'options_key' => 'bir-tax',
                'generator_key' => 'bir-tax',
                'sort_order' => 5,
                'is_active' => true,
            ],
        );

        $birTaxWithheld->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $payslip = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'Payslip',
            ],
            [
                'report_group_id' => $postedBatch->report_group_id,
                'description' => 'Employee payslip from a posted payroll batch. Only income and deduction types with amounts are shown.',
                'options_key' => 'payslip',
                'generator_key' => 'payslip',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $payslip->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $bir1601c = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'BIR Form 1601-C',
            ],
            [
                'report_group_id' => $postedBatch->report_group_id,
                'description' => 'Monthly remittance return of income taxes withheld on compensation (official-like). Multi-select posted batches in the same pay month and year; amounts are summed per employee.',
                'options_key' => 'bir-1601c',
                'generator_key' => 'bir-1601c',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $bir1601c->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $bir2316 = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'BIR Form 2316',
            ],
            [
                'report_group_id' => $postedBatch->report_group_id,
                'description' => 'Certificate of compensation payment / tax withheld per employee (official-like). Select posted batch and employees before generate.',
                'options_key' => 'bir-2316',
                'generator_key' => 'bir-2316',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        $bir2316->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $alphalist = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $payroll->report_classification_id,
                'title' => 'Alphalist',
            ],
            [
                'report_group_id' => $postedBatch->report_group_id,
                'description' => 'BIR Alphalist (Schedules 7.1, 7.3, 7.4, 7.5). Select a payroll year; amounts are YTD from all posted batches. Excel only.',
                'options_key' => 'alphalist',
                'generator_key' => 'alphalist',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        $alphalist->fileTypes()->sync([
            $excel->report_file_type_id,
        ]);

        $historicalData = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $humanResource->report_classification_id,
                'title' => 'Historical Data',
            ],
            [
                'report_group_id' => $hrReports->report_group_id,
                'description' => 'Employee change history with previous and new field values from system logs.',
                'options_key' => 'historical-data',
                'generator_key' => 'historical-data',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $historicalData->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);

        $attendanceReports = ReportGroup::query()->updateOrCreate(
            [
                'report_classification_id' => $timekeeping->report_classification_id,
                'name' => 'Attendance Reports',
            ],
            ['sort_order' => 1, 'is_active' => true],
        );

        $attendanceView = Report::query()->updateOrCreate(
            [
                'report_classification_id' => $timekeeping->report_classification_id,
                'title' => 'Attendance View',
            ],
            [
                'report_group_id' => $attendanceReports->report_group_id,
                'description' => 'Daily attendance view for a date range. Select employees to include; PDF matches Employee Profile Attendance View.',
                'options_key' => 'attendance-view',
                'generator_key' => 'attendance-view',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $attendanceView->fileTypes()->syncWithoutDetaching([
            $html->report_file_type_id,
            $excel->report_file_type_id,
            $pdf->report_file_type_id,
        ]);
    }
}
