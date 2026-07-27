<?php

namespace App\Services\Reports;

use App\Models\DeductionType;
use App\Models\IncomeType;
use App\Models\LeaveType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRegisterReportService
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
        $sheet->setTitle('Payroll Register');

        $sheet->fromArray([$result->title], null, 'A1');
        $sheet->fromArray($result->headers, null, 'A3');
        $sheet->fromArray($result->rows, null, 'A4');

        $writer = new Xlsx($spreadsheet);
        $filename = 'Payroll_Register_'.now()->format('Ymd_His').'.xlsx';

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
            ->values()
            ->all();

        $detailColumns = collect($options['detail_columns'] ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        $sortBy = (string) ($options['sort_by'] ?? 'employee_number');
        $groupBy = filled($options['group_by'] ?? null) ? (string) $options['group_by'] : null;

        $allowedDetails = array_keys(config('payroll_reports.detail_columns', []));
        $detailColumns = array_values(array_intersect($detailColumns, $allowedDetails));

        $allowedSort = array_keys(config('payroll_reports.sort_columns', []));
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'employee_number';
        }

        $allowedGroup = array_keys(config('payroll_reports.group_columns', []));
        if ($groupBy !== null && ! in_array($groupBy, $allowedGroup, true)) {
            $groupBy = null;
        }

        if ($groupBy !== null && ! in_array($groupBy, $detailColumns, true)) {
            $detailColumns[] = $groupBy;
        }

        $batches = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details.employee',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
                'details.leaves.leaveType',
            ])
            ->whereIn('payroll_batch_id', $batchIds)
            ->where('payroll_batch_status_id', PayrollBatchStatus::PROCESSED)
            ->get();

        if ($batches->isEmpty()) {
            return [
                'headers' => ['Message'],
                'rows' => [['No processed payroll batches were found for the selected filters.']],
                'meta' => ['batch_count' => 0, 'employee_count' => 0],
            ];
        }

        $incomeColumns = $this->discoverIncomeColumns($batches);
        $deductionColumns = $this->discoverDeductionColumns($batches);
        $leaveColumns = $this->discoverLeaveColumns($batches);

        $detailHeaders = collect($detailColumns)
            ->map(fn (string $key) => config("payroll_reports.detail_columns.$key"))
            ->values()
            ->all();

        $headers = array_merge(
            ['Employee No.', 'Employee Name'],
            $detailHeaders,
            $incomeColumns->pluck('label')->all(),
            $deductionColumns->pluck('label')->all(),
            $leaveColumns->pluck('label')->all(),
            ['Gross Income', 'Total Deductions', 'Net Pay'],
        );

        $rows = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! $this->detailIsVisible($detail, $user)) {
                    continue;
                }

                $rows[] = $this->buildEmployeeRow(
                    $detail,
                    $detailColumns,
                    $incomeColumns,
                    $deductionColumns,
                    $leaveColumns,
                );
            }
        }

        $rows = $this->sortRows($rows, $sortBy, $detailColumns);
        $rows = $this->applyGrouping($rows, $groupBy, $detailColumns, count($headers));

        return [
            'headers' => $headers,
            'rows' => $rows,
            'meta' => [
                'batch_count' => $batches->count(),
                'employee_count' => count($rows),
                'batch_labels' => $batches->map(fn (PayrollBatch $batch) => $this->batchOptions->batchLabel($batch))->values()->all(),
            ],
        ];
    }

    /**
     * @return Collection<int, array{key: string, label: string, income_type_id: int, include_hours: bool}>
     */
    private function discoverIncomeColumns(Collection $batches): Collection
    {
        $types = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                foreach ($detail->incomes as $income) {
                    $typeId = (int) $income->income_type_id;
                    $label = $income->incomeType?->description
                        ?? $income->incomeType?->income_type_code
                        ?? "Income {$typeId}";

                    $types[$typeId] = [
                        'key' => "income_{$typeId}",
                        'label' => $label,
                        'income_type_id' => $typeId,
                        'include_hours' => false,
                    ];

                    if ($income->hours !== null && (float) $income->hours != 0.0) {
                        $types[$typeId]['include_hours'] = true;
                    }
                }
            }
        }

        uasort($types, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        $columns = collect();

        foreach ($types as $type) {
            if ($type['include_hours']) {
                $columns->push([
                    'key' => $type['key'].'_hours',
                    'label' => $type['label'].' (Hrs)',
                    'income_type_id' => $type['income_type_id'],
                    'mode' => 'hours',
                ]);
            }

            $columns->push([
                'key' => $type['key'].'_amount',
                'label' => $type['label'].' (Amt)',
                'income_type_id' => $type['income_type_id'],
                'mode' => 'amount',
            ]);
        }

        return $columns;
    }

    /**
     * @return Collection<int, array{key: string, label: string, deduction_type_id: int}>
     */
    private function discoverDeductionColumns(Collection $batches): Collection
    {
        $types = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                foreach ($detail->deductions as $deduction) {
                    $typeId = (int) $deduction->deduction_type_id;
                    $label = $deduction->deductionType?->description
                        ?? $deduction->deductionType?->deduction_type_code
                        ?? "Deduction {$typeId}";

                    $types[$typeId] = [
                        'key' => "deduction_{$typeId}",
                        'label' => $label,
                        'deduction_type_id' => $typeId,
                    ];
                }
            }
        }

        uasort($types, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return collect(array_values($types));
    }

    /**
     * @return Collection<int, array{key: string, label: string, leave_type_id: int}>
     */
    private function discoverLeaveColumns(Collection $batches): Collection
    {
        $types = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                foreach ($detail->leaves as $leave) {
                    $typeId = (int) $leave->leave_type_id;
                    $label = $leave->leaveType?->description
                        ?? $leave->leaveType?->leave_type_code
                        ?? "Leave {$typeId}";

                    $types[$typeId] = [
                        'key' => "leave_{$typeId}",
                        'label' => $label,
                        'leave_type_id' => $typeId,
                    ];
                }
            }
        }

        uasort($types, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return collect(array_values($types));
    }

    private function detailIsVisible(PayrollBatchDetail $detail, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! (bool) ($detail->employee?->is_confidential ?? false);
    }

    /**
     * @param  array<int, string>  $detailColumns
     * @param  Collection<int, array<string, mixed>>  $incomeColumns
     * @param  Collection<int, array<string, mixed>>  $deductionColumns
     * @param  Collection<int, array<string, mixed>>  $leaveColumns
     * @return array{values: array<int, string|float|null>, sort_values: array<string, string>, group_value: ?string}
     */
    private function buildEmployeeRow(
        PayrollBatchDetail $detail,
        array $detailColumns,
        Collection $incomeColumns,
        Collection $deductionColumns,
        Collection $leaveColumns,
    ): array {
        $employee = $detail->employee;
        $values = [
            $employee?->employee_number ?? '',
            $employee?->full_name ?? '',
        ];

        $sortValues = [
            'employee_number' => (string) ($employee?->employee_number ?? ''),
            'employee_name' => (string) ($employee?->full_name ?? ''),
            'department' => (string) ($employee?->department ?? ''),
            'college' => (string) ($employee?->college ?? ''),
            'program' => (string) ($employee?->program ?? ''),
            'tax_status' => (string) ($employee?->tax_status ?? ''),
            'employment_status' => (string) ($employee?->employment_status ?? ''),
        ];

        foreach ($detailColumns as $column) {
            $values[] = $sortValues[$column] ?? '';
        }

        $incomeMap = $detail->incomes->groupBy('income_type_id');
        $grossIncome = 0.0;

        foreach ($incomeColumns as $column) {
            $lines = $incomeMap->get($column['income_type_id'], collect());
            if ($column['mode'] === 'hours') {
                $hours = $lines->sum(fn ($line) => (float) ($line->hours ?? 0));
                $values[] = $this->formatNumber($hours, 4);
            } else {
                $amount = $lines->sum(fn ($line) => (float) $line->taxable + (float) $line->non_taxable);
                $grossIncome += $amount;
                $values[] = $this->formatNumber($amount);
            }
        }

        $deductionMap = $detail->deductions->groupBy('deduction_type_id');
        $totalDeductions = 0.0;

        foreach ($deductionColumns as $column) {
            $amount = $deductionMap
                ->get($column['deduction_type_id'], collect())
                ->sum(fn ($line) => (float) $line->employee_amount);

            $totalDeductions += $amount;
            $values[] = $this->formatNumber($amount);
        }

        $leaveMap = $detail->leaves->groupBy('leave_type_id');

        foreach ($leaveColumns as $column) {
            $hours = $leaveMap
                ->get($column['leave_type_id'], collect())
                ->sum(fn ($line) => (float) $line->leave_hours);

            $values[] = $this->formatNumber($hours, 2);
        }

        $values[] = $this->formatNumber($grossIncome);
        $values[] = $this->formatNumber($totalDeductions);
        $values[] = $this->formatNumber($grossIncome - $totalDeductions);

        return [
            'values' => $values,
            'sort_values' => $sortValues,
            'group_value' => null,
        ];
    }

    /**
     * @param  array<int, array{values: array<int, string|float|null>, sort_values: array<string, string>, group_value: ?string}>  $rows
     * @param  array<int, string>  $detailColumns
     * @return array<int, array<int, string|float|null>>
     */
    private function sortRows(array $rows, string $sortBy, array $detailColumns): array
    {
        usort($rows, function (array $left, array $right) use ($sortBy) {
            $leftValue = strtolower((string) ($left['sort_values'][$sortBy] ?? ''));
            $rightValue = strtolower((string) ($right['sort_values'][$sortBy] ?? ''));

            return $leftValue <=> $rightValue;
        });

        return array_map(fn (array $row) => $row['values'], $rows);
    }

    /**
     * @param  array<int, array<int, string|float|null>>  $rows
     * @param  array<int, string>  $detailColumns
     * @return array<int, array<int, string|float|null>>
     */
    private function applyGrouping(array $rows, ?string $groupBy, array $detailColumns, int $columnCount): array
    {
        if ($groupBy === null || $rows === []) {
            return $rows;
        }

        $detailIndex = array_search($groupBy, $detailColumns, true);
        $groupColumnIndex = $detailIndex === false ? 1 : $detailIndex + 2;

        $grouped = [];
        $currentGroup = null;

        foreach ($rows as $row) {
            $groupLabel = (string) ($row[$groupColumnIndex] ?? '');

            if ($groupLabel !== $currentGroup) {
                $currentGroup = $groupLabel;
                $headerRow = array_fill(0, $columnCount, '');
                $headerRow[0] = $groupLabel === '' ? 'Unspecified' : $groupLabel;
                $grouped[] = $headerRow;
            }

            $grouped[] = $row;
        }

        return $grouped;
    }

    private function formatNumber(float $value, int $decimals = 2): string
    {
        if ($value == 0.0) {
            return '';
        }

        return number_format($value, $decimals, '.', '');
    }
}
