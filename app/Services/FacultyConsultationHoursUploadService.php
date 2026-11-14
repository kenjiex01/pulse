<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\LuDay;
use App\Models\TimekeepingEmployeeConsultationHour;
use App\Models\User;
use App\Support\SkolarisScheduleWeekdays;
use App\Support\TimekeepingEmployeeProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Bulk upload of faculty consultation hours: one CSV row per time range.
 * Each employee in the file has their consultation hours replaced by that file's rows.
 */
class FacultyConsultationHoursUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    private const ALIASES = ['emp_num', 'full_name', 'day', 'time_start', 'time_end'];

    private const HEADERS = ['Employee No.', 'Name', 'Day', 'Time Start', 'Time End'];

    private const DESCRIPTIONS = [
        'Required. Faculty employee number.',
        'Reference only — not uploaded.',
        'Monday to Sunday (or MON, TUE, …). One row per time range.',
        'e.g. 9:00 AM or 09:00',
        'e.g. 10:00 AM or 10:00 — must be after Time Start',
    ];

    public function __construct(
        private readonly FacultyConsultationConflictService $conflictService,
    ) {}

    public function buildTemplateContent(): string
    {
        $content = $this->formatCsvRow(self::ALIASES)."\n"
            .$this->formatCsvRow(self::HEADERS)."\n"
            .$this->formatCsvRow(self::DESCRIPTIONS)."\n";

        $days = TimekeepingEmployeeProfile::consultationWeekDays();
        $dayOrder = $days->pluck('day_id')->flip();
        $dayNames = $days->pluck('day', 'day_id');

        $faculty = Employee::query()
            ->whereHas('employmentInformations', fn ($query) => $query->where('user_type', EmployeeEmploymentInformation::TYPE_FACULTY))
            ->with('consultationHours')
            ->orderBy('employee_number')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($faculty as $employee) {
            $hours = $employee->consultationHours
                ->sortBy(fn (TimekeepingEmployeeConsultationHour $hour) => [$dayOrder->get($hour->day_id, 99), $hour->time_start])
                ->values();

            if ($hours->isEmpty()) {
                $content .= $this->formatCsvRow([$employee->employee_number, $employee->full_name, '', '', ''])."\n";

                continue;
            }

            foreach ($hours as $hour) {
                $content .= $this->formatCsvRow([
                    $employee->employee_number,
                    $employee->full_name,
                    (string) $dayNames->get($hour->day_id, ''),
                    $this->displayClock($hour->time_start),
                    $this->displayClock($hour->time_end),
                ])."\n";
            }
        }

        return $content;
    }

    /**
     * @return array{
     *     upload_type: string,
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int,
     *     employee_count: int
     * }
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $this->assertTextFile($file);

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $errors = [];
        $rowsByEmployee = [];
        $employees = [];
        $lineNumber = 0;
        $delimiter = ',';

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $line = $this->stripUtf8Bom($line);
                    $delimiter = substr_count($line, "\t") > substr_count($line, ',') ? "\t" : ',';
                }

                $cells = $this->splitLine($line, $delimiter);

                if ($lineNumber === 1) {
                    if (array_map('trim', $cells) !== self::ALIASES) {
                        throw new RuntimeException('Fields from the uploaded file do not match the Consultation Hours template.');
                    }

                    continue;
                }

                if ($this->matchesRow($cells, self::HEADERS) || $this->matchesRow($cells, self::DESCRIPTIONS) || $this->isBlankRow($cells)) {
                    continue;
                }

                if (count($cells) !== count(self::ALIASES)) {
                    throw new RuntimeException('Details did not match with the number of fields at line '.$lineNumber.'.');
                }

                $row = array_combine(self::ALIASES, array_map('trim', $cells));

                if ($row['day'] === '' && $row['time_start'] === '' && $row['time_end'] === '') {
                    continue;
                }

                $parsed = $this->validateRow($row, $lineNumber, $employees, $errors);

                if ($parsed !== null) {
                    $rowsByEmployee[$parsed['employee_id']][] = $parsed;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineNumber < 1) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $valid = [];

        foreach ($rowsByEmployee as $employeeId => $rows) {
            $overlapErrors = $this->overlapErrors($rows);

            if ($overlapErrors !== []) {
                array_push($errors, ...$overlapErrors);

                continue;
            }

            $employee = $employees[$rows[0]['emp_num']];
            $conflicts = $this->conflictService->checkStoredLoad($employee, array_map(fn (array $row) => [
                'day_id' => $row['day_id'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end'],
            ], $rows));

            $valid[] = [
                'employee_id' => $employeeId,
                'emp_num' => $rows[0]['emp_num'],
                'full_name' => $employee->full_name,
                'slots' => $this->sortSlots($rows),
                'conflicts' => $conflicts,
            ];
        }

        if ($valid === [] && $errors === []) {
            throw new RuntimeException('No consultation hours found for uploading.');
        }

        return [
            'upload_type' => TimekeepingEmployeeProfile::UPLOAD_TYPE_CONSULTATION,
            'valid' => $valid,
            'errors' => $errors,
            'filename' => $file->getClientOriginalName(),
            'valid_count' => count($valid),
            'error_count' => count($errors),
            'employee_count' => count($valid),
        ];
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), $parseResult, now()->addMinutes(self::STAGING_TTL_MINUTES));

        return $token;
    }

    public function discardStaging(User $user, string $token): void
    {
        Cache::forget($this->stagingCacheKey($user->id, $token));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStaging(User $user, string $token): ?array
    {
        $payload = Cache::get($this->stagingCacheKey($user->id, $token));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array{applied: int, ranges: int}
     */
    public function commit(User $user, string $token): array
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $ranges = 0;

        DB::transaction(function () use ($staging, $user, $token, &$ranges): void {
            $now = now();

            foreach ($staging['valid'] as $entry) {
                $employeeId = (int) $entry['employee_id'];
                $hadHours = TimekeepingEmployeeConsultationHour::query()->where('employee_id', $employeeId)->exists();

                TimekeepingEmployeeConsultationHour::query()
                    ->where('employee_id', $employeeId)
                    ->get()
                    ->each(fn (TimekeepingEmployeeConsultationHour $hour) => $hour->forceDelete());

                $sortByDay = [];
                $rows = [];

                foreach ($entry['slots'] as $slot) {
                    $dayId = (int) $slot['day_id'];
                    $sortByDay[$dayId] = ($sortByDay[$dayId] ?? -1) + 1;
                    $rows[] = [
                        'employee_id' => $employeeId,
                        'day_id' => $dayId,
                        'time_start' => $slot['time_start'].':00',
                        'time_end' => $slot['time_end'].':00',
                        'sort_order' => $sortByDay[$dayId],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                TimekeepingEmployeeConsultationHour::query()->insert($rows);
                $ranges += count($rows);

                SysLogService::record(
                    action: $hadHours ? 'edit' : 'add',
                    table: 'tbl_timekeeping_employee_consultation_hours',
                    description: ($hadHours ? 'Updated' : 'Added').' consultation hours for '.($entry['full_name'] ?? 'employee').' ('.($entry['emp_num'] ?? $employeeId).') via bulk upload — '.count($rows).' range(s)',
                    recordId: $employeeId,
                );
            }

            $this->discardStaging($user, $token);
        });

        return [
            'applied' => (int) $staging['valid_count'],
            'ranges' => $ranges,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, Employee>  $employees
     * @param  array<int, string>  $errors
     * @return array{employee_id: int, emp_num: string, line: int, day_id: int, time_start: string, time_end: string}|null
     */
    private function validateRow(array $row, int $lineNumber, array &$employees, array &$errors): ?array
    {
        $empNum = $row['emp_num'];
        $hasError = false;

        if ($empNum === '') {
            $errors[] = "Line {$lineNumber}: Employee No. is required.";

            return null;
        }

        if (! array_key_exists($empNum, $employees)) {
            $employees[$empNum] = Employee::query()->with('employmentInformations')->where('employee_number', $empNum)->first();
        }

        $employee = $employees[$empNum];

        if ($employee === null) {
            $errors[] = "Line {$lineNumber}: Invalid employee number ({$empNum}).";
            $hasError = true;
        } elseif (! $employee->isFaculty()) {
            $errors[] = "Line {$lineNumber}: {$empNum} is not a faculty employee. Consultation hours are for faculty only.";
            $hasError = true;
        }

        $dayId = $this->parseDay($row['day']);

        if ($dayId === null) {
            $errors[] = "Line {$lineNumber}: Invalid Day ({$row['day']}). Use Monday to Sunday.";
            $hasError = true;
        }

        $start = $this->parseClock($row['time_start']);
        $end = $this->parseClock($row['time_end']);

        if ($start === null) {
            $errors[] = "Line {$lineNumber}: Invalid Time Start ({$row['time_start']}).";
            $hasError = true;
        }

        if ($end === null) {
            $errors[] = "Line {$lineNumber}: Invalid Time End ({$row['time_end']}).";
            $hasError = true;
        }

        if ($start !== null && $end !== null && $end <= $start) {
            $errors[] = "Line {$lineNumber}: Time End must be after Time Start.";
            $hasError = true;
        }

        if ($hasError) {
            return null;
        }

        return [
            'employee_id' => (int) $employee->employee_id,
            'emp_num' => $empNum,
            'line' => $lineNumber,
            'day_id' => $dayId,
            'time_start' => $start,
            'time_end' => $end,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function overlapErrors(array $rows): array
    {
        $errors = [];

        foreach (collect($rows)->groupBy('day_id') as $dayRows) {
            $previous = null;

            foreach ($dayRows->sortBy('time_start')->values() as $row) {
                if ($previous !== null && $row['time_start'] < $previous['time_end']) {
                    $errors[] = "Line {$row['line']}: {$row['emp_num']} overlaps another consultation hour on the same day (line {$previous['line']}).";
                }

                $previous = $row;
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{day_id: int, time_start: string, time_end: string}>
     */
    private function sortSlots(array $rows): array
    {
        return collect($rows)
            ->sortBy(fn (array $row) => [($row['day_id'] + 5) % 7, $row['time_start']])
            ->map(fn (array $row) => [
                'day_id' => $row['day_id'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end'],
            ])
            ->values()
            ->all();
    }

    private function parseDay(string $value): ?int
    {
        $isoWeekday = SkolarisScheduleWeekdays::fromDayField($value);

        if (count($isoWeekday) !== 1) {
            return null;
        }

        return ($isoWeekday[0] % 7) + 1;
    }

    private function parseClock(string $value): ?string
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return null;
        }

        foreach (['g:i A', 'g:iA', 'h:i A', 'h:iA', 'H:i', 'H:i:s', 'G:i'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('H:i');
            }
        }

        return null;
    }

    private function displayClock(?string $time): string
    {
        $time = substr((string) $time, 0, 5);

        try {
            return CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
        } catch (Throwable) {
            return '';
        }
    }

    private function assertTextFile(UploadedFile $file): void
    {
        if ($file->getSize() > (int) config('uploads.max_file_kb', 15360) * 1024) {
            throw new RuntimeException('File size exceeds the maximum limit of 15 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getClientMimeType());

        if (! in_array($extension, ['txt', 'csv', ''], true)
            && ! in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)) {
            throw new RuntimeException('File should be in CSV (*.csv) or tab-delimited text (*.txt) format.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitLine(string $line, string $delimiter): array
    {
        $line = rtrim($line, "\r\n");

        return $line === '' ? [''] : str_getcsv($line, $delimiter);
    }

    private function stripUtf8Bom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $expected
     */
    private function matchesRow(array $cells, array $expected): bool
    {
        return count($cells) === count($expected) && array_map('trim', $cells) === $expected;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function formatCsvRow(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return implode(',', $cells);
        }

        fputcsv($handle, $cells);
        rewind($handle);
        $line = stream_get_contents($handle) ?: '';
        fclose($handle);

        return rtrim($line, "\n");
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "employee_profile_consultation_upload_staging:{$userId}:{$token}";
    }
}
