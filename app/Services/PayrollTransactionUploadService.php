<?php

namespace App\Services;

use App\Models\DayType;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeOvertimeApproval;
use App\Models\EmployeeShiftOverride;
use App\Models\IncomeType;
use App\Models\LeaveType;
use App\Models\LoanType;
use App\Models\PayrollCalendar;
use App\Models\RawPayrollDeduction;
use App\Models\RawPayrollHoursWorked;
use App\Models\RawPayrollIncome;
use App\Models\RawPayrollLeave;
use App\Models\RawPayrollLoanPayment;
use App\Models\RawPayrollOvertime;
use App\Models\RawPayrollResignedEmployee;
use App\Models\RawPayrollShiftCode;
use App\Models\RawPayrollTransaction;
use App\Models\ShiftCode;
use App\Models\TimeType;
use App\Models\User;
use App\Support\PayrollTransactionModule;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayrollTransactionUploadService
{
    private const STAGING_TTL_MINUTES = 120;

    private const DATE_PATTERN = '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/';

    private const DATE_PATTERN_US = '/^([0]?[1-9]|1[0-2])\/([0]?[1-9]|1\d|2\d|3[01])\/(19|20)\d{2}$/';

    public function __construct(
        private readonly EmployeeOvertimeApprovalService $overtimeApprovals,
    ) {}

    /**
     * @return array<int, string>
     */
    public function fieldAliases(string $uploadType): array
    {
        return collect(PayrollTransactionModule::uploadConfig($uploadType)['fields'] ?? [])
            ->pluck('alias')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function fieldHeaders(string $uploadType): array
    {
        return collect(PayrollTransactionModule::uploadConfig($uploadType)['fields'] ?? [])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function fieldDescriptions(string $uploadType): array
    {
        return collect(PayrollTransactionModule::uploadConfig($uploadType)['fields'] ?? [])
            ->map(fn (array $field) => $this->descriptionForField($field))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $prefillEmployeeNumbers
     */
    public function buildTemplateContent(string $uploadType, array $prefillEmployeeNumbers = []): string
    {
        $aliases = $this->fieldAliases($uploadType);
        $headers = $this->fieldHeaders($uploadType);
        $descriptions = $this->fieldDescriptions($uploadType);

        $content = $this->formatCsvRow($aliases)."\n"
            .$this->formatCsvRow($headers)."\n"
            .$this->formatCsvRow($descriptions)."\n";

        if ($prefillEmployeeNumbers === []) {
            return $content;
        }

        $empIndex = array_search('emp_num', $aliases, true);
        $fullNameIndex = array_search('full_name', $aliases, true);

        if ($empIndex === false) {
            return $content;
        }

        $prefillNumbers = array_values(array_filter(
            $prefillEmployeeNumbers,
            fn ($number) => trim((string) $number) !== ''
        ));

        $employeesByNumber = $prefillNumbers === []
            ? collect()
            : Employee::query()
                ->whereIn('employee_number', $prefillNumbers)
                ->get()
                ->keyBy('employee_number');

        foreach ($prefillEmployeeNumbers as $employeeNumber) {
            $row = array_fill(0, count($aliases), '');

            $employeeNumber = trim((string) $employeeNumber);

            if ($employeeNumber !== '') {
                $row[$empIndex] = $employeeNumber;

                if ($fullNameIndex !== false) {
                    $employee = $employeesByNumber->get($employeeNumber);
                    $row[$fullNameIndex] = $employee?->full_name ?? '';
                }
            }

            $content .= $this->formatCsvRow($row)."\n";
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
    public function parseUploadedFile(UploadedFile $file, string $uploadType, ?PayrollCalendar $calendar = null): array
    {
        $this->assertTextFile($file);

        $config = PayrollTransactionModule::uploadConfig($uploadType);
        $fields = $config['fields'] ?? [];
        $expectedAliases = $this->fieldAliases($uploadType);
        $expectedHeaders = $this->fieldHeaders($uploadType);
        $expectedDescriptions = $this->fieldDescriptions($uploadType);

        if ($expectedAliases === []) {
            throw new RuntimeException('Upload type has no field definitions.');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $valid = [];
        $errors = [];
        $lineNumber = 0;
        $delimiter = "\t";
        $seenShiftKeys = [];
        $seenOvertimeKeys = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $delimiter = $this->detectDelimiter($line);
                }

                $cells = $this->splitLine($line, $delimiter);

                if ($lineNumber === 1) {
                    if (count($cells) !== count($expectedAliases)) {
                        throw new RuntimeException('Fields and columns are not equal.');
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

                $parsed = $this->validateRow($uploadType, $fields, $row, $lineNumber, $errors, $calendar);

                if ($parsed !== null && $uploadType === 'shift-codes') {
                    $dupKey = ((int) $parsed['employee_id']).'|'.($parsed['work_date'] ?? '');

                    if (isset($seenShiftKeys[$dupKey])) {
                        $errors[] = "Line {$lineNumber}: Duplicate employee and work date in this file (also on line {$seenShiftKeys[$dupKey]}).";
                        $parsed = null;
                    } else {
                        $seenShiftKeys[$dupKey] = $lineNumber;
                    }
                }

                if ($parsed !== null && $uploadType === 'overtime') {
                    $dupKey = ((int) $parsed['employee_id']).'|'
                        .($parsed['work_date'] ?? '').'|'
                        .($parsed['ot_start'] ?? '').'|'
                        .($parsed['ot_end'] ?? '');

                    if (isset($seenOvertimeKeys[$dupKey])) {
                        $errors[] = "Line {$lineNumber}: Duplicate overtime window in this file (also on line {$seenOvertimeKeys[$dupKey]}).";
                        $parsed = null;
                    } else {
                        $seenOvertimeKeys[$dupKey] = $lineNumber;
                    }
                }

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
            throw new RuntimeException('No data rows found for uploading. Remove the caption and hint rows from the template, then add your data.');
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
    public function createStagingToken(User $user, string $uploadType, int $calendarId, array $parseResult): string
    {
        $token = (string) Str::uuid();

        Cache::put($this->stagingCacheKey($user->id, $token), [
            'upload_type' => $uploadType,
            'payroll_calendar_id' => $calendarId,
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

    public function commit(User $user, string $token): RawPayrollTransaction
    {
        $staging = $this->getStaging($user, $token);

        if (! $staging || ($staging['valid_count'] ?? 0) === 0) {
            throw new RuntimeException('No valid staged records to load.');
        }

        $uploadType = (string) ($staging['upload_type'] ?? '');
        $config = PayrollTransactionModule::uploadConfig($uploadType);
        $calendar = PayrollCalendar::query()->with('payType')->findOrFail((int) $staging['payroll_calendar_id']);
        $rows = $staging['valid'];

        return DB::transaction(function () use ($user, $uploadType, $config, $calendar, $rows, $staging, $token) {
            $transaction = RawPayrollTransaction::query()->create([
                'payroll_transaction_type_id' => (int) $config['transaction_type_id'],
                'payroll_calendar_id' => $calendar->payroll_calendar_id,
                'uploaded_by_id' => $user->id,
                'dt_uploaded' => now(),
                'batch_no' => $this->nextBatchNumber((int) $config['transaction_type_id']),
                'filename' => $staging['filename'],
            ]);

            foreach ($rows as $row) {
                $this->persistRow($uploadType, $config, $transaction, $row, $calendar);
            }

            $this->discardStaging($user, $token);

            return $transaction->fresh(['payrollCalendar.payType', 'uploadedBy']);
        });
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
            throw new RuntimeException('File should be in Text (Tab delimited) (*.txt) or CSV format.');
        }
    }

    /**
     * @return array<int, string>
     */
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
     * @param  array<string, mixed>  $field
     */
    private function descriptionForField(array $field): string
    {
        if (! empty($field['hint'])) {
            return (string) $field['hint'];
        }

        $alias = (string) ($field['alias'] ?? '');
        $type = (string) ($field['type'] ?? '');

        return match (true) {
            $alias === 'emp_num' => 'Accepts all existing Employee No.',
            ($field['type'] ?? '') === 'reference' => (string) ($field['hint'] ?? 'For reference only; not imported.'),
            $type === 'decimal' => 'Accepts up to '.((int) ($field['size'] ?? 8)).' digits and 2 decimals',
            $type === 'date' => 'Accepts mm/dd/yyyy format',
            $type === 'time' => 'Accepts HH:MM (24-hour) or h:mm AM/PM',
            $type === 'string' => 'Accepts up to '.((int) ($field['max'] ?? 255)).' characters',
            $type === 'income_type' => $this->lookupValuesHint(IncomeType::class, 'income_type_code'),
            $type === 'deduction_type' => $this->lookupValuesHint(DeductionType::class, 'deduction_type_code'),
            $type === 'day_type' => $this->lookupValuesHint(DayType::class, 'day_type_code'),
            $type === 'time_type' => $this->lookupValuesHint(TimeType::class, 'time_type_code'),
            $type === 'leave_type' => $this->lookupValuesHint(LeaveType::class, 'leave_type_code'),
            $type === 'loan_type' => $this->lookupValuesHint(LoanType::class, 'loan_type_code'),
            $type === 'shift_code' => $this->lookupValuesHint(ShiftCode::class, 'shift_code'),
            default => '',
        };
    }

    /**
     * @param  class-string  $modelClass
     */
    private function lookupValuesHint(string $modelClass, string $codeColumn): string
    {
        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
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

    private function splitLine(string $line, string $delimiter = "\t"): array
    {
        $line = rtrim($line, "\r\n");

        return $line === '' ? [''] : str_getcsv($line, $delimiter);
    }

    private function detectDelimiter(string $line): string
    {
        $tabCount = substr_count($line, "\t");
        $commaCount = substr_count($line, ',');

        return $commaCount > $tabCount ? ',' : "\t";
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
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, string>  $row
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateRow(
        string $uploadType,
        array $fields,
        array $row,
        int $lineNumber,
        array &$errors,
        ?PayrollCalendar $calendar = null,
    ): ?array {
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
            } else {
                $parsed['employee_id'] = $employee->employee_id;
            }
        }

        foreach ($fields as $field) {
            $alias = $field['alias'];

            if ($alias === 'emp_num' || ($field['type'] ?? '') === 'reference') {
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
                continue;
            }

            $result = $this->validateFieldValue($field, $value, $lineNumber, $errors, $hasError);

            if ($result !== null) {
                $parsed = array_merge($parsed, $result);
            }
        }

        if ($uploadType === 'leaves' && ! $hasError) {
            $this->validateLeaveRow($parsed, $lineNumber, $errors, $hasError);
        }

        if (in_array($uploadType, ['incomes', 'income-adjustments'], true)) {
            $this->validateIncomeHoursRow($parsed, $lineNumber, $errors, $hasError);
            $this->validateIncomeDaysRow($parsed, $lineNumber, $errors, $hasError);
        }

        if (in_array($uploadType, ['deductions', 'deduction-adjustments'], true)) {
            $this->validateDeductionHoursRow($parsed, $lineNumber, $errors, $hasError);
            $this->validateDeductionDaysRow($parsed, $lineNumber, $errors, $hasError);
        }

        if ($uploadType === 'shift-codes' && ! $hasError) {
            $this->validateShiftCodeRow($parsed, $lineNumber, $errors, $hasError, $calendar);
        }

        if ($uploadType === 'overtime' && ! $hasError) {
            $this->validateOvertimeRow($parsed, $lineNumber, $errors, $hasError, $calendar);
        }

        if ($uploadType === 'resigned-employees' && ! $hasError && isset($parsed['dt_resigned'])) {
            // validated in validateFieldValue
        }

        return $hasError ? null : $parsed;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateFieldValue(array $field, string $value, int $lineNumber, array &$errors, bool &$hasError): ?array
    {
        $alias = $field['alias'];
        $label = $field['label'];

        return match ($field['type']) {
            'income_type' => $this->lookupIncomeType($value, $lineNumber, $label, $errors, $hasError),
            'deduction_type' => $this->lookupDeductionType($value, $lineNumber, $label, $errors, $hasError),
            'day_type' => $this->lookupDayType($value, $lineNumber, $label, $errors, $hasError),
            'time_type' => $this->lookupTimeType($value, $lineNumber, $label, $errors, $hasError),
            'leave_type' => $alias === 'applies_to'
                ? $this->lookupLeaveType($value, $lineNumber, $label, $errors, $hasError, 'applies_to_leave_type_id')
                : $this->lookupLeaveType($value, $lineNumber, $label, $errors, $hasError, 'leave_type_id'),
            'loan_type' => $this->lookupLoanType($value, $lineNumber, $label, $errors, $hasError),
            'shift_code' => $this->lookupShiftCode($value, $lineNumber, $label, $errors, $hasError),
            'decimal' => $this->validateDecimal($field, $value, $lineNumber, $label, $errors, $hasError),
            'date' => $this->validateDateField($alias, $value, $lineNumber, $label, $errors, $hasError),
            'time' => $this->validateTimeField($alias, $value, $lineNumber, $label, $errors, $hasError),
            'string' => $this->validateStringField($alias, $value, $lineNumber, $label, (int) ($field['max'] ?? 255), $errors, $hasError),
            default => [$alias => $value],
        };
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupIncomeType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = IncomeType::query()->where('income_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['income_type_id' => $type->income_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupDeductionType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = DeductionType::query()->where('deduction_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['deduction_type_id' => $type->deduction_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupDayType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = DayType::query()->where('day_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['day_type_id' => $type->day_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupTimeType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = TimeType::query()->where('time_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['time_type_id' => $type->time_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupLeaveType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError, string $key): ?array
    {
        $type = LeaveType::query()->where('leave_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return [$key => $type->leave_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupLoanType(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = LoanType::query()->where('loan_type_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['loan_type_id' => $type->loan_type_id];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function lookupShiftCode(string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $type = ShiftCode::query()->where('shift_code', $value)->first();

        if (! $type) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        return ['shift_code_id' => $type->shift_code_id];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateShiftCodeRow(
        array $parsed,
        int $lineNumber,
        array &$errors,
        bool &$hasError,
        ?PayrollCalendar $calendar,
    ): void {
        if (! isset($parsed['work_date'])) {
            return;
        }

        if (! $calendar?->dt_from || ! $calendar?->dt_to) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Pay period dates are missing for the selected calendar.";

            return;
        }

        $workDate = Carbon::parse($parsed['work_date'])->startOfDay();
        $from = $calendar->dt_from->copy()->startOfDay();
        $to = $calendar->dt_to->copy()->startOfDay();

        if ($workDate->lt($from) || $workDate->gt($to)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Work Date must fall within the selected pay period ("
                .$from->format('m/d/Y').' – '.$to->format('m/d/Y').').';
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateOvertimeRow(
        array &$parsed,
        int $lineNumber,
        array &$errors,
        bool &$hasError,
        ?PayrollCalendar $calendar,
    ): void {
        if (! isset($parsed['work_date'], $parsed['ot_start'], $parsed['ot_end'], $parsed['employee_id'])) {
            return;
        }

        if (! $calendar?->dt_from || ! $calendar?->dt_to) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Pay period dates are missing for the selected calendar.";

            return;
        }

        $workDate = Carbon::parse($parsed['work_date'])->startOfDay();
        $from = $calendar->dt_from->copy()->startOfDay();
        $to = $calendar->dt_to->copy()->startOfDay();

        if ($workDate->lt($from) || $workDate->gt($to)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Work Date must fall within the selected pay period ("
                .$from->format('m/d/Y').' – '.$to->format('m/d/Y').').';

            return;
        }

        $employee = Employee::query()
            ->with(['timekeepingSetup.shiftCode'])
            ->find((int) $parsed['employee_id']);

        if (! $employee) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Employee not found.";

            return;
        }

        try {
            $summary = $this->overtimeApprovals->validateForStore(
                (int) $employee->employee_id,
                $workDate->toDateString(),
                (string) $parsed['ot_start'],
                (string) $parsed['ot_end'],
                null,
                $employee->timekeepingSetup?->shiftCode,
            );
        } catch (ValidationException $exception) {
            $hasError = true;
            $messages = collect($exception->errors())->flatten()->filter()->values();
            $detail = $messages->isNotEmpty() ? $messages->implode(' ') : 'Invalid overtime window.';
            $errors[] = "Line {$lineNumber}: {$detail}";

            return;
        }

        $parsed['ot_start_at'] = $summary['ot_start']->toDateTimeString();
        $parsed['ot_end_at'] = $summary['ot_end']->toDateTimeString();
        $parsed['billable_minutes'] = $summary['billable_minutes'];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateTimeField(
        string $alias,
        string $value,
        int $lineNumber,
        string $label,
        array &$errors,
        bool &$hasError,
    ): ?array {
        $normalized = $this->normalizeTimeClock($value);

        if ($normalized === null) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}). Use HH:MM or h:mm AM/PM.";

            return null;
        }

        return [$alias => $normalized];
    }

    private function normalizeTimeClock(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value) === 1) {
            try {
                return Carbon::createFromFormat(
                    substr_count($value, ':') === 2 ? 'H:i:s' : 'H:i',
                    $value,
                )->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s*(AM|PM)$/i', $value) === 1) {
            $upper = preg_replace('/\s+/', ' ', strtoupper(trim($value))) ?? '';
            $hasSeconds = substr_count(preg_replace('/\s*(AM|PM)$/i', '', $upper) ?? '', ':') === 2;
            $formats = $hasSeconds
                ? ['g:i:s A', 'h:i:s A']
                : ['g:i A', 'h:i A'];

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $upper)->format('H:i');
                } catch (\Throwable) {
                    // try next format
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateDecimal(array $field, string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        $alias = (string) ($field['alias'] ?? '');

        if (! is_numeric($value)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        $decimals = in_array($alias, ['hours', 'days'], true) ? 4 : 2;

        return [$alias => round((float) $value, $decimals)];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateDateField(string $alias, string $value, int $lineNumber, string $label, array &$errors, bool &$hasError): ?array
    {
        if (! $this->isValidDate($value)) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Invalid {$label} ({$value}).";

            return null;
        }

        $dateKey = match ($alias) {
            'date_from' => 'dt_from',
            'date_to' => 'dt_to',
            'loan_date' => 'dt_loan',
            'reference_date' => 'dt_reference',
            default => $alias,
        };

        return [$dateKey => Carbon::parse($value)->toDateString()];
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function validateStringField(string $alias, string $value, int $lineNumber, string $label, int $max, array &$errors, bool &$hasError): ?array
    {
        if (strlen($value) > $max) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: {$label} exceeds maximum length of {$max}.";

            return null;
        }

        $key = $alias === 'reference_number' ? 'reference_number' : $alias;

        return [$key => $value];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateLeaveRow(array $parsed, int $lineNumber, array &$errors, bool &$hasError): void
    {
        if (isset($parsed['dt_from'], $parsed['dt_to']) && $parsed['dt_from'] > $parsed['dt_to']) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Date From must be on or before Date To.";
        }
    }

    /**
     * Hours is required only for Basic (BASC) and Overtime (OVRT) income uploads.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateIncomeHoursRow(array $parsed, int $lineNumber, array &$errors, bool &$hasError): void
    {
        $incomeTypeId = (int) ($parsed['income_type_id'] ?? 0);

        if ($incomeTypeId <= 0) {
            return;
        }

        $code = IncomeType::query()
            ->whereKey($incomeTypeId)
            ->value('income_type_code');

        if (! in_array($code, ['BASC', 'OVRT'], true)) {
            return;
        }

        if (! array_key_exists('hours', $parsed) || $parsed['hours'] === null || $parsed['hours'] === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Hours is required for Basic (BASC) and Overtime (OVRT).";

            return;
        }

        if ((float) $parsed['hours'] <= 0) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Hours must be greater than 0 for Basic (BASC) and Overtime (OVRT).";
        }
    }

    /**
     * Days is required only for Basic (BASC) and Overtime (OVRT) income uploads.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateIncomeDaysRow(array $parsed, int $lineNumber, array &$errors, bool &$hasError): void
    {
        $incomeTypeId = (int) ($parsed['income_type_id'] ?? 0);

        if ($incomeTypeId <= 0) {
            return;
        }

        $code = IncomeType::query()
            ->whereKey($incomeTypeId)
            ->value('income_type_code');

        if (! in_array($code, ['BASC', 'OVRT'], true)) {
            return;
        }

        if (! array_key_exists('days', $parsed) || $parsed['days'] === null || $parsed['days'] === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Days is required for Basic (BASC) and Overtime (OVRT).";

            return;
        }

        if ((float) $parsed['days'] <= 0) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Days must be greater than 0 for Basic (BASC) and Overtime (OVRT).";
        }
    }

    /**
     * Hours is required only for Late (LTDE) and Undertime (UTDE) deduction uploads.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateDeductionHoursRow(array $parsed, int $lineNumber, array &$errors, bool &$hasError): void
    {
        $deductionTypeId = (int) ($parsed['deduction_type_id'] ?? 0);

        if ($deductionTypeId <= 0) {
            return;
        }

        $code = DeductionType::query()
            ->whereKey($deductionTypeId)
            ->value('deduction_type_code');

        if (! in_array($code, ['LTDE', 'UTDE'], true)) {
            return;
        }

        if (! array_key_exists('hours', $parsed) || $parsed['hours'] === null || $parsed['hours'] === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Hours is required for Late (LTDE) and Undertime (UTDE).";

            return;
        }

        if ((float) $parsed['hours'] <= 0) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Hours must be greater than 0 for Late (LTDE) and Undertime (UTDE).";
        }
    }

    /**
     * Days is required only for Late (LTDE) and Undertime (UTDE) deduction uploads.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $errors
     */
    private function validateDeductionDaysRow(array $parsed, int $lineNumber, array &$errors, bool &$hasError): void
    {
        $deductionTypeId = (int) ($parsed['deduction_type_id'] ?? 0);

        if ($deductionTypeId <= 0) {
            return;
        }

        $code = DeductionType::query()
            ->whereKey($deductionTypeId)
            ->value('deduction_type_code');

        if (! in_array($code, ['LTDE', 'UTDE'], true)) {
            return;
        }

        if (! array_key_exists('days', $parsed) || $parsed['days'] === null || $parsed['days'] === '') {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Days is required for Late (LTDE) and Undertime (UTDE).";

            return;
        }

        if ((float) $parsed['days'] <= 0) {
            $hasError = true;
            $errors[] = "Line {$lineNumber}: Days must be greater than 0 for Late (LTDE) and Undertime (UTDE).";
        }
    }

    private function isValidDate(string $value): bool
    {
        return preg_match(self::DATE_PATTERN, $value) === 1
            || preg_match(self::DATE_PATTERN_US, $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $row
     */
    private function persistRow(string $uploadType, array $config, RawPayrollTransaction $transaction, array $row, PayrollCalendar $calendar): void
    {
        match ($uploadType) {
            'incomes', 'income-adjustments' => RawPayrollIncome::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'income_type_id' => $row['income_type_id'],
                'hours' => $row['hours'] ?? null,
                'days' => $row['days'] ?? null,
                'taxable' => $row['taxable'] ?? null,
                'non_taxable' => $row['non_taxable'] ?? null,
                'amount' => $row['amount'] ?? null,
                'is_adjustment' => (bool) ($config['is_adjustment'] ?? false),
            ]),
            'deductions', 'deduction-adjustments' => RawPayrollDeduction::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'deduction_type_id' => $row['deduction_type_id'],
                'hours' => $row['hours'] ?? null,
                'days' => $row['days'] ?? null,
                'employee_amount' => $row['emp_amount'] ?? null,
                'employer_amount' => $row['empr_amount'] ?? null,
                'amount' => $row['amount'] ?? null,
                'is_adjustment' => (bool) ($config['is_adjustment'] ?? false),
                'reference_number' => $row['reference_number'] ?? null,
                'dt_reference' => isset($row['dt_reference']) ? Carbon::parse($row['dt_reference']) : null,
            ]),
            'hours-worked' => RawPayrollHoursWorked::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'day_type_id' => $row['day_type_id'],
                'time_type_id' => $row['time_type_id'],
                'hours' => $row['hours'],
                'amount' => $row['amount'] ?? null,
            ]),
            'shift-codes' => $this->persistShiftCodeRow($transaction, $row),
            'overtime' => $this->persistOvertimeRow($transaction, $row),
            'leaves' => RawPayrollLeave::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'leave_type_id' => $row['leave_type_id'],
                'dt_from' => Carbon::parse($row['dt_from'])->startOfDay(),
                'dt_to' => Carbon::parse($row['dt_to'])->endOfDay(),
                'leave_hours' => $row['leave_hours'],
                'applies_to_leave_type_id' => $row['applies_to_leave_type_id'] ?? null,
                'applied_hours' => $row['applied_hours'] ?? null,
                'reason' => $row['reason'] ?? null,
            ]),
            'loans' => RawPayrollLoanPayment::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'loan_type_id' => $row['loan_type_id'],
                'dt_loan' => Carbon::parse($row['dt_loan'])->startOfDay(),
                'payment' => $row['payment'] ?? null,
                'penalty' => $row['penalty'] ?? null,
                'reference_number' => $row['reference_number'] ?? null,
                'dt_reference' => isset($row['dt_reference']) ? Carbon::parse($row['dt_reference']) : null,
            ]),
            'resigned-employees' => RawPayrollResignedEmployee::query()->create([
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'employee_id' => $row['employee_id'],
                'dt_resigned' => $row['dt_resigned'] ?? null,
            ]),
            default => throw new RuntimeException('Unsupported upload type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistShiftCodeRow(RawPayrollTransaction $transaction, array $row): void
    {
        $employeeId = (int) $row['employee_id'];
        $workDate = Carbon::parse($row['work_date'])->toDateString();
        $shiftCodeId = (int) $row['shift_code_id'];
        $employeeNumber = Employee::query()->whereKey($employeeId)->value('employee_number') ?? (string) $employeeId;

        $raw = RawPayrollShiftCode::query()->create([
            'payroll_transaction_id' => $transaction->payroll_transaction_id,
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'shift_code_id' => $shiftCodeId,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'raw_payroll_shift_codes',
            recordId: $raw->payroll_shift_code_id,
            newValues: [
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'batch_no' => $transaction->batch_no,
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'shift_code_id' => $shiftCodeId,
            ],
            description: 'Uploaded day shift code for employee '.$employeeNumber
                .' on '.$workDate
                .' in upload batch no. '.$transaction->batch_no,
        );

        $override = EmployeeShiftOverride::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($override) {
            $oldValues = [
                'employee_id' => $override->employee_id,
                'work_date' => $override->work_date?->toDateString(),
                'shift_code_id' => $override->shift_code_id,
            ];

            $override->update(['shift_code_id' => $shiftCodeId]);
            $override->refresh();

            SysLogService::record(
                action: 'update',
                table: 'tbl_employee_shift_overrides',
                recordId: $override->employee_shift_override_id,
                oldValues: $oldValues,
                newValues: [
                    'employee_id' => $override->employee_id,
                    'work_date' => $override->work_date?->toDateString(),
                    'shift_code_id' => $override->shift_code_id,
                ],
                description: 'Updated day shift override from upload for employee '.$employeeNumber
                    .' on '.$workDate
                    .' (upload batch no. '.$transaction->batch_no.')',
            );

            return;
        }

        $override = EmployeeShiftOverride::query()->create([
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'shift_code_id' => $shiftCodeId,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'tbl_employee_shift_overrides',
            recordId: $override->employee_shift_override_id,
            newValues: [
                'employee_id' => $override->employee_id,
                'work_date' => $override->work_date?->toDateString(),
                'shift_code_id' => $override->shift_code_id,
            ],
            description: 'Created day shift override from upload for employee '.$employeeNumber
                .' on '.$workDate
                .' (upload batch no. '.$transaction->batch_no.')',
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistOvertimeRow(RawPayrollTransaction $transaction, array $row): void
    {
        $employeeId = (int) $row['employee_id'];
        $workDate = Carbon::parse($row['work_date'])->toDateString();
        $otStartAt = (string) ($row['ot_start_at'] ?? '');
        $otEndAt = (string) ($row['ot_end_at'] ?? '');
        $billableMinutes = (int) ($row['billable_minutes'] ?? 0);
        $employeeNumber = Employee::query()->whereKey($employeeId)->value('employee_number') ?? (string) $employeeId;

        if ($otStartAt === '' || $otEndAt === '') {
            $employee = Employee::query()
                ->with(['timekeepingSetup.shiftCode'])
                ->find($employeeId);

            $summary = $this->overtimeApprovals->validateForStore(
                $employeeId,
                $workDate,
                (string) $row['ot_start'],
                (string) $row['ot_end'],
                null,
                $employee?->timekeepingSetup?->shiftCode,
            );

            $otStartAt = $summary['ot_start']->toDateTimeString();
            $otEndAt = $summary['ot_end']->toDateTimeString();
            $billableMinutes = $summary['billable_minutes'];
        }

        $raw = RawPayrollOvertime::query()->create([
            'payroll_transaction_id' => $transaction->payroll_transaction_id,
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'ot_start' => $otStartAt,
            'ot_end' => $otEndAt,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'raw_payroll_overtimes',
            recordId: $raw->payroll_overtime_id,
            newValues: [
                'payroll_transaction_id' => $transaction->payroll_transaction_id,
                'batch_no' => $transaction->batch_no,
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'ot_start' => $otStartAt,
                'ot_end' => $otEndAt,
                'billable_minutes' => $billableMinutes,
            ],
            description: 'Uploaded overtime approval for employee '.$employeeNumber
                .' on '.$workDate
                .' ('.$otStartAt.' – '.$otEndAt.')'
                .' in upload batch no. '.$transaction->batch_no,
        );

        $approval = EmployeeOvertimeApproval::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->where('ot_start', $otStartAt)
            ->first();

        if ($approval) {
            $oldValues = [
                'employee_id' => $approval->employee_id,
                'work_date' => $approval->work_date?->toDateString(),
                'ot_start' => $approval->ot_start?->toDateTimeString(),
                'ot_end' => $approval->ot_end?->toDateTimeString(),
            ];

            $approval->update([
                'ot_end' => $otEndAt,
            ]);
            $approval->refresh();

            SysLogService::record(
                action: 'update',
                table: 'tbl_employee_overtime_approvals',
                recordId: $approval->employee_overtime_approval_id,
                oldValues: $oldValues,
                newValues: [
                    'employee_id' => $approval->employee_id,
                    'work_date' => $approval->work_date?->toDateString(),
                    'ot_start' => $approval->ot_start?->toDateTimeString(),
                    'ot_end' => $approval->ot_end?->toDateTimeString(),
                    'billable_minutes' => $billableMinutes,
                ],
                description: 'Updated overtime approval from upload for employee '.$employeeNumber
                    .' on '.$workDate
                    .' (upload batch no. '.$transaction->batch_no.')',
            );

            return;
        }

        $approval = EmployeeOvertimeApproval::query()->create([
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'ot_start' => $otStartAt,
            'ot_end' => $otEndAt,
        ]);

        SysLogService::record(
            action: 'create',
            table: 'tbl_employee_overtime_approvals',
            recordId: $approval->employee_overtime_approval_id,
            newValues: [
                'employee_id' => $approval->employee_id,
                'work_date' => $approval->work_date?->toDateString(),
                'ot_start' => $approval->ot_start?->toDateTimeString(),
                'ot_end' => $approval->ot_end?->toDateTimeString(),
                'billable_minutes' => $billableMinutes,
            ],
            description: 'Created overtime approval from upload for employee '.$employeeNumber
                .' on '.$workDate
                .' (upload batch no. '.$transaction->batch_no.')',
        );
    }

    private function nextBatchNumber(int $transactionTypeId): int
    {
        $last = RawPayrollTransaction::query()
            ->where('payroll_transaction_type_id', $transactionTypeId)
            ->max('batch_no');

        return ((int) $last) + 1;
    }

    private function stagingCacheKey(int $userId, string $token): string
    {
        return "payroll_upload_staging:{$userId}:{$token}";
    }
}
