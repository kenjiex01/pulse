<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\Report;
use App\Models\SysLog;
use App\Models\User;
use App\Services\EmployeeHistoryService;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoricalDataReportService
{
    public function __construct(
        private readonly EmployeeHistoryService $historyService,
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
        $sheet->setTitle('Historical Data');

        $sheet->fromArray([$result->title], null, 'A1');

        if (! empty($result->meta['filter_summary'])) {
            $sheet->fromArray([(string) $result->meta['filter_summary']], null, 'A2');
        }

        $sheet->fromArray($result->headers, null, 'A4');
        $sheet->fromArray($result->rows, null, 'A5');

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Historical_Data_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|null>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $actions = collect($options['actions'] ?? [])
            ->filter(fn ($action) => in_array($action, ['create', 'update', 'delete'], true))
            ->values()
            ->all();

        if ($actions === []) {
            $actions = ['create', 'update', 'delete'];
        }

        $query = SysLog::query()
            ->where('table_name', 'tbl_employees')
            ->whereIn('action', $actions)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($employeeIds !== []) {
            $query->whereIn('record_id', $employeeIds);
        }

        if (! empty($options['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $options['date_from']);
        }

        if (! empty($options['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $options['date_to']);
        }

        $logs = $query->get();
        $employees = $this->employeesForLogs($logs);

        $headers = [
            'Date / Time',
            'User',
            'Action',
            'Employee No.',
            'Employee Name',
            'Field',
            'Previous Value',
            'New Value',
            'Remarks',
        ];

        $rows = [];

        foreach ($logs as $log) {
            $employee = $employees->get((int) $log->record_id);

            if (! $this->employeeIsVisible($employee, $user)) {
                continue;
            }

            $changes = $this->historyService->changesForLog($log);
            $timestamp = $log->created_at?->format('Y-m-d H:i:s') ?? '';
            $actor = $log->user?->name ?? 'System';
            $actionLabel = $this->historyService->actionLabel((string) $log->action);
            $employeeNumber = $employee?->employee_number ?? '—';
            $employeeName = $employee?->full_name ?? '—';
            $remarks = (string) ($log->description ?? '');

            if ($changes === []) {
                $rows[] = [
                    $timestamp,
                    $actor,
                    $actionLabel,
                    $employeeNumber,
                    $employeeName,
                    '—',
                    '—',
                    '—',
                    $remarks,
                ];

                continue;
            }

            foreach ($changes as $change) {
                $rows[] = [
                    $timestamp,
                    $actor,
                    $actionLabel,
                    $employeeNumber,
                    $employeeName,
                    $change['label'],
                    $this->truncateCell($this->historyService->formatDisplayValue($change['old'])),
                    $this->truncateCell($this->historyService->formatDisplayValue($change['new'])),
                    $remarks,
                ];
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'meta' => [
                'filter_summary' => $this->filterSummary($options, $employeeIds, $actions, count($rows)),
                'row_count' => count($rows),
            ],
        ];
    }

    /**
     * @param  Collection<int, SysLog>  $logs
     * @return Collection<int, Employee>
     */
    private function employeesForLogs(Collection $logs): Collection
    {
        $employeeIds = $logs
            ->pluck('record_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === []) {
            return collect();
        }

        return Employee::query()
            ->withTrashed()
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');
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
     * @param  array<int, int|string>  $employeeIds
     * @param  array<int, string>  $actions
     */
    private function filterSummary(array $options, array $employeeIds, array $actions, int $rowCount): string
    {
        $parts = ["{$rowCount} change row(s)"];

        if ($employeeIds !== []) {
            $parts[] = count($employeeIds).' selected employee(s)';
        } else {
            $parts[] = 'all employees';
        }

        if (! empty($options['date_from']) || ! empty($options['date_to'])) {
            $from = $options['date_from'] ?? '…';
            $to = $options['date_to'] ?? '…';
            $parts[] = "date range {$from} to {$to}";
        }

        $parts[] = 'actions: '.implode(', ', $actions);

        return implode(' · ', $parts);
    }

    private function truncateCell(string $value, int $limit = 32000): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3).'...';
    }
}
