<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\Report;
use App\Models\TeachingLoadSession;
use App\Models\User;
use App\Support\PayrollBatchNetPayLines;
use App\Support\PayslipFilename;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipReportService
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
        private readonly PayrollContributionBatchSupport $batchSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Report $report, array $options, User $user): ReportGenerationResult
    {
        $dataset = $this->buildDataset($options, $user);

        return new ReportGenerationResult(
            title: $report->title,
            headers: $dataset['headers'],
            rows: $dataset['rows'],
            meta: $dataset['meta'],
        );
    }

    public function downloadPdfZip(ReportGenerationResult $result): StreamedResponse
    {
        $payslips = $result->meta['payslips'] ?? [];
        $batchLabel = (string) ($result->meta['batch_label'] ?? 'Payslip');
        $companyName = (string) ($result->meta['company_name'] ?? config('app.name'));
        $zipFilename = PayslipFilename::sanitize('Payslip '.$batchLabel.' '.now()->format('Ymd_His')).'.zip';

        return response()->streamDownload(function () use ($payslips, $companyName, $result): void {
            $zipPath = tempnam(sys_get_temp_dir(), 'payslip_zip_');

            if ($zipPath === false) {
                return;
            }

            $zip = new \ZipArchive();
            $usedNames = [];

            if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
                @unlink($zipPath);

                return;
            }

            foreach ($payslips as $payslip) {
                $entryName = PayslipFilename::forPayslip($payslip);

                if (isset($usedNames[$entryName])) {
                    $entryName = PayslipFilename::forPayslipWithSuffix(
                        $payslip,
                        (string) ($payslip['employee_number'] ?? 'duplicate'),
                    );
                }

                $usedNames[$entryName] = true;
                $pdf = $this->renderSinglePayslipPdf($payslip, $companyName, $result->title);
                $zip->addFromString($entryName, $pdf);
            }

            $zip->close();
            readfile($zipPath);
            @unlink($zipPath);
        }, $zipFilename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayslipForEmployee(int $batchId, int $employeeId, User $user): array
    {
        if ($batchId <= 0) {
            throw ValidationException::withMessages([
                'payroll_batch_id' => 'Please select a posted payroll batch.',
            ]);
        }

        if ($employeeId <= 0) {
            throw ValidationException::withMessages([
                'employee_id' => 'Please select an employee.',
            ]);
        }

        $batch = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details' => fn ($query) => $query->where('employee_id', $employeeId),
                'details.employee.employmentInformations.salary.incomes.incomeType',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
            ])
            ->where('payroll_batch_id', $batchId)
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->first();

        if ($batch === null) {
            throw ValidationException::withMessages([
                'payroll_batch_id' => 'Selected payroll batch must be posted.',
            ]);
        }

        $detail = $batch->details->firstWhere('employee_id', $employeeId);

        if ($detail === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Employee is not in the selected payroll batch.',
            ]);
        }

        if (! $this->batchSupport->detailIsVisible($detail, $user)) {
            throw ValidationException::withMessages([
                'employee_id' => 'You do not have permission to view this employee payslip.',
            ]);
        }

        $payslip = $this->buildPayslip($detail, $batch);

        if ($payslip === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'No payslip data found for this employee.',
            ]);
        }

        return $payslip;
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    public function renderPayslipPdf(array $payslip, string $title = 'Payslip'): string
    {
        $companyName = (string) config('payslip_report.company_name', config('app.name'));

        return $this->renderSinglePayslipPdf($payslip, $companyName, $title);
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    private function renderSinglePayslipPdf(array $payslip, string $companyName, string $title): string
    {
        $preview = [
            'title' => $title,
            'headers' => [],
            'rows' => [],
            'meta' => [
                'layout' => 'payslip',
                'payslips' => [$payslip],
                'company_name' => $companyName,
            ],
        ];

        $html = view('payroll.reports.pdf-document', ['preview' => $preview])->render();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function downloadExcel(ReportGenerationResult $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($result->meta['payslips'] ?? [] as $index => $payslip) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($this->sheetTitle($payslip, $index));
            $this->writePayslipSheet($sheet, $payslip);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('Payslip');
            $sheet->setCellValue('A1', 'No payslip data found.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Payslip_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $batchId = (int) ($options['payroll_batch_id'] ?? 0);
        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($batchId <= 0) {
            throw ValidationException::withMessages([
                'payroll_batch_id' => 'Please select a posted payroll batch.',
            ]);
        }

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Please select at least one employee.',
            ]);
        }

        $batch = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details' => fn ($query) => $query->whereIn('employee_id', $employeeIds),
                'details.employee.employmentInformations.salary.incomes.incomeType',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
            ])
            ->where('payroll_batch_id', $batchId)
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->first();

        if ($batch === null) {
            throw ValidationException::withMessages([
                'payroll_batch_id' => 'Selected payroll batch must be posted.',
            ]);
        }

        $payslips = [];

        foreach ($batch->details as $detail) {
            if (! in_array((int) $detail->employee_id, $employeeIds, true)) {
                continue;
            }

            if (! $this->batchSupport->detailIsVisible($detail, $user)) {
                continue;
            }

            $payslip = $this->buildPayslip($detail, $batch);

            if ($payslip !== null) {
                $payslips[] = $payslip;
            }
        }

        usort($payslips, fn (array $left, array $right) => strcasecmp($left['employee_name'], $right['employee_name']));

        $rows = array_map(fn (array $payslip) => [
            $payslip['employee_number'],
            $payslip['employee_name'],
            $this->money($payslip['total_earnings']),
            $this->money($payslip['total_deductions']),
            $this->money($payslip['net_pay']),
        ], $payslips);

        return [
            'headers' => ['Employee No.', 'Employee Name', 'Total Earnings', 'Total Deductions', 'Net Pay'],
            'rows' => $rows,
            'meta' => [
                'layout' => 'payslip',
                'payslips' => $payslips,
                'batch_label' => $this->batchOptions->batchLabel($batch),
                'employee_count' => count($payslips),
                'company_name' => (string) config('payslip_report.company_name', config('app.name')),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPayslip(PayrollBatchDetail $detail, PayrollBatch $batch): ?array
    {
        $employee = $detail->employee;

        if ($employee === null) {
            return null;
        }

        $layoutType = $this->resolveLayoutType($employee);
        $incomeLines = PayrollBatchNetPayLines::incomeLines($detail->incomes);
        $deductionRows = PayrollBatchNetPayLines::deductionRows($detail->deductions);
        $earnings = PayrollBatchNetPayLines::payslipEarningsLines($incomeLines);
        $deductions = PayrollBatchNetPayLines::payslipDeductionLines($deductionRows);

        if ($earnings === [] && $deductions === []) {
            return null;
        }

        $totalEarnings = round(collect($earnings)->sum('amount'), 2);
        $totalDeductions = round(collect($deductions)->sum('amount'), 2);
        $netPay = round($totalEarnings - $totalDeductions, 2);
        $calendar = $batch->payrollCalendar;

        return [
            'employee_id' => (int) $employee->employee_id,
            'employee_number' => (string) ($employee->employee_number ?? ''),
            'employee_name' => (string) $employee->full_name,
            'last_name' => (string) ($employee->last_name ?? ''),
            'first_name' => (string) ($employee->first_name ?? ''),
            'middle_name' => (string) ($employee->middle_name ?? ''),
            'faculty_label' => (string) ($employee->user_type_label ?? ''),
            'layout_type' => $layoutType,
            'pay_period' => $calendar ? $this->formatPeriodLabel($calendar) : '',
            'pay_date' => $this->resolvePayDate($calendar),
            'total_hours' => $layoutType === 'faculty' ? $this->resolveTotalHours($detail) : null,
            'days_present' => $layoutType === 'staff' ? $this->resolveDaysPresent($detail) : null,
            'loading_schedule' => $layoutType === 'faculty' ? $this->resolveLoadingSchedule($employee, $calendar) : null,
            'daily_rate' => $layoutType === 'faculty' ? $this->resolveDailyRate($detail, $employee) : null,
            'new_rate' => $layoutType === 'staff' ? $this->resolveNewRate($detail, $employee) : null,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'is_confidential' => (bool) ($employee->is_confidential ?? false),
        ];
    }

    private function resolveLayoutType(Employee $employee): string
    {
        return $employee->isFaculty() ? 'faculty' : 'staff';
    }

    private function resolveDaysPresent(PayrollBatchDetail $detail): ?float
    {
        $bascDays = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC')
            ->sum(fn ($income) => (float) ($income->days ?? 0));

        if ($bascDays <= 0) {
            $bascDays = $detail->incomes->sum(fn ($income) => (float) ($income->days ?? 0));
        }

        return $bascDays > 0 ? round($bascDays, 4) : null;
    }

    private function resolveNewRate(PayrollBatchDetail $detail, Employee $employee): ?float
    {
        $bascLines = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC');

        $days = $bascLines->sum(fn ($income) => (float) ($income->days ?? 0));
        $amount = $bascLines->sum(fn ($income) => (float) $income->taxable + (float) $income->non_taxable);

        if ($days > 0 && $amount > 0) {
            return round($amount / $days, 2);
        }

        $salary = $employee->employmentInformations?->sortBy('sort_order')->first()?->salary;
        $basicAmount = $salary?->basicIncomeAmount() ?? 0.0;
        $daysPerPeriod = (float) ($salary?->days_per_period ?? 0);

        if ($basicAmount > 0 && $daysPerPeriod > 0) {
            return round($basicAmount / $daysPerPeriod, 2);
        }

        return null;
    }

    private function resolveLoadingSchedule(Employee $employee, $calendar): ?float
    {
        if ($calendar?->dt_from === null || $calendar?->dt_to === null) {
            return null;
        }

        $hours = TeachingLoadSession::query()
            ->where('employee_id', $employee->employee_id)
            ->whereBetween('session_date', [$calendar->dt_from->toDateString(), $calendar->dt_to->toDateString()])
            ->sum('total_hours');

        return $hours > 0 ? round((float) $hours, 2) : null;
    }

    private function resolveTotalHours(PayrollBatchDetail $detail): ?float
    {
        $hours = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC')
            ->sum(fn ($income) => (float) ($income->hours ?? 0));

        if ($hours <= 0) {
            $hours = $detail->incomes->sum(fn ($income) => (float) ($income->hours ?? 0));
        }

        return $hours > 0 ? round($hours, 4) : null;
    }

    private function resolveHourlyRate(PayrollBatchDetail $detail, $employee): ?float
    {
        $bascLines = $detail->incomes
            ->filter(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')) === 'BASC');

        $hours = $bascLines->sum(fn ($income) => (float) ($income->hours ?? 0));
        $amount = $bascLines->sum(fn ($income) => (float) $income->taxable + (float) $income->non_taxable);

        if ($hours > 0 && $amount > 0) {
            return round($amount / $hours, 2);
        }

        $salary = $employee->employmentInformations?->sortBy('sort_order')->first()?->salary;

        return $salary?->hourlyRate();
    }

    /**
     * Daily rate for faculty/hybrid payslips: hourly rate × hours per day from salary setup.
     */
    private function resolveDailyRate(PayrollBatchDetail $detail, $employee): ?float
    {
        $hourlyRate = $this->resolveHourlyRate($detail, $employee);

        if ($hourlyRate === null) {
            return null;
        }

        $salary = $employee->employmentInformations?->sortBy('sort_order')->first()?->salary;
        $hoursPerDay = (float) ($salary?->hours_per_day ?? 0);

        if ($hoursPerDay <= 0) {
            return null;
        }

        return round($hourlyRate * $hoursPerDay, 2);
    }

    private function formatPeriodLabel($calendar): string
    {
        $from = $calendar->dt_from;
        $to = $calendar->dt_to;

        if ($from === null || $to === null) {
            return '';
        }

        if ($from->format('F Y') === $to->format('F Y')) {
            return $from->format('F j').' - '.$to->format('j, Y');
        }

        return $from->format('M j, Y').' - '.$to->format('M j, Y');
    }

    private function resolvePayDate($calendar): string
    {
        if ($calendar?->dt_to === null) {
            return '';
        }

        return $calendar->dt_to->copy()->addDays(10)->format('F j, Y');
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    private function writePayslipSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $payslip): void
    {
        $company = (string) config('payslip_report.company_name', config('app.name'));
        $isStaff = ($payslip['layout_type'] ?? 'faculty') === 'staff';

        $sheet->mergeCells('E1:M1');
        $sheet->setCellValue('E1', $company);
        $sheet->mergeCells('E2:M2');
        $sheet->setCellValue('E2', 'PAYSLIP');
        $sheet->mergeCells('E3:M3');
        $sheet->setCellValue('E3', ($payslip['faculty_label'] ?? '') !== '' ? $payslip['faculty_label'].': '.$payslip['employee_name'] : $payslip['employee_name']);
        $sheet->setCellValue('E4', 'Pay Period: '.($payslip['pay_period'] ?? ''));

        if ($isStaff) {
            $sheet->setCellValue('K4', 'Days Present: '.($payslip['days_present'] ?? ''));
        } else {
            $sheet->setCellValue('E5', 'Total Hours Present: '.($payslip['total_hours'] ?? ''));

            if (($payslip['loading_schedule'] ?? null) !== null) {
                $sheet->setCellValue('E6', 'Loading Schedule: '.($payslip['loading_schedule'] ?? ''));
            }
        }

        $sheet->setCellValue('A11', 'Pay Period:');
        $sheet->setCellValue('B11', $payslip['pay_period'] ?? '');
        $sheet->setCellValue('A12', 'Pay Date:');
        $sheet->setCellValue('B12', $payslip['pay_date'] ?? '');
        $sheet->setCellValue('A13', 'Employee:');
        $sheet->setCellValue('B13', $payslip['employee_name'] ?? '');
        $sheet->setCellValue('A14', 'Net Pay:');
        $sheet->setCellValue('B14', $payslip['net_pay'] ?? 0);

        if ($isStaff) {
            $sheet->setCellValue('E11', 'Earnings');
            $sheet->setCellValue('F11', 'Hours');
            $sheet->setCellValue('G11', 'Days');
            $sheet->setCellValue('H11', 'Amount');
            $sheet->setCellValue('J11', 'Deductions');
            $sheet->setCellValue('K11', 'Mins');
            $sheet->setCellValue('L11', 'Amount');
        } else {
            $sheet->setCellValue('E11', 'Earnings');
            $sheet->setCellValue('F11', 'Hours');
            $sheet->setCellValue('G11', 'Days');
            $sheet->setCellValue('H11', 'Amount');
            $sheet->setCellValue('J11', 'Deductions');
            $sheet->setCellValue('K11', 'Mins');
            $sheet->setCellValue('L11', 'Amount');
        }

        $row = 12;
        $lineCount = max(count($payslip['earnings'] ?? []), count($payslip['deductions'] ?? []));

        for ($index = 0; $index < $lineCount; $index++) {
            $earning = $payslip['earnings'][$index] ?? null;
            $deduction = $payslip['deductions'][$index] ?? null;

            if ($earning !== null) {
                $sheet->setCellValue("E{$row}", $earning['label']);
                $sheet->setCellValue("F{$row}", $earning['hours'] ?? '');
                $sheet->setCellValue("G{$row}", $earning['days'] ?? '');
                $sheet->setCellValue("H{$row}", $earning['amount']);
            }

            if ($deduction !== null) {
                $sheet->setCellValue("J{$row}", $deduction['label']);
                $sheet->setCellValue("K{$row}", isset($deduction['minutes']) ? (int) $deduction['minutes'] : '');
                $sheet->setCellValue("L{$row}", $deduction['amount']);
            }

            $row++;
        }

        if ($isStaff) {
            $sheet->setCellValue('E'.($row + 1), 'New rate:');
            $sheet->setCellValue('H'.($row + 1), $payslip['new_rate'] ?? '');
            $sheet->setCellValue('E'.($row + 2), 'Total Earnings:');
            $sheet->setCellValue('H'.($row + 2), $payslip['total_earnings'] ?? 0);
            $sheet->setCellValue('J'.($row + 2), 'Total Deductions:');
            $sheet->setCellValue('L'.($row + 2), $payslip['total_deductions'] ?? 0);
            $sheet->setCellValue('K'.($row + 3), 'Net Pay:');
            $sheet->setCellValue('L'.($row + 3), $payslip['net_pay'] ?? 0);
        } else {
            $sheet->setCellValue('E'.($row + 1), 'Daily Rate:');
            $sheet->setCellValue('H'.($row + 1), $payslip['daily_rate'] ?? '');
            $sheet->setCellValue('E'.($row + 2), 'Total Earnings:');
            $sheet->setCellValue('H'.($row + 2), $payslip['total_earnings'] ?? 0);
            $sheet->setCellValue('J'.($row + 2), 'Total Deductions:');
            $sheet->setCellValue('L'.($row + 2), $payslip['total_deductions'] ?? 0);
            $sheet->setCellValue('K'.($row + 3), 'Net Pay:');
            $sheet->setCellValue('L'.($row + 3), $payslip['net_pay'] ?? 0);
        }

        $lastRow = $row + 3;

        $sheet->getStyle('E1:M6')->getFont()->setBold(true);
        $sheet->getStyle('E11:K11')->getFont()->setBold(true);
        $sheet->getStyle('E1:M'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('G12:K'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(2);
        $sheet->getColumnDimension('D')->setWidth(2);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(2);
        $sheet->getColumnDimension('I')->setWidth(22);
        $sheet->getColumnDimension('J')->setWidth(8);
        $sheet->getColumnDimension('K')->setWidth(12);
        $sheet->getColumnDimension('L')->setWidth(12);
        $sheet->getColumnDimension('M')->setWidth(2);

        $sheet->setShowGridlines(false);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $pageSetup->setPrintArea('A1:M'.$lastRow);
        $pageSetup->setHorizontalCentered(true);
        $pageSetup->setVerticalCentered(false);

        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setRight(0.3)
            ->setLeft(0.3)
            ->setBottom(0.5);
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    private function sheetTitle(array $payslip, int $index): string
    {
        $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', '-', (string) ($payslip['employee_name'] ?? 'Employee')) ?: 'Employee';

        return mb_substr(trim($name), 0, 31) ?: 'Payslip'.($index + 1);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
