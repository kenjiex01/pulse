<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\User;
use App\Support\Bir1601cFormMapper;
use App\Support\BirFormSettings;
use App\Support\GovernmentIdNumbers;
use App\Support\SpreadsheetDownload;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Bir1601cReportService
{
    public function __construct(
        private readonly BirFormEmployeeSelection $selection,
        private readonly Bir1601cOfficialPdfWriter $officialPdf,
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
        $sheet->setTitle('1601-C');

        $meta = $result->meta;
        $lines = $meta['employee_lines'] ?? [];
        $lastColumn = 'F';

        $row = 1;
        foreach ([
            'Republic of the Philippines',
            'Department of Finance',
            'Bureau of Internal Revenue',
            'BIR Form No. 1601-C',
            'Monthly Remittance Return of Income Taxes Withheld on Compensation',
        ] as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setBold($row >= 4);
            $row++;
        }

        $row++;
        $sheet->setCellValue("A{$row}", 'For the Month of:');
        $sheet->setCellValue("B{$row}", (string) ($meta['month_of_return'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Withholding Agent / Employer:');
        $sheet->setCellValue("B{$row}", (string) ($meta['company_name'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'TIN:');
        $sheet->setCellValue("B{$row}", (string) ($meta['company_tin_formatted'] ?? $meta['company_tin'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Registered Address:');
        $sheet->setCellValue("B{$row}", (string) ($meta['company_address'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'RDO Code:');
        $sheet->setCellValue("B{$row}", (string) ($meta['company_rdo_code'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Payroll Batch:');
        $sheet->setCellValue("B{$row}", (string) ($meta['batch_label'] ?? ''));

        $row += 2;
        $headerRow = $row;
        $headers = ['Seq', 'TIN', 'Name of Payee', 'ATC', 'Tax Base / Compensation', 'Tax Withheld'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, $headerRow], $header);
        }
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true);

        $excelRow = $headerRow + 1;
        $seq = 1;
        foreach ($lines as $line) {
            $sheet->setCellValue("A{$excelRow}", $seq++);
            $sheet->setCellValueExplicit(
                "B{$excelRow}",
                (string) ($line['tin'] ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
            );
            $sheet->setCellValue("C{$excelRow}", $line['employee_name'] ?? '');
            $sheet->setCellValue("D{$excelRow}", (string) ($meta['compensation_atc'] ?? 'WI010'));
            $sheet->setCellValue("E{$excelRow}", (float) ($line['taxable_compensation'] ?? 0));
            $sheet->setCellValue("F{$excelRow}", (float) ($line['tax_withheld'] ?? 0));
            $excelRow++;
        }

        $totalRow = $excelRow;
        $sheet->setCellValue("A{$totalRow}", '');
        $sheet->setCellValue("C{$totalRow}", 'TOTAL');
        $sheet->setCellValue("E{$totalRow}", (float) ($meta['totals']['taxable_compensation'] ?? 0));
        $sheet->setCellValue("F{$totalRow}", (float) ($meta['totals']['tax_withheld'] ?? 0));
        $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->getFont()->setBold(true);

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$totalRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("E".($headerRow + 1).":F{$totalRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $excelRow = $totalRow + 2;
        $sheet->setCellValue("A{$excelRow}", 'Prepared / Authorized Signatory:');
        $sheet->setCellValue("B{$excelRow}", (string) ($meta['signatory_name'] ?? ''));
        $excelRow++;
        $sheet->setCellValue("B{$excelRow}", (string) ($meta['signatory_title'] ?? ''));

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'BIR_1601C_'.now()->format('Ymd_His'),
        );
    }

    /**
     * Official BIR 1601-C (January 2018 ENCS) stamped PDF.
     */
    public function downloadOfficialPdf(ReportGenerationResult $result, bool $inline = false): Response
    {
        $binary = $this->officialPdf->write($result->meta);

        $filename = 'BIR_1601C_'.now()->format('Ymd_His').'.pdf';
        $disposition = $inline ? 'inline' : 'attachment';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $resolved = $this->selection->resolve($options, $user);
        $lines = $resolved['lines'];
        $bir = BirFormSettings::all();
        $atc = (string) $bir['compensation_atc'];

        $includeAnnual13th = filter_var($options['include_annual_13th_month'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $annual13th = 0.0;

        if ($includeAnnual13th) {
            $employeeIds = collect($options['employee_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $yearResolved = $this->selection->resolveForYear([
                'pay_year' => (int) $resolved['pay_year'],
                'employee_ids' => $employeeIds,
            ], $user);
            $annual13th = Bir1601cFormMapper::annual13thMonthFromLines($yearResolved['lines']);
        }

        $totals = Bir1601cFormMapper::totalsFromLines($lines, [
            'include_annual_13th_month' => $includeAnnual13th,
            'annual_13th_month' => $annual13th,
        ]);

        $rows = [];
        $seq = 1;
        foreach ($lines as $line) {
            $rows[] = [
                (string) $seq++,
                (string) ($line['tin_formatted'] !== '' ? $line['tin_formatted'] : $line['tin']),
                (string) $line['employee_name'],
                $atc,
                $this->money($line['taxable_compensation']),
                $this->money($line['tax_withheld']),
            ];
        }

        $companyTin = (string) $bir['company_tin'];

        return [
            'headers' => ['Seq', 'TIN', 'Name of Payee', 'ATC', 'Tax Base / Compensation', 'Tax Withheld'],
            'rows' => $rows,
            'meta' => [
                'layout' => 'bir_1601c_official',
                'uses_official_pdf' => true,
                'form_number' => '1601-C',
                'form_title' => 'Monthly Remittance Return of Income Taxes Withheld on Compensation',
                'month_of_return' => $resolved['month_of_return'],
                'period_label' => $resolved['period_label'],
                'period_from' => $resolved['period_from'],
                'period_to' => $resolved['period_to'],
                'pay_year' => $resolved['pay_year'],
                'calendar_month' => $resolved['calendar_month'],
                'batch_label' => $resolved['batch_label'],
                'batch_count' => (int) ($resolved['batch_count'] ?? 1),
                'employee_lines' => $lines,
                'employee_count' => count($lines),
                'totals' => $totals,
                'include_annual_13th_month' => $includeAnnual13th,
                'annual_13th_month' => $annual13th,
                'compensation_atc' => $atc,
                'company_name' => (string) $bir['company_name'],
                'company_address' => (string) $bir['company_address'],
                'company_tin' => $companyTin,
                'company_tin_formatted' => GovernmentIdNumbers::format($companyTin, GovernmentIdNumbers::TYPE_TIN),
                'company_rdo_code' => (string) $bir['company_rdo_code'],
                'company_zip' => (string) $bir['company_zip'],
                'signatory_name' => (string) $bir['signatory_name'],
                'signatory_title' => (string) $bir['signatory_title'],
                'disclaimer' => 'Official BIR 1601-C (January 2018 ENCS) blank template with field stamping. Amounts are summed from the selected posted payroll batch(es) and employees.',
            ],
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
