<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\EmployeeCampusAssignment;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalary;
use App\Models\Report;
use App\Models\User;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeCredentialsReportService
{
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
        $sheet->setTitle('Employee');

        $sheet->fromArray([$result->title], null, 'A1');

        if (! empty($result->meta['filter_summary'])) {
            $sheet->fromArray([(string) $result->meta['filter_summary']], null, 'A2');
        }

        $sheet->fromArray($result->headers, null, 'A4');
        $sheet->fromArray($result->rows, null, 'A5');
        $sheet->freezePane('C5');

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Employee_'.now()->format('Ymd_His'),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, meta: array<string, mixed>}
     */
    private function buildDataset(array $options, User $user): array
    {
        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $employees = $this->employeesForReport($employeeIds, $user);
        $headers = $this->headers();
        $rows = [];

        foreach ($employees as $employee) {
            $rows[] = $this->buildRow($employee);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'meta' => [
                'filter_summary' => $this->filterSummary($employeeIds, count($rows)),
                'row_count' => count($rows),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            'Employee No.',
            'Employee Name',
            'Last Name',
            'First Name',
            'Middle Name',
            'Suffix',
            'Hybrid',
            'Active',
            'Employment Status',
            'Compliance Status',
            'Confidential',
            'Birth Date',
            'Place of Birth',
            'Gender',
            'Civil Status',
            'Nationality',
            'Religion',
            'Language / Dialect',
            'TIN',
            'SSS No.',
            'PhilHealth No.',
            'Pag-IBIG No.',
            'GSIS No.',
            'Tax Status',
            'Email',
            'Mobile',
            'Home Phone',
            'Work Phone',
            'Emergency Contact',
            'Emergency Relationship',
            'Emergency Phone',
            'Address',
            'Country',
            'Region',
            'Province',
            'City / Municipality',
            'Barangay',
            'Postal Code',
            'Primary Campus',
            'Assignments',
            'User Type',
            'Position',
            'Designation',
            'Rank',
            'Employment Type',
            'Hire Date',
            'Pay Type',
            'Basic Computation',
            'Days / Period',
            'Hours / Day',
            'Basic Amount',
            'Hourly Rate',
            'COLA / Hour',
            'Rate Group',
            'ND Rate Group',
            'Is Above minimum wage earner',
            'Salary Effective From',
            'Salary Incomes',
            'Salary Deductions',
            'Shift Code',
            'Shift Description',
            'Time In',
            'Time Out',
            'Flexi Time',
            'Expected Hours / Day',
            'Holiday Group',
            'Timekeeping Policy',
            'Rest Days',
            'Leave',
            'Populate',
            'Auto OT',
            'Loans',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildRow(Employee $employee): array
    {
        $employments = $employee->employmentInformations ?? collect();
        $tagSalary = (bool) $employee->is_hybrid;
        $setup = $employee->timekeepingSetup;
        $shift = $setup?->shiftCode;

        return [
            (string) ($employee->employee_number ?? ''),
            $this->formatEmployeeName($employee),
            trim((string) ($employee->last_name ?? '')),
            trim((string) ($employee->first_name ?? '')),
            trim((string) ($employee->middle_name ?? '')),
            trim((string) ($employee->suffix ?? '')),
            $this->yesNo((bool) $employee->is_hybrid),
            $this->yesNo((bool) $employee->is_active),
            (string) ($employee->employment_status ?? ''),
            (string) ($employee->compliance_status ?? ''),
            $this->yesNo((bool) $employee->is_confidential),
            $employee->birth_date?->format('Y-m-d') ?? '',
            (string) ($employee->place_of_birth ?? ''),
            (string) ($employee->gender ?? ''),
            (string) ($employee->civil_status ?? ''),
            (string) ($employee->nationality ?? ''),
            (string) ($employee->religion ?? ''),
            (string) ($employee->language_dialect ?? ''),
            (string) ($employee->tin_number ?? ''),
            (string) ($employee->sss_number ?? ''),
            (string) ($employee->philhealth_number ?? ''),
            (string) ($employee->pagibig_number ?? ''),
            (string) ($employee->gsis_number ?? ''),
            (string) ($employee->tax_status ?? ''),
            (string) ($employee->email ?? ''),
            (string) ($employee->phone ?? ''),
            (string) ($employee->home_phone ?? ''),
            (string) ($employee->work_phone ?? ''),
            (string) ($employee->emergency_contact_name ?? ''),
            (string) ($employee->emergency_contact_relationship ?? ''),
            (string) ($employee->emergency_contact_phone ?? ''),
            (string) ($employee->address_line ?? ''),
            (string) ($employee->country ?? ''),
            (string) ($employee->region ?? ''),
            (string) ($employee->province ?? ''),
            (string) ($employee->city_municipality ?? ''),
            (string) ($employee->barangay ?? ''),
            (string) ($employee->postal_code ?? ''),
            $this->primaryCampusLabel($employee),
            $this->assignmentsLabel($employee),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->user_type_label ?: $info->user_type)),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->position ?? '')),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->designation ?? '')),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->rank ?? '')),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->employment_type ?? '')),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $info->hire_date?->format('Y-m-d') ?? ''),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->salary?->payType?->pay_type ?? ''), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->salary?->basicComputation?->basic_computation ?? ''), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->numberOrBlank($info->salary?->days_per_period), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->numberOrBlank($info->salary?->hours_per_day), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->moneyOrBlank($info->salary?->basicIncomeAmount()), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->moneyOrBlank($info->salary?->hourlyRate()), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->moneyOrBlank($info->salary?->cola_rate_per_hour), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->salary?->rateGroup?->rate_group_code ?? $info->salary?->rateGroup?->description ?? ''), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => (string) ($info->salary?->ndRateGroup?->nd_rate_group_code ?? $info->salary?->ndRateGroup?->description ?? ''), $tagSalary),
            $this->joinEmployment($employments, function (EmployeeEmploymentInformation $info) {
                if ($info->salary === null) {
                    return '';
                }

                return $this->yesNo((bool) $info->salary->is_above_minimum_wage_earner);
            }, $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $info->salary?->date_effective_from?->format('Y-m-d') ?? '', $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->incomesLabel($info->salary), $tagSalary),
            $this->joinEmployment($employments, fn (EmployeeEmploymentInformation $info) => $this->deductionsLabel($info->salary), $tagSalary),
            (string) ($shift?->shift_code ?? ''),
            (string) ($shift?->description ?? ''),
            $this->formatTime($shift?->time_in),
            $this->formatTime($shift?->time_out),
            $shift ? $this->yesNo((bool) $shift->is_flexi_time) : '',
            $this->numberOrBlank($shift?->expected_hours_per_day),
            (string) ($setup?->holidayGroup?->timekeeping_holiday_group_code ?? $setup?->holidayGroup?->description ?? ''),
            (string) ($setup?->policy?->policy_name ?? $setup?->policy?->policy_code ?? ''),
            $this->restDaysLabel($employee),
            $setup ? $this->yesNo((bool) $setup->is_leave) : '',
            $setup ? $this->yesNo((bool) $setup->is_populate) : '',
            $setup ? $this->yesNo((bool) $setup->is_auto_compute_excess_as_ot) : '',
            $this->loansLabel($employee),
        ];
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return Collection<int, Employee>
     */
    private function employeesForReport(array $employeeIds, User $user): Collection
    {
        $query = Employee::query()
            ->with([
                'campus',
                'campusAssignments.campus',
                'employmentInformations.salary.payType',
                'employmentInformations.salary.basicComputation',
                'employmentInformations.salary.rateGroup',
                'employmentInformations.salary.ndRateGroup',
                'employmentInformations.salary.incomes.incomeType',
                'employmentInformations.salary.deductions.deductionType',
                'timekeepingSetup.shiftCode',
                'timekeepingSetup.holidayGroup',
                'timekeepingSetup.policy',
                'timekeepingRestDays.day',
                'loans.loanType',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->orderBy('employee_number');

        if ($employeeIds !== []) {
            $query->whereIn('employee_id', $employeeIds);
        }

        if (! $user->isAdmin()) {
            $query->where(function ($confidential) {
                $confidential->whereNull('is_confidential')
                    ->orWhere('is_confidential', false);
            });
        }

        return $query->get();
    }

    private function formatEmployeeName(Employee $employee): string
    {
        $last = trim((string) ($employee->last_name ?? ''));
        $first = trim((string) ($employee->first_name ?? ''));
        $middle = trim((string) ($employee->middle_name ?? ''));
        $given = trim(implode(' ', array_filter([$first, $middle], fn ($part) => $part !== '')));

        if ($last === '') {
            return $given !== '' ? $given : (string) ($employee->full_name ?? '');
        }

        return $given !== '' ? $last.', '.$given : $last;
    }

    private function primaryCampusLabel(Employee $employee): string
    {
        $name = trim((string) ($employee->campus_name ?? ''));
        $code = '';

        if ($employee->relationLoaded('campus') && is_object($employee->getRelation('campus'))) {
            $code = trim((string) ($employee->getRelation('campus')->campus_code ?? ''));
        }

        if ($name === '' && $code === '') {
            return trim((string) ($employee->getAttributes()['campus'] ?? ''));
        }

        return $code !== '' ? $name.' ['.$code.']' : $name;
    }

    private function assignmentsLabel(Employee $employee): string
    {
        $assignments = $employee->campusAssignments ?? collect();

        if ($assignments->isEmpty()) {
            return $this->primaryCampusLabel($employee);
        }

        return $assignments
            ->map(function (EmployeeCampusAssignment $assignment) {
                $campus = $assignment->campus;
                $name = trim((string) ($campus?->campus_name ?? ''));
                $code = trim((string) ($campus?->campus_code ?? ''));
                $label = $code !== '' ? ($name !== '' ? $name.' ['.$code.']' : $code) : $name;
                $parts = [];

                if ($assignment->is_primary) {
                    $parts[] = 'Primary';
                }

                $biometric = trim((string) ($assignment->biometric_id ?? ''));
                if ($biometric !== '') {
                    $parts[] = 'Bio '.$biometric;
                }

                foreach (['college', 'department', 'program'] as $field) {
                    $value = trim((string) ($assignment->{$field} ?? ''));
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                }

                return $parts === [] ? $label : $label.' ('.implode(', ', $parts).')';
            })
            ->filter()
            ->implode(' · ');
    }

    /**
     * @param  Collection<int, EmployeeEmploymentInformation>  $employments
     * @param  callable(EmployeeEmploymentInformation): string  $callback
     */
    private function joinEmployment(Collection $employments, callable $callback, bool $tagUserType = false): string
    {
        $values = $employments
            ->map(function (EmployeeEmploymentInformation $info) use ($callback, $tagUserType) {
                $value = trim((string) $callback($info));

                if ($value === '') {
                    return '';
                }

                if (! $tagUserType) {
                    return $value;
                }

                $tag = $this->employmentUserTypeTag($info);

                return $tag !== '' ? $value.' ('.$tag.')' : $value;
            })
            ->filter(fn (string $value) => $value !== '');

        if (! $tagUserType) {
            $values = $values->unique();
        }

        return $values->implode(' · ');
    }

    private function employmentUserTypeTag(EmployeeEmploymentInformation $info): string
    {
        return match (strtolower(trim((string) ($info->user_type ?? '')))) {
            'faculty' => 'Faculty',
            'staff' => 'Staff',
            'admin' => 'Admin',
            default => trim((string) ($info->user_type_label ?: $info->user_type)),
        };
    }

    private function incomesLabel(?EmployeeSalary $salary): string
    {
        if ($salary === null) {
            return '';
        }

        return $salary->incomes
            ->map(function ($income) {
                $code = (string) ($income->incomeType?->income_type_code ?? $income->incomeType?->description ?? 'Income');
                $amount = (float) $income->taxable + (float) $income->non_taxable;

                return $code.' '.$this->money($amount);
            })
            ->filter()
            ->implode('; ');
    }

    private function deductionsLabel(?EmployeeSalary $salary): string
    {
        if ($salary === null) {
            return '';
        }

        return $salary->deductions
            ->map(function ($deduction) {
                $code = (string) ($deduction->deductionType?->deduction_type_code ?? $deduction->deductionType?->description ?? 'Deduction');
                $ee = (float) $deduction->employee_amount;
                $er = (float) $deduction->employer_amount;

                return $code.' EE '.$this->money($ee).' ER '.$this->money($er);
            })
            ->filter()
            ->implode('; ');
    }

    private function restDaysLabel(Employee $employee): string
    {
        return $employee->timekeepingRestDays
            ->map(function ($restDay) {
                $day = (string) ($restDay->day?->day ?? '');
                if ($day === '') {
                    return '';
                }

                return $restDay->is_paid ? $day.' (Paid)' : $day;
            })
            ->filter()
            ->implode(', ');
    }

    private function loansLabel(Employee $employee): string
    {
        return $employee->loans
            ->map(function (EmployeeLoan $loan) {
                $type = (string) ($loan->loanType?->loan_type_code ?? $loan->loanType?->description ?? 'Loan');

                return $type.' '.$this->money((float) $loan->loan_amount);
            })
            ->filter()
            ->implode(' · ');
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $text = (string) $value;

        return preg_match('/^\d{2}:\d{2}/', $text) ? substr($text, 0, 5) : $text;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    private function numberOrBlank(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function moneyOrBlank(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->money((float) $value);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * @param  array<int, int>  $employeeIds
     */
    private function filterSummary(array $employeeIds, int $rowCount): string
    {
        $parts = [
            "{$rowCount} employee(s)",
        ];

        if ($employeeIds !== []) {
            $parts[] = count($employeeIds).' selected employee(s)';
        } else {
            $parts[] = 'all employees';
        }

        return implode(' · ', $parts);
    }
}
