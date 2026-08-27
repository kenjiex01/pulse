<?php

namespace App\Services\Reports;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollCalendar;
use App\Models\User;
use Illuminate\Support\Collection;

class PayrollRegisterRowBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForBatches(iterable $batches, User $user, ?string $employeeType = null): array
    {
        $rows = [];
        $index = 0;
        $employeeType = $employeeType !== null ? strtolower(trim($employeeType)) : null;

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! $this->detailIsVisible($detail, $user)) {
                    continue;
                }

                if ($employeeType !== null && ! $this->employeeMatchesType($detail->employee, $employeeType)) {
                    continue;
                }

                $index++;
                $rows[] = $this->buildRow($detail, $batch, $index);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRow(PayrollBatchDetail $detail, PayrollBatch $batch, int $index): array
    {
        $employee = $detail->employee;
        $calendar = $batch->payrollCalendar;
        $employment = $this->resolveEmploymentForRow($employee);

        $incomesByCode = $detail->incomes
            ->groupBy(fn ($income) => strtoupper((string) ($income->incomeType?->income_type_code ?? '')));

        $deductionsByCode = $detail->deductions
            ->groupBy(fn ($deduction) => strtoupper((string) ($deduction->deductionType?->deduction_type_code ?? '')));

        $basc = $this->sumIncomeCodes($incomesByCode, ['BASC']);
        $holiday = $this->sumIncomeCodes($incomesByCode, config('payroll_register_layout.holiday_income_codes', []));
        $specialHoliday = $this->sumIncomeCodes(
            $incomesByCode,
            config('payroll_register_staff_layout.special_holiday_income_codes', []),
        );
        $holidayDuty = $this->sumIncomeCodes(
            $incomesByCode,
            config('payroll_register_staff_layout.holiday_duty_income_codes', []),
        );
        $holOt = $this->sumIncomeCodes(
            $incomesByCode,
            config('payroll_register_staff_layout.hol_ot_income_codes', []),
        );
        $overtimeRd = $this->sumIncomeCodes(
            $incomesByCode,
            config('payroll_register_staff_layout.overtime_rd_income_codes', []),
        );
        $overtime = $this->sumIncomeCodes($incomesByCode, ['OVRT']);
        $late = $this->sumDeductionCodes($deductionsByCode, ['LTDE', 'UTDE']);
        $deMinimis = $this->sumIncomeCodes($incomesByCode, ['DEMN']);

        $taxableIncome = $detail->incomes->sum(fn ($income) => (float) $income->taxable);
        $nonTaxableIncome = $detail->incomes->sum(fn ($income) => (float) $income->non_taxable);
        $grossIncome = $taxableIncome + $nonTaxableIncome;
        $totalDeductions = $detail->deductions->sum(fn ($deduction) => (float) $deduction->employee_amount);
        $netPay = $grossIncome - $totalDeductions;

        $hourlyRate = $basc['hours'] > 0
            ? round($basc['amount'] / $basc['hours'], 2)
            : null;

        $salary = $employment?->salary;
        $hoursPerDay = (float) ($salary?->hours_per_day ?? 8);
        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8.0;
        }

        $days = $basc['days'] > 0
            ? round($basc['days'], 4)
            : ($basc['hours'] > 0 ? round($basc['hours'] / $hoursPerDay, 4) : 0.0);

        $basicRate = null;
        if ($days > 0 && $basc['amount'] > 0) {
            $basicRate = round($basc['amount'] / $days, 2);
        } elseif ($hourlyRate !== null) {
            $basicRate = round($hourlyRate * $hoursPerDay, 2);
        } else {
            $basicAmount = $salary?->basicIncomeAmount() ?? 0.0;
            $daysPerPeriod = (float) ($salary?->days_per_period ?? 0);
            if ($basicAmount > 0 && $daysPerPeriod > 0) {
                $basicRate = round($basicAmount / $daysPerPeriod, 2);
            }
        }

        $dailyRate = ($hourlyRate !== null && $hoursPerDay > 0)
            ? round($hourlyRate * $hoursPerDay, 2)
            : $basicRate;

        $lateMinutes = $late['hours'] > 0
            ? round($late['hours'] * 60, 2)
            : null;

        $grossPhilhealth = round(max(0.0, $grossIncome - $overtime['amount']), 2);
        $overtimeTotal = round(
            $overtime['amount'] + $overtimeRd['amount'] + $holiday['amount']
            + $holidayDuty['amount'] + $specialHoliday['amount'] + $holOt['amount'],
            2,
        );

        $sssPremium = $this->deductionAmount($deductionsByCode, ['SSSP']);
        $sssProvident = $this->deductionAmount($deductionsByCode, ['SSMP']);

        return [
            'index' => $index,
            'employee_number' => (string) ($employee?->employee_number ?? ''),
            'last_name' => trim((string) ($employee?->last_name ?? '')),
            'first_name' => trim((string) ($employee?->first_name ?? '')),
            'middle_name' => trim((string) ($employee?->middle_name ?? '')),
            'employee_name' => $this->formatRegisterEmployeeName($employee),
            'college' => (string) ($employee?->college ?? ''),
            'program' => (string) ($employee?->program ?? ''),
            'tax_status' => (string) ($employee?->tax_status ?? ''),
            'employment_status' => (string) ($employee?->employment_status ?? ''),
            'user_type' => (string) ($employment?->user_type ?? ''),
            'payroll_period' => $calendar ? $this->formatPeriodLabel($calendar) : '',
            'period_sheet' => $this->periodSheetKey($calendar),
            'pay_date' => $this->resolvePayDate($calendar),
            'ft_pt' => $this->resolveFtPtLabel($employment?->user_type, $employment?->employment_type),
            'hourly_rate' => $hourlyRate,
            'daily_rate' => $dailyRate,
            'basic_rate' => $basicRate,
            'days' => $days > 0 ? $days : null,
            'total_pay' => $this->formatNumber($basc['amount']),
            'rank' => (string) ($employment?->rank ?? ''),
            'basc_hours' => $this->formatNumber($basc['hours'], 2),
            'basc_amount' => $this->formatNumber($basc['amount']),
            'total_hours_both' => $this->formatNumber($basc['hours'], 2),
            'total_pay_both' => $this->formatNumber($basc['amount']),
            'holiday_hours' => $this->formatNumber($holiday['hours'], 2),
            'holiday_hours_total' => $this->formatNumber($holiday['hours'], 2),
            'holiday_days' => $this->formatNumber($holiday['days'] > 0 ? $holiday['days'] : $holiday['hours'], 2),
            'holiday_amount' => $this->formatNumber($holiday['amount']),
            'lhol_amount' => $this->formatNumber($holiday['amount']),
            'holiday_duty_hours' => $this->formatNumber($holidayDuty['hours'], 2),
            'holiday_duty_amount' => $this->formatNumber($holidayDuty['amount']),
            'special_holiday_hours' => $this->formatNumber($specialHoliday['hours'], 2),
            'special_holiday_amount' => $this->formatNumber($specialHoliday['amount']),
            'hol_ot_hours' => $this->formatNumber($holOt['hours'], 2),
            'hol_ot_amount' => $this->formatNumber($holOt['amount']),
            'late_hours' => $this->formatNumber($late['hours'], 2),
            'late_minutes' => $lateMinutes,
            'late_amount' => $this->formatNumber($late['amount']),
            'total_tardiness_amount' => $this->formatNumber($late['amount']),
            'overtime_hours' => $this->formatNumber($overtime['hours'], 2),
            'overtime_amount' => $this->formatNumber($overtime['amount']),
            'overtime_reg_hours' => $this->formatNumber($overtime['hours'], 2),
            'overtime_reg_amount' => $this->formatNumber($overtime['amount']),
            'overtime_rd_hours' => $this->formatNumber($overtimeRd['hours'], 2),
            'overtime_rd_amount' => $this->formatNumber($overtimeRd['amount']),
            'overtime_total' => $this->formatNumber($overtimeTotal),
            'department' => (string) ($employee?->department ?? ''),
            'campus_code' => $this->resolveCampusCode($employee),
            'campus_sheet' => $this->resolveCampusSheet($employee),
            'gross_income' => $this->formatNumber($grossIncome),
            'gross_philhealth' => $this->formatNumber($grossPhilhealth),
            'gross_taxable' => $this->formatNumber($taxableIncome),
            'tax_withheld' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['WHTX'])),
            'pagibig' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['PIBG'])),
            'sss' => $this->formatNumber($sssPremium),
            'sss_provident' => $this->formatNumber($sssProvident),
            'philhealth' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['PHIL', 'PHIM'])),
            'pagibig_loan' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['MPL'])),
            'pagibig_calamity' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['PCAL'])),
            'sss_loan' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['SSAL', 'SSEL'])),
            'sss_calamity' => $this->formatNumber($this->deductionAmount($deductionsByCode, ['SCAL'])),
            'ca_property_loan' => null,
            'shortages' => null,
            'uniform_textile' => null,
            'incomplete_uniform' => null,
            'safety_shoes' => null,
            'vc_securities' => null,
            'labor_day_deduction' => null,
            'de_minimis' => $this->formatNumber($deMinimis['amount']),
            'water_elec_bill' => null,
            'sss_prev_cutoff' => null,
            'food_contribution' => null,
            'adjustment_sss_loan' => null,
            'total_deductions' => $this->formatNumber($totalDeductions),
            'taxable_income' => $this->formatNumber($taxableIncome),
            'net_pay' => $this->formatNumber($netPay),
        ];
    }

    public function periodSheetKey(?PayrollCalendar $calendar): string
    {
        if ($calendar?->dt_from === null || $calendar?->dt_to === null) {
            return 'Register';
        }

        return sprintf(
            '%s-%s',
            $calendar->dt_from->format('j'),
            $calendar->dt_to->format('j'),
        );
    }

    public function employeeMatchesType(?\App\Models\Employee $employee, string $employeeType): bool
    {
        if ($employee === null) {
            return false;
        }

        $employeeType = strtolower(trim($employeeType));

        $infos = $employee->relationLoaded('employmentInformations')
            ? $employee->employmentInformations
            : $employee->employmentInformations()->get();

        return $infos->contains(
            fn ($info) => strtolower((string) ($info->user_type ?? '')) === $employeeType
        );
    }

    private function resolveEmploymentForRow(?\App\Models\Employee $employee): mixed
    {
        if ($employee === null) {
            return null;
        }

        $infos = $employee->employmentInformations ?? collect();

        return $infos
            ->sortBy('sort_order')
            ->first(fn ($info) => in_array(strtolower((string) ($info->user_type ?? '')), ['staff', 'admin'], true))
            ?? $infos->sortBy('sort_order')->first();
    }

    public function resolveCampusSheet(?\App\Models\Employee $employee): string
    {
        $sheetCampus = $this->sheetCampus($employee);
        $code = strtoupper(trim((string) ($sheetCampus?->campus_code ?? $this->resolveCampusCode($employee))));
        $default = (string) config('payroll_register_layout.excel_campus_sheet_default', 'Cainta');
        $sheets = config('payroll_register_layout.excel_campus_sheets', []);

        if ($code !== '') {
            foreach ($sheets as $sheetName => $codes) {
                $codes = array_map(fn ($item) => strtoupper((string) $item), (array) $codes);

                if (in_array($code, $codes, true)) {
                    return (string) $sheetName;
                }
            }
        }

        $campusName = trim((string) ($sheetCampus?->campus_name ?? ''));
        if ($campusName !== '') {
            return $this->shortCampusSheetName($campusName, $default);
        }

        if ($code !== '') {
            return $code;
        }

        return $default;
    }

    public function shortCampusSheetName(string $campusName, string $default = 'Cainta'): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $campusName) ?? '');
        $name = preg_replace('/^ICCT Colleges\s+/i', '', $name) ?? $name;
        $name = preg_replace('/\s+Campus$/i', '', $name) ?? $name;
        $name = preg_replace('/\s+Main$/i', '', $name) ?? $name;
        $name = trim($name);

        return $name !== '' ? $name : $default;
    }

    public function resolveCampusCode(?\App\Models\Employee $employee): string
    {
        if ($employee === null) {
            return '';
        }

        $code = trim((string) ($this->mainCampus($employee)?->campus_code ?? ''));

        if ($code !== '') {
            return $code;
        }

        return strtoupper(trim((string) ($employee->getAttributes()['campus'] ?? '')));
    }

    /**
     * Campus used for Payroll Register worksheets: the assignment marked Main assignment.
     */
    private function mainCampus(?\App\Models\Employee $employee): ?\App\Models\Campus
    {
        if ($employee === null) {
            return null;
        }

        if ($employee->relationLoaded('campusAssignments')) {
            $assignment = $employee->campusAssignments
                ->first(fn ($row) => (bool) ($row->is_primary ?? false))
                ?? $employee->campusAssignments->first();
            $fromAssignment = $assignment?->campus;

            if (is_object($fromAssignment)) {
                return $fromAssignment;
            }
        }

        $fromEmployee = $employee->relationLoaded('campus')
            ? $employee->getRelation('campus')
            : $employee->campus;

        return is_object($fromEmployee) ? $fromEmployee : null;
    }

    private function sheetCampus(?\App\Models\Employee $employee): ?\App\Models\Campus
    {
        $campus = $this->mainCampus($employee);

        if ($campus === null) {
            return null;
        }

        return $campus->payrollRegisterCampus();
    }

    public function formatPeriodLabel(PayrollCalendar $calendar): string
    {
        $from = $calendar->dt_from;
        $to = $calendar->dt_to;

        if ($from === null || $to === null) {
            return $calendar->formattedPayPeriod();
        }

        if ($from->format('F Y') === $to->format('F Y')) {
            return $from->format('F j').' - '.$to->format('j, Y');
        }

        return $from->format('M j, Y').' - '.$to->format('M j, Y');
    }

    public function periodTitleForBatches(iterable $batches): string
    {
        $labels = [];

        foreach ($batches as $batch) {
            $calendar = $batch->payrollCalendar;

            if ($calendar === null) {
                continue;
            }

            $labels[] = $this->formatPeriodLabel($calendar);
        }

        return implode(' · ', array_values(array_unique($labels)));
    }

    public function sheetTitleForBatches(iterable $batches): string
    {
        $batch = collect($batches)->first();
        $calendar = $batch?->payrollCalendar;

        if ($calendar?->dt_from === null || $calendar?->dt_to === null) {
            return 'Payroll Register';
        }

        return sprintf(
            '%s-%s (PER HOUR)',
            $calendar->dt_from->format('n.j'),
            $calendar->dt_to->format('n.j.y'),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $registerRows
     * @return array{
     *     headers: array<int, string>,
     *     subheaders: array<int, string>,
     *     rows: array<int, array<int, string>>,
     *     highlight_indices: array<int, int>
     * }
     */
    public function buildLayoutTable(array $registerRows, string $layoutConfig = 'payroll_register_layout'): array
    {
        $columns = config("{$layoutConfig}.columns", config('payroll_register_layout.columns', []));
        $headers = [];
        $subheaders = [];
        $highlightIndices = [];
        $rows = [];

        foreach ($columns as $index => $column) {
            $headers[] = (string) ($column['row5'] ?? '');
            $subheaders[] = (string) ($column['row6'] ?? '');

            if (! empty($column['highlight'])) {
                $highlightIndices[] = $index;
            }
        }

        foreach ($registerRows as $registerRow) {
            $row = [];

            foreach ($columns as $column) {
                $field = $column['field'] ?? null;

                if ($field === null || $field === '') {
                    $row[] = '';

                    continue;
                }

                $row[] = $this->formatLayoutCell($registerRow[$field] ?? null);
            }

            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'subheaders' => $subheaders,
            'rows' => $rows,
            'highlight_indices' => $highlightIndices,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $registerRows
     * @return array<int, array<string, mixed>>
     */
    public function sortRegisterRows(array $registerRows, string $sortBy): array
    {
        $allowedSort = array_keys(config('payroll_reports.sort_columns', []));

        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'employee_name';
        }

        usort($registerRows, function (array $left, array $right) use ($sortBy): int {
            if ($sortBy === 'employee_name') {
                return $this->compareRegisterName($left, $right);
            }

            $leftValue = strtolower((string) ($left[$sortBy] ?? ''));
            $rightValue = strtolower((string) ($right[$sortBy] ?? ''));

            $compared = $leftValue <=> $rightValue;

            return $compared !== 0 ? $compared : $this->compareRegisterName($left, $right);
        });

        foreach ($registerRows as $index => &$registerRow) {
            $registerRow['index'] = $index + 1;
        }
        unset($registerRow);

        return $registerRows;
    }

    /**
     * Payroll Register name: Last Name, First Name Middle Name.
     */
    public function formatRegisterEmployeeName(?\App\Models\Employee $employee): string
    {
        if ($employee === null) {
            return '';
        }

        $last = trim((string) ($employee->last_name ?? ''));
        $first = trim((string) ($employee->first_name ?? ''));
        $middle = trim((string) ($employee->middle_name ?? ''));
        $given = trim(implode(' ', array_filter([$first, $middle], fn ($part) => $part !== '')));

        if ($last === '') {
            return $given !== '' ? $given : (string) ($employee->full_name ?? '');
        }

        return $given !== '' ? $last.', '.$given : $last;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareRegisterName(array $left, array $right): int
    {
        $leftLast = strtolower((string) ($left['last_name'] ?? ''));
        $rightLast = strtolower((string) ($right['last_name'] ?? ''));
        $byLast = $leftLast <=> $rightLast;

        if ($byLast !== 0) {
            return $byLast;
        }

        $leftFirst = strtolower((string) ($left['first_name'] ?? ''));
        $rightFirst = strtolower((string) ($right['first_name'] ?? ''));
        $byFirst = $leftFirst <=> $rightFirst;

        if ($byFirst !== 0) {
            return $byFirst;
        }

        $leftMiddle = strtolower((string) ($left['middle_name'] ?? ''));
        $rightMiddle = strtolower((string) ($right['middle_name'] ?? ''));
        $byMiddle = $leftMiddle <=> $rightMiddle;

        if ($byMiddle !== 0) {
            return $byMiddle;
        }

        $leftName = strtolower((string) ($left['employee_name'] ?? ''));
        $rightName = strtolower((string) ($right['employee_name'] ?? ''));
        $byName = $leftName <=> $rightName;

        if ($byName !== 0) {
            return $byName;
        }

        return strtolower((string) ($left['employee_number'] ?? ''))
            <=> strtolower((string) ($right['employee_number'] ?? ''));
    }

    private function detailIsVisible(PayrollBatchDetail $detail, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! (bool) ($detail->employee?->is_confidential ?? false);
    }

    private function resolvePayDate(?PayrollCalendar $calendar): string
    {
        if ($calendar?->dt_to === null) {
            return '';
        }

        return $calendar->dt_to->copy()->addDays(10)->format('F j, Y');
    }

    private function resolveFtPtLabel(?string $userType, ?string $employmentType): string
    {
        $employmentType = strtolower((string) $employmentType);

        if (str_contains($employmentType, 'part')) {
            return 'Ptp';
        }

        return match ($userType) {
            'faculty' => 'Ftr',
            'staff', 'admin' => 'Str',
            default => '',
        };
    }

    /**
     * @param  Collection<string, Collection<int, mixed>>  $incomesByCode
     * @param  array<int, string>  $codes
     * @return array{hours: float, amount: float, days: float}
     */
    private function sumIncomeCodes(Collection $incomesByCode, array $codes): array
    {
        $hours = 0.0;
        $amount = 0.0;
        $days = 0.0;

        foreach ($codes as $code) {
            $lines = $incomesByCode->get(strtoupper($code), collect());

            foreach ($lines as $line) {
                $hours += (float) ($line->hours ?? 0);
                $days += (float) ($line->days ?? 0);
                $amount += (float) $line->taxable + (float) $line->non_taxable;
            }
        }

        return compact('hours', 'amount', 'days');
    }

    /**
     * @param  Collection<string, Collection<int, mixed>>  $deductionsByCode
     * @param  array<int, string>  $codes
     * @return array{hours: float, amount: float}
     */
    private function sumDeductionCodes(Collection $deductionsByCode, array $codes): array
    {
        $hours = 0.0;
        $amount = 0.0;

        foreach ($codes as $code) {
            $lines = $deductionsByCode->get(strtoupper($code), collect());

            foreach ($lines as $line) {
                $hours += (float) ($line->hours ?? 0);
                $amount += (float) $line->employee_amount;
            }
        }

        return compact('hours', 'amount');
    }

    /**
     * @param  Collection<string, Collection<int, mixed>>  $deductionsByCode
     * @param  array<int, string>  $codes
     */
    private function deductionAmount(Collection $deductionsByCode, array $codes): float
    {
        return $this->sumDeductionCodes($deductionsByCode, $codes)['amount'];
    }

    private function formatNumber(float $value, int $decimals = 2): ?float
    {
        if ($value == 0.0) {
            return null;
        }

        return round($value, $decimals);
    }

    private function formatLayoutCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $float = (float) $value;

            if ($float == 0.0) {
                return '';
            }

            $formatted = number_format($float, 4, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return (string) $value;
    }
}
