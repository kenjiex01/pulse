<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\SkolarisLoadSessionTimes;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Builds Employee Load template rows for a date range. Primary source is Skolaris
 * daily-loads (same shift schedules as Loading Attendance). Falls back to faculty
 * overview + batch offering details when daily-loads has no rows for the range.
 */
class EmployeeLoadTemplateService
{
    /** Map of single-letter schedule tokens to ISO weekday numbers (Mon=1..Sun=7). */
    private const LETTER_DAYS = ['M' => 1, 'T' => 2, 'W' => 3, 'H' => 4, 'F' => 5, 'S' => 6, 'U' => 7];

    private const ABBR_DAYS = ['MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6, 'SUN' => 7];

    private const FULL_DAYS = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
        'friday' => 5, 'saturday' => 6, 'sunday' => 7,
    ];

    public function __construct(private readonly SkolarisApiService $skolaris) {}

    public function assertTemplateDateRange(string $dateFrom, string $dateTo): void
    {
        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();

        if ($from->greaterThan($to)) {
            throw new RuntimeException('Date From must be on or before Date To.');
        }

        $maxDays = max(1, (int) config('employee_load.max_template_days', 31));
        $spanDays = $from->diffInDays($to) + 1;

        if ($spanDays > $maxDays) {
            throw new RuntimeException(
                "Date range is too long ({$spanDays} days). Use at most {$maxDays} days per template download."
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function buildRows(string $dateFrom, string $dateTo): array
    {
        if (! app()->runningUnitTests()) {
            @set_time_limit(max(30, (int) config('employee_load.build_time_limit_seconds', 300)));
        }

        $this->assertTemplateDateRange($dateFrom, $dateTo);

        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();

        $rows = [];
        $rowCount = $this->streamDailyLoadTemplateRows($from, $to, $dateFrom, $dateTo, function (array $row) use (&$rows): void {
            $rows[] = $row;
        });

        if ($rowCount > 0) {
            $rowNo = 0;

            foreach ($rows as $index => $row) {
                $rowNo++;
                $rows[$index]['row_no'] = (string) $rowNo;
            }

            return $rows;
        }

        return $this->buildRowsFromFacultyOverview($from, $to, $dateFrom, $dateTo);
    }

    /**
     * Emit pre-filled template rows without holding the full dataset when used from CSV streaming.
     *
     * @param  callable(array<string, string>): void  $emit
     */
    public function streamPrefilledTemplateRows(string $dateFrom, string $dateTo, callable $emit): int
    {
        $this->assertTemplateDateRange($dateFrom, $dateTo);

        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();
        $rowNo = 0;

        $dailyCount = $this->streamDailyLoadTemplateRows(
            $from,
            $to,
            $dateFrom,
            $dateTo,
            function (array $row) use ($emit, &$rowNo): void {
                $rowNo++;
                $row['row_no'] = (string) $rowNo;
                $emit($row);
            },
        );

        if ($dailyCount > 0) {
            return $dailyCount;
        }

        foreach ($this->buildRowsFromFacultyOverview($from, $to, $dateFrom, $dateTo) as $row) {
            $rowNo++;
            $row['row_no'] = (string) $rowNo;
            $emit($row);
        }

        return $rowNo;
    }

    /**
     * @param  callable(array<string, string>): void  $emit
     */
    private function streamDailyLoadTemplateRows(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $dateFrom,
        string $dateTo,
        callable $emit,
    ): int {
        $employees = $this->fetchDailyLoadEmployees($dateFrom, $dateTo);

        if ($employees === []) {
            return 0;
        }

        $localFaculty = $this->localFacultyNumberLookup();
        $seen = [];
        $count = 0;

        foreach ($employees as $employee) {
            foreach ($this->dailyLoadEmployeeRows($employee, $from, $to, $seen, $localFaculty) as $row) {
                $emit($row);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDailyLoadEmployees(string $dateFrom, string $dateTo): array
    {
        $mode = strtolower(trim((string) config('employee_load.daily_loads_fetch', 'bulk')));

        if ($mode === 'chunked_parallel') {
            $numbers = $this->facultyEmployeeNumbers();

            if ($numbers === []) {
                return [];
            }

            return $this->skolaris->dailyLoadsConcurrent($dateFrom, $dateTo, $numbers);
        }

        return $this->skolaris->dailyLoadsForEmployeeLoadTemplate($dateFrom, $dateTo);
    }

    /**
     * @return array<string, true>|null
     */
    private function localFacultyNumberLookup(): ?array
    {
        if (! config('employee_load.restrict_to_local_faculty', true)) {
            return null;
        }

        $numbers = $this->facultyEmployeeNumbers();

        if ($numbers === []) {
            return null;
        }

        return array_fill_keys($numbers, true);
    }

    /**
     * @return array<int, string>
     */
    private function facultyEmployeeNumbers(): array
    {
        return Employee::query()
            ->facultyEligible()
            ->where('is_active', true)
            ->whereNotNull('employee_number')
            ->where('employee_number', '!=', '')
            ->orderBy('employee_number')
            ->pluck('employee_number')
            ->map(fn ($number) => trim((string) $number))
            ->filter(fn (string $number) => $number !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array<string, true>|null  $localFaculty
     * @return array<int, array<string, string>>
     */
    private function dailyLoadEmployeeRows(
        mixed $employee,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array &$seen,
        ?array $localFaculty = null,
    ): array {
        if (! is_array($employee)) {
            return [];
        }

        $facultyName = trim((string) ($employee['full_name'] ?? ''));
        $employeeNumber = trim((string) ($employee['employee_number'] ?? ''));

        if ($localFaculty !== null && $employeeNumber !== '' && ! isset($localFaculty[$employeeNumber])) {
            return [];
        }

        if ($localFaculty !== null && $employeeNumber === '' && $localFaculty !== []) {
            return [];
        }

        $collegeDefault = trim((string) ($employee['college_code'] ?? $employee['college_name'] ?? ''));
        $rows = [];

        foreach ($employee['loads'] ?? [] as $load) {
            if (! is_array($load)) {
                continue;
            }

            $sessionDateRaw = trim((string) ($load['attendance_date'] ?? ''));

            if ($sessionDateRaw === '') {
                continue;
            }

            try {
                $sessionDate = CarbonImmutable::parse($sessionDateRaw)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            if ($sessionDate->lessThan($from) || $sessionDate->greaterThan($to)) {
                continue;
            }

            $offeringId = isset($load['offering_id']) ? (int) $load['offering_id'] : 0;
            $subject = trim((string) ($load['subject_code'] ?? ''));
            $sectionName = trim((string) ($load['section'] ?? ''));
            $schedule = $this->classScheduleFromDailyLoad($load);
            $college = trim((string) ($load['college_code'] ?? $load['college'] ?? $collegeDefault));
            $modality = $this->modalityLabel((string) ($load['modality'] ?? $load['modality_code'] ?? ''));

            $dedupeKey = implode('|', [
                $employeeNumber,
                $sessionDate->toDateString(),
                (string) $offeringId,
                $subject,
                $sectionName,
                $schedule,
            ]);

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $sessionTimes = SkolarisLoadSessionTimes::forTemplateCsv($load);

            $rows[] = [
                'row_no' => '',
                'faculty_name' => $facultyName,
                'college' => $college,
                'modality' => $modality,
                'subject' => $subject,
                'section' => $sectionName,
                'load_date' => $this->formatSessionDate($sessionDate),
                'class_schedule' => $schedule,
                'time_in' => $sessionTimes['time_in'],
                'time_out' => $sessionTimes['time_out'],
                'remarks' => '',
                'comments' => '',
                'verification_remarks' => '',
                'employee_number' => $employeeNumber,
                'skolaris_offering_id' => $offeringId > 0 ? (string) $offeringId : '',
                'session_date_iso' => $sessionDate->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * Legacy path: faculty overview + batch details, expanded by weekday.
     *
     * @return array<int, array<string, string>>
     */
    private function buildRowsFromFacultyOverview(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $dateFrom,
        string $dateTo,
    ): array {
        $period = $this->resolvePeriodForRange($dateFrom, $dateTo);

        $cacheMinutes = max(0, (int) config('employee_load.overview_cache_minutes', 10));
        $overviewKey = 'employee_load_faculty_overview_'.$period['id'];

        $campuses = $cacheMinutes > 0
            ? Cache::remember(
                $overviewKey,
                now()->addMinutes($cacheMinutes),
                fn (): array => $this->skolaris->facultyOverview($period['id']),
            )
            : $this->skolaris->facultyOverview($period['id']);

        $faculties = $this->collectFaculties($campuses);
        $offeringIds = $this->collectOfferingIds($faculties);

        if ($offeringIds === []) {
            return [];
        }

        $offerings = $this->skolaris->courseOfferingBatchDetails($offeringIds);

        $rows = [];
        $rowNo = 0;

        foreach ($faculties as $faculty) {
            $facultyName = trim((string) ($faculty['full_name'] ?? ''));
            $employeeNumber = trim((string) ($faculty['employee_number'] ?? ''));
            $loading = $faculty['faculty_loading'] ?? null;

            foreach ($faculty['sections'] ?? [] as $section) {
                foreach ($section['offering_ids'] ?? [] as $offeringId) {
                    $offering = $offerings[(int) $offeringId] ?? null;

                    if ($offering === null) {
                        continue;
                    }

                    if (! $this->offeringMatchesLoading($offering, $loading)) {
                        continue;
                    }

                    $weekdays = $this->weekdaysFor($offering);

                    if ($weekdays === []) {
                        continue;
                    }

                    $schedule = $this->classScheduleLabel($offering);
                    $college = $this->college($offering);
                    $modality = $this->modalityLabel((string) ($offering['modality'] ?? ''));
                    $subject = $this->subjectCode($offering, $section);
                    $sectionName = (string) ($offering['section'] ?? $section['section_name'] ?? '');

                    foreach ($this->sessionDates($from, $to, $weekdays) as $date) {
                        $rowNo++;

                        $rs = $offering['roomSchedule'] ?? $offering['room_schedule'] ?? [];
                        $sessionTimes = SkolarisLoadSessionTimes::forTemplateCsv([
                            'schedule' => $schedule,
                            'time_in' => $offering['schedule_time_start'] ?? ($rs['start_time'] ?? null),
                            'time_out' => $offering['schedule_time_end'] ?? ($rs['end_time'] ?? null),
                        ]);

                        $rows[] = [
                            'row_no' => (string) $rowNo,
                            'faculty_name' => $facultyName,
                            'college' => $college,
                            'modality' => $modality,
                            'subject' => $subject,
                            'section' => $sectionName,
                            'load_date' => $this->formatSessionDate($date),
                            'class_schedule' => $schedule,
                            'time_in' => $sessionTimes['time_in'],
                            'time_out' => $sessionTimes['time_out'],
                            'remarks' => '',
                            'comments' => '',
                            'verification_remarks' => '',
                            'employee_number' => $employeeNumber,
                            'skolaris_offering_id' => (string) $offeringId,
                            'session_date_iso' => $date->toDateString(),
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $load
     */
    private function classScheduleFromDailyLoad(array $load): string
    {
        $schedule = trim((string) ($load['schedule'] ?? ''));

        if ($schedule !== '') {
            return $schedule;
        }

        $startLabel = $this->formatTime($load['time_in'] ?? null);
        $endLabel = $this->formatTime($load['time_out'] ?? null);

        if ($startLabel === null || $endLabel === null) {
            return '';
        }

        return $startLabel.' - '.$endLabel;
    }

    /**
     * Resolve the enrollment period (loading) whose class date range best
     * overlaps the selected dates.
     *
     * @return array{id: int, label: string, classes_start_date: ?string, classes_end_date: ?string}
     */
    public function resolvePeriodForRange(string $dateFrom, string $dateTo): array
    {
        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();

        $periods = $this->skolaris->enrollmentPeriods();

        $best = null;
        $bestOverlap = -1;

        foreach ($periods as $period) {
            $start = $period['classes_start_date'] ?? null;
            $end = $period['classes_end_date'] ?? null;

            if (blank($start) || blank($end)) {
                continue;
            }

            $classesStart = CarbonImmutable::parse($start)->startOfDay();
            $classesEnd = CarbonImmutable::parse($end)->startOfDay();

            $overlapStart = $from->greaterThan($classesStart) ? $from : $classesStart;
            $overlapEnd = $to->lessThan($classesEnd) ? $to : $classesEnd;

            if ($overlapStart->greaterThan($overlapEnd)) {
                continue;
            }

            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;

            if ($overlapDays > $bestOverlap) {
                $bestOverlap = $overlapDays;
                $best = $period;
            }
        }

        if ($best === null) {
            throw new RuntimeException('No enrollment period (loading) covers the selected date range. Choose dates within an active loading period.');
        }

        return [
            'id' => (int) ($best['enrollment_period_id'] ?? $best['id']),
            'label' => $this->periodLabel($best),
            'classes_start_date' => $best['classes_start_date'] ?? null,
            'classes_end_date' => $best['classes_end_date'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function periodLabel(array $period): string
    {
        foreach (['period_name', 'name', 'label', 'title', 'display_name'] as $key) {
            if (! empty($period[$key])) {
                return (string) $period[$key];
            }
        }

        $parts = array_filter([
            $period['academic_year'] ?? null,
            isset($period['term_sequence']) ? 'Sem '.$period['term_sequence'] : null,
        ]);

        $label = trim(implode(' — ', $parts));

        return $label !== '' ? $label : 'Enrollment Period #'.($period['enrollment_period_id'] ?? $period['id'] ?? '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $campuses
     * @return array<int, array<string, mixed>>
     */
    private function collectFaculties(array $campuses): array
    {
        $faculties = [];

        foreach ($campuses as $campus) {
            foreach ($campus['faculty'] ?? [] as $faculty) {
                // Skip the synthetic TBA / unassigned bucket and faculty with no load.
                if (empty($faculty['employee_number']) || empty($faculty['sections'])) {
                    continue;
                }

                $faculties[] = $faculty;
            }
        }

        return $faculties;
    }

    /**
     * @param  array<int, array<string, mixed>>  $faculties
     * @return array<int, int>
     */
    private function collectOfferingIds(array $faculties): array
    {
        $ids = [];

        foreach ($faculties as $faculty) {
            foreach ($faculty['sections'] ?? [] as $section) {
                foreach ($section['offering_ids'] ?? [] as $offeringId) {
                    $ids[] = (int) $offeringId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Respect the faculty include_lecture / include_lab flags (same behaviour as
     * offeringMatchesScheduleSlotFilter in the Skolaris frontend).
     *
     * @param  array<string, mixed>  $offering
     * @param  array<string, mixed>|null  $loading
     */
    private function offeringMatchesLoading(array $offering, ?array $loading): bool
    {
        if (! is_array($loading)) {
            return true;
        }

        $includeLecture = $loading['include_lecture'] ?? true;
        $includeLab = $loading['include_lab'] ?? true;

        $slot = strtoupper(trim((string) ($offering['schedule_slot_type'] ?? '')));
        $isLab = $slot === 'LAB';

        if ($isLab) {
            return (bool) $includeLab;
        }

        return (bool) $includeLecture;
    }

    /**
     * @param  array<string, mixed>  $offering
     * @return array<int, int> ISO weekday numbers
     */
    private function weekdaysFor(array $offering): array
    {
        $raw = $offering['schedule_day'] ?? ($offering['roomSchedule']['day'] ?? null);

        if (blank($raw)) {
            return [];
        }

        $days = [];

        foreach (preg_split('/\s*[\/,]\s*/', (string) $raw) ?: [] as $piece) {
            $piece = trim($piece);

            if ($piece === '') {
                continue;
            }

            $days = array_merge($days, $this->tokenToWeekdays($piece));
        }

        return array_values(array_unique(array_filter($days)));
    }

    /**
     * @return array<int, int>
     */
    private function tokenToWeekdays(string $piece): array
    {
        $lower = strtolower($piece);

        foreach (self::FULL_DAYS as $name => $iso) {
            if (str_starts_with($lower, $name)) {
                return [$iso];
            }
        }

        $compact = strtoupper(preg_replace('/\s+/', '', $piece) ?? '');

        // Three-letter abbreviation groups, e.g. MONWED → MON, WED.
        if (strlen($compact) % 3 === 0 && preg_match('/^(MON|TUE|WED|THU|FRI|SAT|SUN)+$/', $compact)) {
            $out = [];

            foreach (str_split($compact, 3) as $abbr) {
                if (isset(self::ABBR_DAYS[$abbr])) {
                    $out[] = self::ABBR_DAYS[$abbr];
                }
            }

            return $out;
        }

        // Single-letter tokens, e.g. MWF, TTH represented as letters.
        if (preg_match('/^[MTWHFSU]+$/', $compact)) {
            $out = [];

            foreach (str_split($compact) as $letter) {
                if (isset(self::LETTER_DAYS[$letter])) {
                    $out[] = self::LETTER_DAYS[$letter];
                }
            }

            return $out;
        }

        $abbr = substr($compact, 0, 3);

        return isset(self::ABBR_DAYS[$abbr]) ? [self::ABBR_DAYS[$abbr]] : [];
    }

    /**
     * @param  array<int, int>  $weekdays
     * @return array<int, CarbonImmutable>
     */
    private function sessionDates(CarbonImmutable $from, CarbonImmutable $to, array $weekdays): array
    {
        $dates = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $immutable = CarbonImmutable::instance($date);

            if (in_array($immutable->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $immutable;
            }
        }

        return $dates;
    }

    private function formatSessionDate(CarbonImmutable $date): string
    {
        return $date->format('j-M-y').' ('.strtoupper($date->format('l')).')';
    }

    /**
     * @param  array<string, mixed>  $offering
     */
    private function classScheduleLabel(array $offering): string
    {
        $rs = $offering['roomSchedule'] ?? $offering['room_schedule'] ?? [];
        $start = $offering['schedule_time_start'] ?? ($rs['start_time'] ?? null);
        $end = $offering['schedule_time_end'] ?? ($rs['end_time'] ?? null);

        $startLabel = $this->formatTime($start);
        $endLabel = $this->formatTime($end);

        if ($startLabel === null || $endLabel === null) {
            return '';
        }

        return $startLabel.' - '.$endLabel;
    }

    private function formatTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('g:i A');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $offering
     */
    private function college(array $offering): string
    {
        return (string) (
            $offering['program']['college']['college_code']
            ?? $offering['program']['college']['code']
            ?? ''
        );
    }

    /**
     * @param  array<string, mixed>  $offering
     * @param  array<string, mixed>  $section
     */
    private function subjectCode(array $offering, array $section): string
    {
        return (string) (
            $offering['subject']['subject_code']
            ?? $section['subject_code']
            ?? ''
        );
    }

    private function modalityLabel(string $code): string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return '';
        }

        $labels = (array) config('employee_load.modality_labels', []);

        return $labels[$code] ?? $code;
    }
}
