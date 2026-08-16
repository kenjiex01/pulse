<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use App\Services\EmployeeAttendanceViewService;
use App\Support\SpreadsheetDownload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceViewReportService
{
    public const MAX_RANGE_DAYS = 92;

    public function __construct(
        private readonly EmployeeAttendanceViewService $attendanceView,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Report $report, array $options, User $user): ReportGenerationResult
    {
        $from = CarbonImmutable::parse((string) $options['date_from'])->startOfDay();
        $to = CarbonImmutable::parse((string) $options['date_to'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => 'Date To must be on or after Date From.',
            ]);
        }

        if (((int) $from->diffInDays($to) + 1) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => 'The date range cannot exceed '.self::MAX_RANGE_DAYS.' days.',
            ]);
        }

        $employees = $this->visibleEmployees($options, $user);

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one employee you can access.',
            ]);
        }

        $dayHeaders = $this->attendanceView->pdfHeaders();
        $flatHeaders = array_merge(['Employee No.', 'Employee Name'], $dayHeaders);
        $flatRows = [];
        $sections = [];

        foreach ($employees as $employee) {
            $days = $this->attendanceView->daysForRange($employee, $from, $to);
            $dayRows = [];

            foreach ($days as $day) {
                $dayRows[] = $this->attendanceView->pdfRowForDay($day);
            }

            $name = trim((string) ($employee->full_name ?: $employee->employee_number));
            $number = (string) $employee->employee_number;

            $sections[] = [
                'heading' => $name.' ('.$number.')',
                'employee_number' => $number,
                'employee_name' => $name,
                'headers' => $dayHeaders,
                'rows' => $dayRows,
            ];

            foreach ($dayRows as $row) {
                $flatRows[] = array_merge([$number, $name], $row);
            }
        }

        $period = $from->format('m/d/Y').' – '.$to->format('m/d/Y');

        return new ReportGenerationResult(
            title: $report->title,
            headers: $flatHeaders,
            rows: $flatRows,
            meta: [
                'layout' => 'attendance_view',
                'filter_summary' => $employees->count().' employee(s) — '.$period,
                'period_label' => $period,
                'sections' => $sections,
            ],
        );
    }

    public function downloadExcel(ReportGenerationResult $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance View');

        $sheet->fromArray([$result->title], null, 'A1');

        if (! empty($result->meta['filter_summary'])) {
            $sheet->fromArray([(string) $result->meta['filter_summary']], null, 'A2');
        }

        $sheet->fromArray($result->headers, null, 'A4');
        $sheet->fromArray($result->rows, null, 'A5');

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Attendance_View_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, Employee>
     */
    private function visibleEmployees(array $options, User $user): Collection
    {
        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === []) {
            return collect();
        }

        $query = Employee::query()
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! $user->isAdmin()) {
            $query->where(function ($q) {
                $q->whereNull('is_confidential')
                    ->orWhere('is_confidential', false);
            });
        }

        return $query->get();
    }
}
