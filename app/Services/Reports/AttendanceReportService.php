<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\LuDay;
use App\Models\Report;
use App\Models\ShiftCode;
use App\Models\TimekeepingHoliday;
use App\Models\TimekeepingHolidayYear;
use App\Models\TimekeepingPolicy;
use App\Models\User;
use App\Services\EmployeeShiftResolver;
use App\Services\TimeLogsPayrollService;
use App\Support\BirFormSettings;
use App\Support\SpreadsheetDownload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportService
{
    public const MAX_RANGE_DAYS = 366;

    public const ABSENT_REMARK = 'ABSENT W/O LOA RTW';

    public function __construct(
        private readonly TimeLogsPayrollService $timeLogsPayroll,
        private readonly EmployeeShiftResolver $shiftResolver,
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

        $company = BirFormSettings::all();
        $usedTitles = [];
        $sheets = [];

        foreach ($employees as $employee) {
            $sheets[] = $this->sheetForEmployee($employee, $from, $to, $usedTitles);
        }

        return new ReportGenerationResult(
            title: $report->title,
            headers: ['Cut-off Period', 'Date', 'Time In', 'Time Out', 'Mins Late', 'Remarks/Reason'],
            rows: [],
            meta: [
                'layout' => 'attendance_report',
                'period_label' => $this->periodLabel($from, $to),
                'company_name' => mb_strtoupper(trim((string) ($company['company_name'] ?? ''))),
                'company_address' => mb_strtoupper(trim((string) ($company['company_address'] ?? ''))),
                'prepared_by' => trim((string) $user->name),
                'noted_by' => trim((string) ($company['signatory_name'] ?? '')),
                'noted_by_title' => trim((string) ($company['signatory_title'] ?? '')),
                'sheets' => $sheets,
            ],
        );
    }

    public function downloadExcel(ReportGenerationResult $result): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheets = $result->meta['sheets'] ?? [];
        $first = true;

        foreach ($sheets as $sheetData) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;
            $sheet->setTitle((string) $sheetData['title']);
            $this->writeSheet($sheet, $result, $sheetData);
        }

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Attendance_Report_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, true>  $usedTitles
     * @return array<string, mixed>
     */
    private function sheetForEmployee(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, array &$usedTitles): array
    {
        $employeeId = (int) $employee->employee_id;
        $policy = $employee->timekeepingSetup?->policy;
        $defaultShift = $employee->timekeepingSetup?->shiftCode;
        $restDayIds = $employee->timekeepingRestDays
            ->pluck('day_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $holidays = $this->holidayDescriptions($employee, $from, $to);

        $this->shiftResolver->loadOverridesForRange($employeeId, $from, $to);
        $sessions = $this->timeLogsPayroll->daySessionsForPeriod($employeeId, $from, $to)
            ->keyBy(fn (array $session) => $session['date']->toDateString());

        $months = [];

        foreach ($this->monthSlices($from, $to) as [$monthStart, $monthEnd]) {
            $days = [];
            $tardinessCount = 0;
            $lateMinutes = 0;
            $absentUnits = 0.0;
            $cursor = $monthStart;

            while ($cursor->lte($monthEnd)) {
                $day = $this->evaluateDay(
                    $employeeId,
                    $cursor,
                    $sessions->get($cursor->toDateString()),
                    $restDayIds,
                    $holidays,
                    $policy,
                    $defaultShift,
                );
                $days[] = $day;
                $tardinessCount += $day['is_tardy'] ? 1 : 0;
                $lateMinutes += $day['late_minutes'];
                $absentUnits += $day['absent_units'];
                $cursor = $cursor->addDay();
            }

            $months[] = [
                'label' => strtoupper($monthStart->format('F')).' '.$monthStart->format('d').' - '.$monthEnd->format('d').', '.$monthEnd->format('Y'),
                'tardiness_count' => $tardinessCount,
                'absent_label' => $this->formatQuantity($absentUnits),
                'late_minutes' => $lateMinutes,
                'days' => $days,
            ];
        }

        return [
            'title' => $this->worksheetTitle(
                (string) $employee->last_name,
                (string) ($employee->employee_number ?: 'Employee'),
                $usedTitles,
            ),
            'employee_name' => $this->employeeHeading($employee),
            'position' => $this->positionLabel($employee),
            'date_hired' => $employee->hire_date
                ? 'DATE HIRED: '.$employee->hire_date->format('m/d/Y')
                : 'DATE HIRED:',
            'months' => $months,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @param  list<int>  $restDayIds
     * @param  array<string, string>  $holidays
     * @return array{date: CarbonImmutable, time_in: ?string, time_out: ?string, late_minutes: int, remark: string, absent_units: float, is_tardy: bool}
     */
    private function evaluateDay(
        int $employeeId,
        CarbonImmutable $date,
        ?array $session,
        array $restDayIds,
        array $holidays,
        ?TimekeepingPolicy $policy,
        ?ShiftCode $defaultShift,
    ): array {
        $timeIn = $session['time_in'] ?? null;
        $timeOut = $session['time_out'] ?? null;
        $timeIn = is_string($timeIn) && $timeIn !== '' ? $timeIn : null;
        $timeOut = is_string($timeOut) && $timeOut !== '' ? $timeOut : null;

        $holiday = $holidays[$date->toDateString()] ?? null;
        $isRestDay = in_array(LuDay::idFromDate($date), $restDayIds, true);
        $lateMinutes = 0;
        $absentUnits = 0.0;
        $remark = '';

        if ($holiday !== null) {
            $remark = $holiday;
        } elseif ($isRestDay) {
            $remark = strtoupper($date->format('l'));
        } else {
            $shift = $this->shiftResolver->forDate($employeeId, $date, $defaultShift);
            $isFlexi = $shift !== null && (bool) $shift->is_flexi_time;

            if ($timeIn === null) {
                $remark = self::ABSENT_REMARK;
                $absentUnits = 1.0;
            } elseif (! $isFlexi) {
                $resolved = $this->timeLogsPayroll->resolvedLateForSession(
                    [
                        'date' => $date,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                    ],
                    $policy,
                    $shift?->time_in,
                    0,
                    $shift,
                );

                if ($resolved['is_absent']) {
                    $remark = self::ABSENT_REMARK;
                    $absentUnits = 1.0;
                } elseif ($resolved['is_half_day']) {
                    $absentUnits = 0.5;
                } else {
                    $lateMinutes = max(0, (int) $resolved['billable_minutes']);
                }
            }
        }

        return [
            'date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'late_minutes' => $lateMinutes,
            'remark' => $remark,
            'absent_units' => $absentUnits,
            'is_tardy' => $lateMinutes > 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function holidayDescriptions(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $holidayIds = $employee->timekeepingSetup?->holidayGroup?->holidays
            ?->pluck('timekeeping_holiday_id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        if ($holidayIds === []) {
            return [];
        }

        $map = [];
        $years = range($from->year, $to->year);

        TimekeepingHolidayYear::query()
            ->with('holiday')
            ->whereIn('timekeeping_holiday_id', $holidayIds)
            ->whereHas('year', fn ($query) => $query->whereIn('timekeeping_year', $years))
            ->whereDate('dt_datestamp', '>=', $from->toDateString())
            ->whereDate('dt_datestamp', '<=', $to->toDateString())
            ->orderBy('dt_datestamp')
            ->get()
            ->each(function (TimekeepingHolidayYear $entry) use (&$map): void {
                $description = $this->holidayLabel($entry->holiday, (string) $entry->timekeeping_holiday_code);
                if ($description === '' || $entry->dt_datestamp === null) {
                    return;
                }

                $map[$entry->dt_datestamp->toDateString()] = $description;
            });

        foreach ($employee->timekeepingSetup?->holidayGroup?->holidays ?? [] as $master) {
            if (! $master instanceof TimekeepingHoliday) {
                continue;
            }

            $description = $this->holidayLabel($master, (string) $master->timekeeping_holiday_code);
            if ($description === '') {
                continue;
            }

            foreach ($this->masterHolidayDates($master, $from, $to) as $date) {
                $key = $date->toDateString();
                if (! isset($map[$key])) {
                    $map[$key] = $description;
                }
            }
        }

        return $map;
    }

    private function holidayLabel(?TimekeepingHoliday $holiday, string $fallbackCode): string
    {
        $label = trim((string) ($holiday?->description ?: $fallbackCode));

        return $label === '' ? '' : mb_strtoupper($label);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function masterHolidayDates(TimekeepingHoliday $master, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($master->dt_datestamp === null) {
            return [];
        }

        $base = CarbonImmutable::parse($master->dt_datestamp->toDateString());
        $dates = [];

        if ($master->recurring) {
            foreach (range($from->year, $to->year) as $year) {
                try {
                    $occurrence = $base->setDate($year, $base->month, $base->day);
                } catch (\Throwable) {
                    continue;
                }

                if ($occurrence->greaterThanOrEqualTo($from) && $occurrence->lessThanOrEqualTo($to)) {
                    $dates[] = $occurrence;
                }
            }

            return $dates;
        }

        if ($base->greaterThanOrEqualTo($from) && $base->lessThanOrEqualTo($to)) {
            $dates[] = $base;
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $sheetData
     */
    private function writeSheet(Worksheet $sheet, ReportGenerationResult $result, array $sheetData): void
    {
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.28)->setLeft(0.63)->setRight(0.7);

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(32);

        $sheet->setCellValue('A1', 'ATTENDANCE SUMMARY');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setName('Tahoma')->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', (string) ($result->meta['period_label'] ?? ''));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setName('Tahoma')->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $identity = [
            4 => (string) ($result->meta['company_name'] ?? ''),
            5 => (string) ($result->meta['company_address'] ?? ''),
            6 => (string) ($sheetData['employee_name'] ?? ''),
            7 => (string) ($sheetData['position'] ?? ''),
            8 => (string) ($sheetData['date_hired'] ?? ''),
        ];

        foreach ($identity as $row => $value) {
            $sheet->setCellValue('A'.$row, $value);
            $sheet->mergeCells('A'.$row.':F'.$row);
        }

        $sheet->getStyle('A4:F8')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:F8')->getFont()->setBold(true)->setName('Tahoma')->setSize(12);
        $sheet->getStyle('A4:F8')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5')->getFont()->setSize(10);
        $sheet->getStyle('A8')->getFont()->setSize(8);
        $sheet->getStyle('A4:F8')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

        $headers = ['CUT- OFF PERIOD', 'DATE', 'TIME IN', 'TIME OUT', 'MINS LATE', 'REMARKS/REASON'];
        $sheet->fromArray($headers, null, 'A9');
        $sheet->getStyle('A9:F9')->getFont()->setBold(true)->setName('Tahoma')->setSize(8);
        $sheet->getStyle('A9:F9')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFFF99');
        $sheet->getStyle('A9:F9')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A9:F9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $this->applyGrid($sheet, 10);

        $row = 11;
        $months = $sheetData['months'] ?? [];
        $lastMonth = count($months) - 1;

        foreach ($months as $index => $month) {
            $summary = [
                (string) $month['label'],
                'TOTAL TARDINESS PER MONTH: '.$month['tardiness_count'],
                'TOTAL ABSENT/ HALFDAY PER MONTH: '.$month['absent_label'],
                'TOTAL NO OF MINUTES LATE: '.$month['late_minutes'],
            ];
            $days = $month['days'] ?? [];
            $lineCount = max(4, count($days));

            for ($i = 0; $i < $lineCount; $i++) {
                $day = $days[$i] ?? null;
                if ($i < 4) {
                    $sheet->setCellValue('A'.$row, $summary[$i]);
                    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setName('Tahoma')->setSize(8);
                    $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                if (is_array($day)) {
                    $sheet->setCellValue('B'.$row, ExcelDate::PHPToExcel($day['date']));
                    $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('mm/dd/yyyy');

                    $timeIn = $this->excelTimeFraction($day['time_in'] ?? null);
                    if ($timeIn !== null) {
                        $sheet->setCellValue('C'.$row, $timeIn);
                        $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('h:mm AM/PM');
                    }

                    $timeOut = $this->excelTimeFraction($day['time_out'] ?? null);
                    if ($timeOut !== null) {
                        $sheet->setCellValue('D'.$row, $timeOut);
                        $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode('h:mm AM/PM');
                    }

                    if ((int) ($day['late_minutes'] ?? 0) > 0) {
                        $sheet->setCellValue('E'.$row, (int) $day['late_minutes']);
                    }

                    $remark = (string) ($day['remark'] ?? '');
                    if ($remark !== '') {
                        $sheet->setCellValue('F'.$row, $remark);
                        $sheet->getStyle('F'.$row)->getFont()->setBold(true);
                    }
                }

                $this->applyGrid($sheet, $row);
                $sheet->getStyle('B'.$row.':E'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setName('Tahoma')->setSize(8);
                if ($i < 4 || ($day['remark'] ?? '') !== '') {
                    $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                    if (($day['remark'] ?? '') !== '') {
                        $sheet->getStyle('F'.$row)->getFont()->setBold(true)->setName('Tahoma')->setSize(8);
                    }
                }

                $row++;
            }

            if ($index < $lastMonth) {
                $this->applyGrid($sheet, $row);
                $this->applyGrid($sheet, $row + 1);
                $row += 2;
            }
        }

        $row += 2;
        $sheet->setCellValue('A'.$row, 'Prepared by:');
        $sheet->setCellValue('C'.$row, 'Noted by:');
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setName('Tahoma')->setSize(10);

        $row += 3;
        $sheet->setCellValue('A'.$row, (string) ($result->meta['prepared_by'] ?? ''));
        $sheet->setCellValue('C'.$row, (string) ($result->meta['noted_by'] ?? ''));
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true)->setName('Tahoma')->setSize(10);

        $row++;
        $sheet->setCellValue('C'.$row, (string) ($result->meta['noted_by_title'] ?? ''));
        $sheet->getStyle('C'.$row)->getFont()->setName('Tahoma')->setSize(10);
    }

    private function applyGrid(Worksheet $sheet, int $row): void
    {
        $sheet->getStyle('A'.$row.':F'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setName('Tahoma')->setSize(8);
    }

    private function excelTimeFraction(?string $time): ?float
    {
        if ($time === null || $time === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::parse('2000-01-01 '.$time);
        } catch (\Throwable) {
            return null;
        }

        return ($parsed->hour * 3600 + $parsed->minute * 60 + $parsed->second) / 86400;
    }

    private function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->year === $to->year) {
            return $from->format('F j').' to '.$to->format('F j, Y');
        }

        return $from->format('F j, Y').' to '.$to->format('F j, Y');
    }

    private function employeeHeading(Employee $employee): string
    {
        $last = mb_strtoupper(trim((string) $employee->last_name));
        $given = mb_strtoupper(trim(implode(' ', array_filter([
            trim((string) $employee->first_name),
            trim((string) $employee->middle_name),
            trim((string) $employee->suffix),
        ]))));

        if ($last === '') {
            return $given;
        }

        return $given === '' ? $last : $last.', '.$given;
    }

    private function positionLabel(Employee $employee): string
    {
        $label = trim((string) ($employee->designation ?: $employee->position ?: ''));

        return mb_strtoupper($label);
    }

    private function formatQuantity(float $value): string
    {
        $rounded = round($value, 1);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 1, '.', '');
    }

    /**
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function monthSlices(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $slices = [];
        $cursor = $from;

        while ($cursor->lte($to)) {
            $monthEnd = $cursor->endOfMonth()->startOfDay();
            if ($monthEnd->gt($to)) {
                $monthEnd = $to;
            }

            $slices[] = [$cursor, $monthEnd];
            $cursor = $monthEnd->addDay();
        }

        return $slices;
    }

    /**
     * @param  array<string, true>  $usedTitles
     */
    private function worksheetTitle(string $lastName, string $fallback, array &$usedTitles): string
    {
        $base = $this->safeSheetTitle($lastName);
        if ($base === '') {
            $base = $this->safeSheetTitle($fallback);
        }
        if ($base === '') {
            $base = 'EMPLOYEE';
        }

        $title = mb_substr($base, 0, 31);
        $n = 2;

        while (isset($usedTitles[mb_strtolower($title)])) {
            $suffix = ' '.$n;
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
            $n++;
        }

        $usedTitles[mb_strtolower($title)] = true;

        return $title;
    }

    private function safeSheetTitle(string $value): string
    {
        $title = mb_strtoupper(trim($value));
        $title = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $title);

        return trim($title);
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
            ->with([
                'employmentInformations',
                'timekeepingSetup.policy',
                'timekeepingSetup.shiftCode',
                'timekeepingSetup.holidayGroup.holidays',
                'timekeepingRestDays',
            ])
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
