<?php

namespace App\Services\Reports;

use App\Models\DeductionType;
use App\Models\GovtTableSss;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollIncome;
use App\Models\Report;
use App\Models\User;
use App\Support\SssDeductionTypes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SssContributionReportService
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
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

    public function downloadExcel(ReportGenerationResult $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SSS Contribution');

        $row = 1;
        $sheet->setCellValue("A{$row}", (string) ($result->meta['company_name'] ?? config('app.name')));
        $row++;

        if (! empty($result->meta['company_address'])) {
            $sheet->setCellValue("A{$row}", (string) $result->meta['company_address']);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'SOCIAL SECURITY SYSTEM [SSS] MONTHLY CONTRIBUTION');
        $row++;
        $sheet->setCellValue("A{$row}", (string) ($result->meta['period_label'] ?? ''));
        $row += 2;

        $sheet->fromArray([$result->headers], null, "A{$row}");
        $headerRow = $row;
        $row++;
        $sheet->fromArray($result->rows, null, "A{$row}");

        $lastCol = chr(ord('A') + max(count($result->headers) - 1, 0));
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}".($headerRow + count($result->rows)))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D{$row}:{$lastCol}".($headerRow + count($result->rows)))
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $writer = new Xlsx($spreadsheet);
        $filename = 'SSS_Contribution_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|float|null>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $batchIds = collect($options['payroll_batch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $batches = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details.employee',
                'details.deductions.deductionType',
            ])
            ->whereIn('payroll_batch_id', $batchIds)
            ->where('payroll_batch_status_id', PayrollBatchStatus::PROCESSED)
            ->get();

        if ($batches->isEmpty()) {
            return [
                'headers' => $this->headers(),
                'rows' => [],
                'meta' => [
                    'layout' => 'sss',
                    'batch_count' => 0,
                    'employee_count' => 0,
                    'company_name' => config('app.name'),
                    'company_address' => '',
                    'period_label' => '',
                    'batch_labels' => [],
                ],
            ];
        }

        $this->assertSamePayMonthAndYear($batches);

        $calendar = $batches->first()?->payrollCalendar;
        $payYear = (int) ($calendar?->pay_year ?? 0);
        $calendarMonth = (int) ($calendar?->calendar_month ?? 0);

        $sssTypeId = (int) DeductionType::query()
            ->where('deduction_type_code', SssDeductionTypes::PREMIUM)
            ->value('deduction_type_id');
        $mpfTypeId = (int) DeductionType::query()
            ->where('deduction_type_code', SssDeductionTypes::MPF)
            ->value('deduction_type_id');

        $byEmployee = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! $this->detailIsVisible($detail, $user)) {
                    continue;
                }

                $employee = $detail->employee;

                if ($employee === null) {
                    continue;
                }

                $employeeId = (int) $detail->employee_id;

                if (! isset($byEmployee[$employeeId])) {
                    $byEmployee[$employeeId] = [
                        'employee_id' => $employeeId,
                        'sss_number' => (string) ($employee->sss_number ?: ''),
                        'employee_name' => $this->formatEmployeeName($employee->last_name, $employee->first_name, $employee->middle_name, $employee->suffix),
                        'ss_employee' => 0.0,
                        'ss_employer_with_ec' => 0.0,
                        'mpf_employee' => 0.0,
                        'mpf_employer' => 0.0,
                    ];
                }

                foreach ($detail->deductions as $deduction) {
                    $typeId = (int) $deduction->deduction_type_id;
                    $ee = (float) $deduction->employee_amount;
                    $er = (float) $deduction->employer_amount;

                    if ($typeId === $sssTypeId) {
                        $byEmployee[$employeeId]['ss_employee'] += $ee;
                        $byEmployee[$employeeId]['ss_employer_with_ec'] += $er;
                    } elseif ($typeId === $mpfTypeId) {
                        $byEmployee[$employeeId]['mpf_employee'] += $ee;
                        $byEmployee[$employeeId]['mpf_employer'] += $er;
                    }
                }
            }
        }

        foreach ($byEmployee as $employeeId => $row) {
            $byEmployee[$employeeId]['taxable'] = $this->monthlyTaxableForEmployee(
                $employeeId,
                $payYear,
                $calendarMonth,
                $batches->pluck('payroll_batch_id')->all(),
            );
        }

        $rows = collect($byEmployee)
            ->map(function (array $row) {
                $ec = $this->employerEcForGross((float) $row['taxable']);
                $ssEmployer = round(max(0, (float) $row['ss_employer_with_ec'] - $ec), 2);
                $ssEmployee = round((float) $row['ss_employee'], 2);
                $mpfEmployee = round((float) $row['mpf_employee'], 2);
                $mpfEmployer = round((float) $row['mpf_employer'], 2);
                $ssTotal = round($ssEmployee + $ssEmployer, 2);
                $grandTotal = round($ssTotal + $ec + $mpfEmployee + $mpfEmployer, 2);

                if ($ssEmployee <= 0 && $ssEmployer <= 0 && $ec <= 0 && $mpfEmployee <= 0 && $mpfEmployer <= 0) {
                    return null;
                }

                return [
                    'sss_number' => $row['sss_number'] !== '' ? $row['sss_number'] : '—',
                    'employee_name' => $row['employee_name'],
                    'ss_employee' => $ssEmployee,
                    'ss_employer' => $ssEmployer,
                    'ss_total' => $ssTotal,
                    'ec' => $ec,
                    'mpf_employee' => $mpfEmployee,
                    'mpf_employer' => $mpfEmployer,
                    'grand_total' => $grandTotal,
                ];
            })
            ->filter()
            ->sortBy('employee_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $formattedRows = [];
        $line = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                $row['sss_number'],
                $line++,
                $row['employee_name'],
                $this->money($row['ss_employee']),
                $this->money($row['ss_employer']),
                $this->money($row['ss_total']),
                $this->money($row['ec']),
                $this->money($row['mpf_employee']),
                $this->money($row['mpf_employer']),
                $this->money($row['grand_total']),
            ];
        }

        $periodLabel = $calendarMonth > 0 && $payYear > 0
            ? date('F Y', mktime(0, 0, 0, $calendarMonth, 1, $payYear))
            : '';

        return [
            'headers' => $this->headers(),
            'rows' => $formattedRows,
            'meta' => [
                'layout' => 'sss',
                'batch_count' => $batches->count(),
                'employee_count' => count($formattedRows),
                'company_name' => config('app.name'),
                'company_address' => '',
                'period_label' => $periodLabel,
                'pay_year' => $payYear,
                'calendar_month' => $calendarMonth,
                'batch_labels' => $batches
                    ->map(fn (PayrollBatch $batch) => $this->batchOptions->batchLabel($batch))
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'SSS No.',
            'No.',
            'Employee',
            'SS Employee',
            'SS Employer',
            'SS Total',
            'EC',
            'MPF Employee',
            'MPF Employer',
            'Grand Total',
        ];
    }

    /**
     * @param  Collection<int, PayrollBatch>  $batches
     */
    private function assertSamePayMonthAndYear(Collection $batches): void
    {
        $keys = $batches
            ->map(function (PayrollBatch $batch) {
                $calendar = $batch->payrollCalendar;

                return sprintf('%s-%s', $calendar?->pay_year, $calendar?->calendar_month);
            })
            ->unique()
            ->values();

        if ($keys->count() > 1) {
            throw ValidationException::withMessages([
                'payroll_batch_ids' => 'Selected payroll batches must share the same pay month and pay year.',
            ]);
        }
    }

    /**
     * @param  list<int>  $batchIds
     */
    private function monthlyTaxableForEmployee(int $employeeId, int $payYear, int $calendarMonth, array $batchIds): float
    {
        if ($payYear <= 0 || $calendarMonth <= 0 || $batchIds === []) {
            return 0.0;
        }

        $total = (float) PayrollIncome::query()
            ->whereHas('payrollBatchDetail', function ($detailQuery) use ($employeeId, $payYear, $calendarMonth, $batchIds) {
                $detailQuery
                    ->where('employee_id', $employeeId)
                    ->whereIn('payroll_batch_id', $batchIds)
                    ->whereHas('payrollBatch.payrollCalendar', function ($calendarQuery) use ($payYear, $calendarMonth) {
                        $calendarQuery
                            ->where('pay_year', $payYear)
                            ->where('calendar_month', $calendarMonth);
                    });
            })
            ->sum('taxable');

        return round($total, 2);
    }

    private function employerEcForGross(float $grossTaxable): float
    {
        if ($grossTaxable <= 0) {
            return 0.0;
        }

        $row = GovtTableSss::query()
            ->where('compensation_from', '<=', $grossTaxable)
            ->where('compensation_to', '>=', $grossTaxable)
            ->orderBy('compensation_from')
            ->first();

        return round((float) ($row->employer_ec ?? 0), 2);
    }

    private function detailIsVisible(PayrollBatchDetail $detail, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! (bool) ($detail->employee?->is_confidential);
    }

    private function formatEmployeeName(?string $last, ?string $first, ?string $middle, ?string $suffix): string
    {
        $last = trim((string) $last);
        $first = trim((string) $first);
        $middle = trim((string) $middle);
        $suffix = trim((string) $suffix);

        $given = trim(implode(' ', array_filter([$first, $middle, $suffix])));

        if ($last === '') {
            return $given !== '' ? $given : '—';
        }

        return $given !== '' ? "{$last}, {$given}" : $last;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
