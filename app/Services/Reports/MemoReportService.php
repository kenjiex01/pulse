<?php

namespace App\Services\Reports;

use App\Models\CompanyDocumentSendLog;
use App\Models\Employee;
use App\Models\Report;
use App\Models\TimekeepingMemoSendLog;
use App\Models\User;
use App\Services\CompanyDocumentOffenseMemoService;
use App\Support\SpreadsheetDownload;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoReportService
{
    public function __construct(
        private readonly CompanyDocumentOffenseMemoService $offenseMemoService,
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
        $sheet->setTitle('Memo Send Log');

        $sheet->fromArray([$result->title], null, 'A1');

        if (! empty($result->meta['filter_summary'])) {
            $sheet->fromArray([(string) $result->meta['filter_summary']], null, 'A2');
        }

        $sheet->fromArray($result->headers, null, 'A4');
        $sheet->fromArray($result->rows, null, 'A5');

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Memo_Send_Log_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|null>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $formIds = collect($options['company_document_form_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $dateFrom = (string) $options['date_from'];
        $dateTo = (string) $options['date_to'];

        $headers = [
            'Sent Date/Time',
            'Employee No.',
            'Employee Name',
            'Company Document',
            'Offense Frequency',
            'Source',
            'Sent By',
        ];

        $rows = [];

        CompanyDocumentSendLog::query()
            ->with(['employee', 'form', 'sender', 'submission.values'])
            ->whereIn('company_document_form_id', $formIds)
            ->whereDate('sent_at', '>=', $dateFrom)
            ->whereDate('sent_at', '<=', $dateTo)
            ->orderByDesc('sent_at')
            ->get()
            ->each(function (CompanyDocumentSendLog $log) use (&$rows, $user): void {
                if (! $this->employeeIsVisible($log->employee, $user)) {
                    return;
                }

                $rows[] = $this->mapRow($log, 'Company Documents');
            });

        TimekeepingMemoSendLog::query()
            ->with(['employee', 'form', 'sender', 'submission.values'])
            ->whereIn('company_document_form_id', $formIds)
            ->whereDate('sent_at', '>=', $dateFrom)
            ->whereDate('sent_at', '<=', $dateTo)
            ->orderByDesc('sent_at')
            ->get()
            ->each(function (TimekeepingMemoSendLog $log) use (&$rows, $user): void {
                if (! $this->employeeIsVisible($log->employee, $user)) {
                    return;
                }

                $rows[] = $this->mapRow($log, 'Timekeeping');
            });

        usort($rows, fn (array $left, array $right) => strcmp((string) $right[0], (string) $left[0]));

        return [
            'headers' => $headers,
            'rows' => $rows,
            'meta' => [
                'filter_summary' => $this->filterSummary($options, $formIds, count($rows)),
                'row_count' => count($rows),
            ],
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function mapRow(Model $log, string $source): array
    {
        $offenseFrequency = $this->offenseMemoService->offenseFrequencyLabelForSendLog(
            $log->form,
            $log->employee,
            $log->submission,
            $log->sent_at,
        );

        return [
            $log->sent_at?->format('Y-m-d H:i:s') ?? '',
            (string) ($log->employee?->employee_number ?? '—'),
            (string) ($log->employee?->full_name ?? '—'),
            (string) ($log->form?->name ?? '—'),
            $offenseFrequency !== '' ? $offenseFrequency : '—',
            $source,
            (string) ($log->sender?->name ?? 'System'),
        ];
    }

    private function employeeIsVisible(?Employee $employee, User $user): bool
    {
        if ($employee === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return ! (bool) $employee->is_confidential;
    }

    /**
     * @param  array<int, int>  $formIds
     */
    private function filterSummary(array $options, array $formIds, int $rowCount): string
    {
        $parts = ["{$rowCount} send row(s)"];
        $parts[] = count($formIds).' selected memo template(s)';
        $parts[] = 'date range '.($options['date_from'] ?? '…').' to '.($options['date_to'] ?? '…');

        return implode(' · ', $parts);
    }
}
