<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\User;
use App\Support\Bir2316FormMapper;
use App\Support\BirFormSettings;
use App\Support\GovernmentIdNumbers;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Bir2316ReportService
{
    public function __construct(
        private readonly BirFormEmployeeSelection $selection,
        private readonly Bir2316OfficialPdfWriter $officialPdf,
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
        $spreadsheet->removeSheetByIndex(0);

        $certificates = $result->meta['certificates'] ?? [];

        foreach ($certificates as $index => $certificate) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($this->sheetTitle($certificate, $index));
            $this->writeCertificateSheet($sheet, $certificate, $result->meta);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('2316');
            $sheet->setCellValue('A1', 'No certificate data found.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'BIR_2316_'.now()->format('Ymd_His'),
        );
    }

    /**
     * Official BIR 2316 (September 2021 ENCS) stamped PDF.
     */
    public function downloadOfficialPdf(ReportGenerationResult $result, bool $inline = false): Response
    {
        $binary = $this->officialPdf->write(
            $result->meta['certificates'] ?? [],
            $result->meta,
        );

        $filename = 'BIR_2316_'.now()->format('Ymd_His').'.pdf';
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
        $resolved = $this->selection->resolveForYear($options, $user);
        $bir = BirFormSettings::all();
        $companyTin = (string) $bir['company_tin'];
        $employer = [
            'name' => (string) $bir['company_name'],
            'tin' => $companyTin,
            'tin_formatted' => GovernmentIdNumbers::format($companyTin, GovernmentIdNumbers::TYPE_TIN),
            'address' => (string) $bir['company_address'],
            'rdo_code' => (string) $bir['company_rdo_code'],
            'zip' => (string) $bir['company_zip'],
        ];

        $certificates = [];
        foreach ($resolved['lines'] as $line) {
            $from = $resolved['period_from'] !== '' ? Carbon::parse($resolved['period_from']) : null;
            $to = $resolved['period_to'] !== '' ? Carbon::parse($resolved['period_to']) : null;
            $form = Bir2316FormMapper::map($line);

            $certificates[] = [
                ...$line,
                ...$form,
                'for_year' => (string) ($resolved['pay_year'] ?: ($from?->year ?? '')),
                'period_from_mm' => $from?->format('m') ?? '',
                'period_from_dd' => $from?->format('d') ?? '',
                'period_to_mm' => $to?->format('m') ?? '',
                'period_to_dd' => $to?->format('d') ?? '',
                'period_from' => $resolved['period_from'],
                'period_to' => $resolved['period_to'],
                'period_label' => $resolved['period_label'],
                'month_of_return' => $resolved['month_of_return'],
                'batch_label' => $resolved['batch_label'],
                'employee_rdo' => (string) $bir['company_rdo_code'],
                'smw_rate_per_day' => (float) $bir['smw_rate_per_day'],
                'smw_rate_per_month' => (float) $bir['smw_rate_per_month'],
                'statutory_contributions' => round(
                    (float) $line['sss_contribution']
                    + (float) $line['philhealth_contribution']
                    + (float) $line['pagibig_contribution'],
                    2,
                ),
            ];
        }

        $rows = array_map(fn (array $certificate) => [
            (string) $certificate['employee_number'],
            (string) $certificate['employee_name'],
            (string) ($certificate['tin_formatted'] !== '' ? $certificate['tin_formatted'] : $certificate['tin']),
            $this->money((float) $certificate['gross_compensation']),
            $this->money((float) $certificate['tax_withheld']),
        ], $certificates);

        return [
            'headers' => ['Employee No.', 'Employee Name', 'TIN', 'Gross Compensation', 'Tax Withheld'],
            'rows' => $rows,
            'meta' => [
                'layout' => 'bir_2316_official',
                'uses_official_pdf' => true,
                'form_number' => '2316',
                'form_title' => 'Certificate of Compensation Payment / Tax Withheld',
                'period_label' => $resolved['period_label'],
                'period_from' => $resolved['period_from'],
                'period_to' => $resolved['period_to'],
                'batch_label' => $resolved['batch_label'],
                'certificates' => $certificates,
                'employee_count' => count($certificates),
                'employer' => $employer,
                'signatory_name' => (string) $bir['signatory_name'],
                'signatory_title' => (string) $bir['signatory_title'],
                'disclaimer' => 'Official BIR 2316 (September 2021 ENCS) blank template with field stamping. Amounts are from the selected posted payroll batch.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $certificate
     * @param  array<string, mixed>  $meta
     */
    private function writeCertificateSheet($sheet, array $certificate, array $meta): void
    {
        $employer = $meta['employer'] ?? [];

        $row = 1;
        foreach ([
            'Republic of the Philippines',
            'Department of Finance',
            'Bureau of Internal Revenue',
            'BIR Form No. 2316',
            'Certificate of Compensation Payment / Tax Withheld',
        ] as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setBold($row >= 4);
            $row++;
        }

        $row++;
        $sheet->setCellValue("A{$row}", 'Part I — Employer / Withholding Agent');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", 'Registered Name');
        $sheet->setCellValue("B{$row}", (string) ($employer['name'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'TIN');
        $sheet->setCellValue("B{$row}", (string) (($employer['tin_formatted'] ?? '') !== ''
            ? $employer['tin_formatted']
            : ($employer['tin'] ?? '')));
        $row++;
        $sheet->setCellValue("A{$row}", 'Registered Address');
        $sheet->setCellValue("B{$row}", (string) ($employer['address'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'RDO Code');
        $sheet->setCellValue("B{$row}", (string) ($employer['rdo_code'] ?? ''));

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Part II — Employee / Payee');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", "Employee's Name");
        $sheet->setCellValue("B{$row}", (string) ($certificate['employee_name'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Employee No.');
        $sheet->setCellValue("B{$row}", (string) ($certificate['employee_number'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'TIN');
        $sheet->setCellValueExplicit(
            "B{$row}",
            (string) ($certificate['tin'] ?? ''),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
        );
        $row++;
        $sheet->setCellValue("A{$row}", 'Address');
        $sheet->setCellValue("B{$row}", (string) ($certificate['address'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Date of Birth');
        $sheet->setCellValue("B{$row}", (string) ($certificate['birth_date_display'] ?? ''));

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Part III — Compensation & Tax Withheld (Selected Batch Period)');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", 'Period Covered');
        $sheet->setCellValue("B{$row}", (string) ($certificate['period_label'] ?? ''));
        $row++;
        $sheet->setCellValue("A{$row}", 'Gross Compensation');
        $sheet->setCellValue("B{$row}", (float) ($certificate['gross_compensation'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'Non-Taxable / Exempt');
        $sheet->setCellValue("B{$row}", (float) ($certificate['non_taxable_compensation'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'Taxable Compensation');
        $sheet->setCellValue("B{$row}", (float) ($certificate['taxable_compensation'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'Tax Withheld');
        $sheet->setCellValue("B{$row}", (float) ($certificate['tax_withheld'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'SSS (Employee)');
        $sheet->setCellValue("B{$row}", (float) ($certificate['sss_contribution'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'PhilHealth (Employee)');
        $sheet->setCellValue("B{$row}", (float) ($certificate['philhealth_contribution'] ?? 0));
        $row++;
        $sheet->setCellValue("A{$row}", 'Pag-IBIG (Employee)');
        $sheet->setCellValue("B{$row}", (float) ($certificate['pagibig_contribution'] ?? 0));

        $amountStart = $row - 6;
        $sheet->getStyle("B{$amountStart}:B{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $sheet->getStyle("A".($row - 10).":B{$row}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Authorized Signatory');
        $sheet->setCellValue("B{$row}", (string) ($meta['signatory_name'] ?? ''));
        $row++;
        $sheet->setCellValue("B{$row}", (string) ($meta['signatory_title'] ?? ''));

        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * @param  array<string, mixed>  $certificate
     */
    private function sheetTitle(array $certificate, int $index): string
    {
        $number = trim((string) ($certificate['employee_number'] ?? ''));
        $base = $number !== '' ? $number : 'Emp'.($index + 1);
        $base = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $base) ?? 'Emp';

        return mb_substr($base, 0, 31);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
