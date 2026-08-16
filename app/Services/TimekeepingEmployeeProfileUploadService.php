<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeRestDay;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingPolicy;
use App\Models\User;
use App\Services\SysLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TimekeepingEmployeeProfileUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    /**
     * @var array<int, array{rest: string, paid: string}>
     */
    private const REST_DAY_ALIASES = [
        1 => ['rest' => 'rest_sun', 'paid' => 'rest_sun_paid'],
        2 => ['rest' => 'rest_mon', 'paid' => 'rest_mon_paid'],
        3 => ['rest' => 'rest_tue', 'paid' => 'rest_tue_paid'],
        4 => ['rest' => 'rest_wed', 'paid' => 'rest_wed_paid'],
        5 => ['rest' => 'rest_thu', 'paid' => 'rest_thu_paid'],
        6 => ['rest' => 'rest_fri', 'paid' => 'rest_fri_paid'],
        7 => ['rest' => 'rest_sat', 'paid' => 'rest_sat_paid'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        return (array) config('employee_profile_upload.fields', []);
    }

    /**
     * @return array<int, string>
     */
    public function fieldAliases(): array
    {
        return collect($this->fields())->pluck('alias')->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function fieldHeaders(): array
    {
        return collect($this->fields())->pluck('label')->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function fieldDescriptions(): array
    {
        return collect($this->fields())
            ->map(fn (array $field) => $this->descriptionForField($field))
            ->values()
            ->all();
    }

    public function buildTemplateContent(): string
    {
        $aliases = $this->fieldAliases();
        $headers = $this->fieldHeaders();
        $descriptions = $this->fieldDescriptions();

        $content = $this->formatCsvRow($aliases)."\n"
            .$this->formatCsvRow($headers)."\n"
            .$this->formatCsvRow($descriptions)."\n";

        $employees = Employee::query()
            ->with(['timekeepingSetup.holidayGroup', 'timekeepingSetup.shiftCode', 'timekeepingSetup.policy', 'timekeepingRestDays'])
            ->orderBy('employee_number')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($employees as $employee) {
            $content .= $this->formatCsvRow($this->prefillRow($aliases, $employee))."\n";
        }

        return $content;
    }

    /**
     * @return array{
     *     valid: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     filename: string,
     *     valid_count: int,
     *     error_count: int
     * }
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $this->assertTextFile($file);

        $fields = $this->fields();
        $expectedAliases = $this->fieldAliases();
        $expectedHeaders = $this->fieldHeaders();
        $expectedDescriptions = $this->fieldDescriptions();

        if ($expectedAliases === []) {
            throw new RuntimeException('Upload template has no field definitions.');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $valid = [];
        $errors = [];
        $seenEmployees = [];
        $lineNumber = 0;
        $delimiter = "\t";

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $delimiter = $this->detectDelimiter($line);
                }

                $cells = $this->splitLine($line, $delimiter);

                if ($lineNumber === 1) {
                    if (count($cells) !== count($expectedAliases)) {
                        throw new RuntimeException('Fields and columns are not equal. Use the downloaded template as-is.');
                    }

                    foreach ($cells as $index => $cell) {
                        if (trim($cell) !== $expectedAliases[$index]) {
                            throw new RuntimeException('Fields from the uploaded file do not match the template.');
                        }
                    }

                    continue;
                }

                if ($this->matchesInstructionRow($cells, $expectedHeaders)) {
                    continue;
                }

                if ($this->matchesInstructionRow($cells, $expectedDescriptions)) {
                    continue;
                }

                if ($this->isBlankRow($cells)) {
                    continue;
                }

                if (count($cells) !== count($expectedAliases)) {
                    throw new RuntimeException('Details did not match with the number of fields at line '.$lineNumber.'.');
                }

                $row = [];

                foreach ($expectedAliases as $index => $alias) {
                    $row[$alias] = trim($cells[$index] ?? '');
                }

                $parsed = $this->validateRow($fields, $row, $lineNumber, $seenEmployees, $errors);

                if ($parsed !== null) {
                    $valid[] = $parsed;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineNumber < 1) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        if ($valid === [] && $errors === []) {
            throw new RuntimeException('No data rows found for uploading.');
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'filename' => $file->getClientOriginalName(),
            'valid_count' => count($valid),
            'error_count' => count($errors),
        ];
    }

    /**
     * @param  array<string, mixed>  $parseResult
     */
    public function createStagingToken(User $user, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
            'filename' => $parseResult['filename'],
            'valid' => $parseResult['valid'],
            'errors' => $parseResult['errors'],
            'valid_count' => $parseResult['valid_count'],
            'error_count' => $parseResult['error_count'],
        ], now()->addMinutes(self::STAGING_TTL_MINUTES));

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
     * @return array{applied: int, created: int, updated: int}
     */
    public function commit(User $user, string $token): array
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $rows = $staging['valid'];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $user, $token, &$created, &$updated): void {
            foreach ($rows as $row) {
                $employeeId = (int) ($row['employee_id'] ?? 0);
                $exists = TimekeepingEmployeeSetup::query()->where('employee_id', $employeeId)->exists();

                $this->persistSetup($employeeId, $row);

                SysLogService::record(
                    action: $exists ? 'edit' : 'add',
                    table: 'tbl_timekeeping_employee_setup',
                    description: ($exists ? 'Updated' : 'Added').' timekeeping profile for '.($row['full_name'] ?? 'employee').' ('.($row['emp_num'] ?? $employeeId).') via bulk upload',
                    recordId: $employeeId,
                );

                if ($exists) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $this->discardStaging($user, $token);
        });

        return [
            'applied' => count($rows),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seenEmployees
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateRow(array $fields, array $row, int $lineNumber, array &$seenEmployees, array &$errors): ?array
    {
        $parsed = [];
        $hasError = false;

        $empNum = trim($row['emp_num'] ?? '');

        if ($empNum === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Employee No. is required.";
        } else {
            $employee = Employee::query()->where('employee_number', $empNum)->first();

            if (! $employee) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Invalid employee number ({$empNum}).";
            } elseif (isset($seenEmployees[$empNum])) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: Duplicate employee number ({$empNum}).";
            } else {
                $seenEmployees[$empNum] = true;
                $parsed['employee_id'] = $employee->employee_id;
                $parsed['emp_num'] = $empNum;
                $parsed['full_name'] = $employee->full_name;
            }
        }

        foreach ($fields as $field) {
            $alias = $field['alias'];
            $type = (string) ($field['type'] ?? '');

            if ($alias === 'emp_num' || $type === 'reference') {
                continue;
            }

            $value = trim($row[$alias] ?? '');
            $required = (bool) ($field['required'] ?? false);

            if ($value === '' && $required) {
                $hasError = true;
                $errors[] = "Line {$lineNumber}: {$field['label']} is required.";

                continue;
            }

            if ($value === '') {
                if ($type === 'boolean') {
                    $parsed[$alias] = false;
                }

                continue;
            }

            $result = match ($type) {
                'holiday_group' => $this->lookupHolidayGroup($value, $lineNumber, $field['label'], $errors, $hasError),
                'policy' => $this->lookupPolicy($value, $lineNumber, $field['label'], $errors, $hasError),
                'shift_code' => $this->lookupShiftCode($value, $lineNumber, $field['label'], $errors, $hasError),
                'boolean' => [$alias => $this->parseBoolean($value, $lineNumber, $field['label'], $errors, $hasError)],
                default => null,
            };

            if ($result !== null) {
                $parsed = array_merge($parsed, $result);
            }
        }

        if (! $hasError) {
            $parsed['rest_days'] = $this->parseRestDays($parsed);
        }

        return $hasError ? null : $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<array{day_id: int, is_paid: bool}>
     */
    private function parseRestDays(array $parsed): array
    {
        $restDays = [];

        foreach (self::REST_DAY_ALIASES as $dayId => $aliases) {
            if (! ($parsed[$aliases['rest']] ?? false)) {
                continue;
            }

            $restDays[] = [
                'day_id' => $dayId,
                'is_paid' => (bool) ($parsed[$aliases['paid']] ?? false),
            ];
        }

        return $restDays;
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupHolidayGroup(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $group = TimekeepingHolidayGroup::query()
            ->where('timekeeping_holiday_group_code', $value)
            ->first();

        if (! $group) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [
            'holiday_group_code' => $value,
            'timekeeping_holiday_group_id' => $group->timekeeping_holiday_group_id,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupPolicy(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $policy = TimekeepingPolicy::query()
            ->where('policy_name', $value)
            ->first();

        if (! $policy) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [
            'policy_name' => $value,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupShiftCode(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $shift = ShiftCode::query()
            ->where('shift_code', $value)
            ->first();

        if (! $shift) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [
            'shift_code' => $value,
            'shift_code_id' => $shift->shift_code_id,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function parseBoolean(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): bool
    {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'y', 'yes', 'true'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'n', 'no', 'false', ''], true)) {
            return false;
        }

        $hasError = true;
        $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}). Use 1/0 or Y/N.";

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistSetup(int $employeeId, array $row): void
    {
        TimekeepingEmployeeSetup::query()->updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'timekeeping_holiday_group_id' => $row['timekeeping_holiday_group_id'],
                'shift_code_id' => $row['shift_code_id'],
                'timekeeping_policy_id' => $row['timekeeping_policy_id'],
                'is_leave' => (bool) ($row['is_leave'] ?? false),
                'is_populate' => (bool) ($row['is_populate'] ?? false),
                'is_auto_compute_excess_as_ot' => (bool) ($row['is_auto_compute_excess_as_ot'] ?? false),
            ],
        );

        TimekeepingEmployeeRestDay::query()
            ->where('employee_id', $employeeId)
            ->delete();

        $restDays = collect($row['rest_days'] ?? [])
            ->map(fn (array $day) => [
                'employee_id' => $employeeId,
                'day_id' => $day['day_id'],
                'is_paid' => (bool) ($day['is_paid'] ?? false),
            ])
            ->values()
            ->all();

        if ($restDays !== []) {
            TimekeepingEmployeeRestDay::query()->insert($restDays);
        }
    }

    /**
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    private function prefillRow(array $aliases, Employee $employee): array
    {
        $setup = $employee->timekeepingSetup;
        $restDayMap = $employee->timekeepingRestDays->keyBy('day_id');
        $values = array_fill(0, count($aliases), '');

        foreach ($aliases as $index => $alias) {
            $values[$index] = match ($alias) {
                'emp_num' => (string) ($employee->employee_number ?? ''),
                'full_name' => (string) ($employee->full_name ?? ''),
                'holiday_group_code' => (string) ($setup?->holidayGroup?->timekeeping_holiday_group_code ?? ''),
                'policy_name' => (string) ($setup?->policy?->policy_name ?? ''),
                'shift_code' => (string) ($setup?->shiftCode?->shift_code ?? ''),
                'is_leave' => $setup ? ($setup->is_leave ? '1' : '0') : '',
                'is_populate' => $setup ? ($setup->is_populate ? '1' : '0') : '',
                'is_auto_compute_excess_as_ot' => $setup ? ($setup->is_auto_compute_excess_as_ot ? '1' : '0') : '',
                default => $this->prefillRestDayValue($alias, $restDayMap),
            };
        }

        return $values;
    }

    /**
     * @param  Collection<int, TimekeepingEmployeeRestDay>  $restDayMap
     */
    private function prefillRestDayValue(string $alias, Collection $restDayMap): string
    {
        foreach (self::REST_DAY_ALIASES as $dayId => $aliases) {
            if ($alias === $aliases['rest']) {
                return $restDayMap->has($dayId) ? '1' : '0';
            }

            if ($alias === $aliases['paid']) {
                return $restDayMap->get($dayId)?->is_paid ? '1' : '0';
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function descriptionForField(array $field): string
    {
        if (! empty($field['hint'])) {
            return (string) $field['hint'];
        }

        return match ((string) ($field['type'] ?? '')) {
            'holiday_group' => $this->lookupValuesHint(TimekeepingHolidayGroup::class, 'timekeeping_holiday_group_code'),
            'policy' => $this->lookupValuesHint(TimekeepingPolicy::class, 'policy_name'),
            'shift_code' => $this->lookupValuesHint(ShiftCode::class, 'shift_code'),
            default => '',
        };
    }

    /**
     * @param  class-string  $modelClass
     */
    private function lookupValuesHint(string $modelClass, string $codeColumn): string
    {
        $values = $modelClass::query()->orderBy($codeColumn)->pluck($codeColumn);

        if ($values->isEmpty()) {
            return 'NO LOOKUP VALUES';
        }

        $list = $values->take(50)->implode(', ');

        if ($values->count() > 50) {
            $list .= ', ...';
        }

        return 'Accepts current value(s): '.$list;
    }

    private function assertTextFile(UploadedFile $file): void
    {
        if ($file->getSize() > (int) config('uploads.max_file_kb', 15360) * 1024) {
            throw new RuntimeException('File size exceeds the maximum limit of 15 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getClientMimeType());
        $allowedExtensions = ['txt', 'csv', ''];
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];

        if (! in_array($extension, $allowedExtensions, true) && ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('File should be in CSV (*.csv) or tab-delimited text (*.txt) format.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitLine(string $line, string $delimiter = ','): array
    {
        $line = rtrim($line, "\r\n");

        return $line === '' ? [''] : str_getcsv($line, $delimiter);
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, "\t") > substr_count($line, ',') ? "\t" : ',';
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<int, string>  $expected
     */
    private function matchesInstructionRow(array $cells, array $expected): bool
    {
        if ($expected === [] || count($cells) !== count($expected)) {
            return false;
        }

        foreach ($cells as $index => $cell) {
            if (trim($cell) !== trim($expected[$index] ?? '')) {
                return false;
            }
        }

        return true;
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
     * @param  list<string>  $cells
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
        return "employee_profile_upload_staging:{$userId}:{$token}";
    }
}
