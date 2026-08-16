<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\User;
use App\Support\GovernmentIdNumbers;
use App\Support\PhilhealthDeductionTypes;
use App\Support\SssDeductionTypes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BirFormEmployeeSelection
{
    public function __construct(
        private readonly ReportBatchOptionsService $batchOptions,
        private readonly PayrollContributionBatchSupport $batchSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     batch: PayrollBatch|null,
     *     lines: list<array<string, mixed>>,
     *     period_label: string,
     *     period_from: string,
     *     period_to: string,
     *     pay_year: int,
     *     calendar_month: int,
     *     month_of_return: string,
     *     batch_label: string,
     *     batch_count: int
     * }
     */
    public function resolve(array $options, User $user): array
    {
        $batchIds = collect($options['payroll_batch_ids'] ?? [])
            ->when(
                collect($options['payroll_batch_ids'] ?? [])->isEmpty() && (int) ($options['payroll_batch_id'] ?? 0) > 0,
                fn ($ids) => $ids->push((int) $options['payroll_batch_id']),
            )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($batchIds === []) {
            throw ValidationException::withMessages([
                'payroll_batch_ids' => 'Please select at least one posted payroll batch.',
            ]);
        }

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Please select at least one employee.',
            ]);
        }

        $batches = PayrollBatch::query()
            ->with([
                'payrollCalendar.payType',
                'details' => fn ($query) => $query->whereIn('employee_id', $employeeIds),
                'details.employee',
                'details.employee.employmentInformations.salary',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
            ])
            ->whereIn('payroll_batch_id', $batchIds)
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->orderBy('batch_no')
            ->get();

        if ($batches->count() !== count($batchIds)) {
            throw ValidationException::withMessages([
                'payroll_batch_ids' => 'All selected payroll batches must be posted.',
            ]);
        }

        $this->batchSupport->assertSamePayMonthAndYear($batches);

        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! in_array((int) $detail->employee_id, $employeeIds, true)) {
                    continue;
                }

                if (! $this->batchSupport->detailIsVisible($detail, $user)) {
                    continue;
                }

                $line = $this->buildLine($detail);

                if ($line === null) {
                    continue;
                }

                $employeeId = (int) $line['employee_id'];

                if (! isset($byEmployee[$employeeId])) {
                    $byEmployee[$employeeId] = $line;

                    continue;
                }

                $byEmployee[$employeeId] = $this->mergeLines($byEmployee[$employeeId], $line);
            }
        }

        $lines = array_values($byEmployee);
        usort($lines, fn (array $left, array $right) => strcasecmp($left['employee_name'], $right['employee_name']));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'No posted payroll amounts found for the selected employees in the selected batches.',
            ]);
        }

        $calendar = $batches->first()?->payrollCalendar;
        $payYear = (int) ($calendar?->pay_year ?? 0);
        $calendarMonth = (int) ($calendar?->calendar_month ?? 0);

        $periodFrom = $batches
            ->map(fn (PayrollBatch $batch) => $batch->payrollCalendar?->dt_from)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();
        $periodTo = $batches
            ->map(fn (PayrollBatch $batch) => $batch->payrollCalendar?->dt_to)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sortDesc()
            ->first();

        $periodFromStr = $periodFrom?->format('Y-m-d') ?? '';
        $periodToStr = $periodTo?->format('Y-m-d') ?? '';
        $periodLabel = $periodFromStr !== '' && $periodToStr !== ''
            ? $periodFrom->format('M d, Y').' – '.$periodTo->format('M d, Y')
            : ($payYear > 0 && $calendarMonth > 0
                ? date('F Y', mktime(0, 0, 0, $calendarMonth, 1, $payYear))
                : '');
        $monthOfReturn = $payYear > 0 && $calendarMonth > 0
            ? date('F Y', mktime(0, 0, 0, $calendarMonth, 1, $payYear))
            : $periodLabel;

        $batchLabels = $batches
            ->map(fn (PayrollBatch $batch) => $this->batchOptions->batchLabel($batch))
            ->values()
            ->all();

        return [
            'batch' => $batches->count() === 1 ? $batches->first() : null,
            'lines' => $lines,
            'period_label' => $periodLabel,
            'period_from' => $periodFromStr,
            'period_to' => $periodToStr,
            'pay_year' => $payYear,
            'calendar_month' => $calendarMonth,
            'month_of_return' => $monthOfReturn,
            'batch_label' => implode(' · ', $batchLabels),
            'batch_count' => $batches->count(),
        ];
    }

    /**
     * Sum all employees with posted payroll across a payroll year (Alphalist).
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     batch: null,
     *     lines: list<array<string, mixed>>,
     *     period_label: string,
     *     period_from: string,
     *     period_to: string,
     *     pay_year: int,
     *     calendar_month: int,
     *     month_of_return: string,
     *     batch_label: string
     * }
     */
    public function resolveAllForYear(array $options, User $user): array
    {
        $payYear = (int) ($options['pay_year'] ?? 0);

        if ($payYear < 1000 || $payYear > 9999) {
            throw ValidationException::withMessages([
                'pay_year' => 'Please select a payroll year.',
            ]);
        }

        $employeeIds = PayrollBatchDetail::query()
            ->whereHas('payrollBatch', function ($query) use ($payYear, $user) {
                $query->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
                    ->whereHas('payrollCalendar', fn ($calendar) => $calendar->where('pay_year', $payYear))
                    ->where(function ($locked) use ($user) {
                        $locked->whereNull('locked_for_id')
                            ->orWhere('locked_for_id', $user->id);
                    });
            })
            ->distinct()
            ->orderBy('employee_id')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'pay_year' => 'No employees with posted payroll found for the selected payroll year.',
            ]);
        }

        return $this->resolveForYear([
            ...$options,
            'pay_year' => $payYear,
            'employee_ids' => $employeeIds,
        ], $user);
    }

    /**
     * Sum selected employees across all posted batches in a payroll year (BIR 2316).
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     batch: null,
     *     lines: list<array<string, mixed>>,
     *     period_label: string,
     *     period_from: string,
     *     period_to: string,
     *     pay_year: int,
     *     calendar_month: int,
     *     month_of_return: string,
     *     batch_label: string
     * }
     */
    public function resolveForYear(array $options, User $user): array
    {
        $payYear = (int) ($options['pay_year'] ?? 0);
        $employeeIds = collect($options['employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($payYear < 1000 || $payYear > 9999) {
            throw ValidationException::withMessages([
                'pay_year' => 'Please select a payroll year.',
            ]);
        }

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Please select at least one employee.',
            ]);
        }

        $batches = PayrollBatch::query()
            ->with([
                'payrollCalendar',
                'details' => fn ($query) => $query->whereIn('employee_id', $employeeIds),
                'details.employee',
                'details.employee.employmentInformations.salary',
                'details.incomes.incomeType',
                'details.deductions.deductionType',
            ])
            ->where('payroll_batch_status_id', PayrollBatchStatus::POSTED)
            ->whereHas('payrollCalendar', fn ($query) => $query->where('pay_year', $payYear))
            ->where(function ($query) use ($user) {
                $query->whereNull('locked_for_id')
                    ->orWhere('locked_for_id', $user->id);
            })
            ->orderBy('batch_no')
            ->get();

        if ($batches->isEmpty()) {
            throw ValidationException::withMessages([
                'pay_year' => 'No posted payroll batches found for the selected payroll year.',
            ]);
        }

        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = [];

        foreach ($batches as $batch) {
            foreach ($batch->details as $detail) {
                if (! in_array((int) $detail->employee_id, $employeeIds, true)) {
                    continue;
                }

                if (! $this->batchSupport->detailIsVisible($detail, $user)) {
                    continue;
                }

                $line = $this->buildLine($detail);

                if ($line === null) {
                    continue;
                }

                $employeeId = (int) $line['employee_id'];

                if (! isset($byEmployee[$employeeId])) {
                    $byEmployee[$employeeId] = $line;

                    continue;
                }

                $byEmployee[$employeeId] = $this->mergeLines($byEmployee[$employeeId], $line);
            }
        }

        $lines = array_values($byEmployee);
        usort($lines, fn (array $left, array $right) => strcasecmp($left['employee_name'], $right['employee_name']));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'No posted payroll amounts found for the selected employees in this payroll year.',
            ]);
        }

        $periodFrom = sprintf('%04d-01-01', $payYear);
        $periodTo = sprintf('%04d-12-31', $payYear);

        return [
            'batch' => null,
            'lines' => $lines,
            'period_label' => 'Payroll Year '.$payYear,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'pay_year' => $payYear,
            'calendar_month' => 12,
            'month_of_return' => 'December '.$payYear,
            'batch_label' => 'All posted batches · '.$payYear,
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function mergeLines(array $left, array $right): array
    {
        foreach ([
            'taxable_compensation',
            'non_taxable_compensation',
            'gross_compensation',
            'tax_withheld',
            'sss_contribution',
            'philhealth_contribution',
            'pagibig_contribution',
        ] as $field) {
            $left[$field] = round((float) $left[$field] + (float) $right[$field], 2);
        }

        /** @var array<string, array{taxable: float, non_taxable: float}> $breakdown */
        $breakdown = $left['income_breakdown'] ?? [];

        foreach (($right['income_breakdown'] ?? []) as $code => $amounts) {
            if (! isset($breakdown[$code])) {
                $breakdown[$code] = [
                    'taxable' => 0.0,
                    'non_taxable' => 0.0,
                ];
            }

            $breakdown[$code]['taxable'] = round(
                (float) $breakdown[$code]['taxable'] + (float) ($amounts['taxable'] ?? 0),
                2,
            );
            $breakdown[$code]['non_taxable'] = round(
                (float) $breakdown[$code]['non_taxable'] + (float) ($amounts['non_taxable'] ?? 0),
                2,
            );
        }

        $left['income_breakdown'] = $breakdown;
        $left['is_above_minimum_wage_earner'] = (bool) $left['is_above_minimum_wage_earner']
            || (bool) $right['is_above_minimum_wage_earner'];

        return $left;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildLine(PayrollBatchDetail $detail): ?array
    {
        $employee = $detail->employee;

        if ($employee === null) {
            return null;
        }

        $taxable = 0.0;
        $nonTaxable = 0.0;
        /** @var array<string, array{taxable: float, non_taxable: float}> $breakdown */
        $breakdown = [];

        foreach ($detail->incomes as $income) {
            $tax = (float) $income->taxable;
            $non = (float) $income->non_taxable;
            $taxable += $tax;
            $nonTaxable += $non;

            $code = \App\Support\Bir2316FormMapper::codeForIncomeType($income->incomeType);
            if (! isset($breakdown[$code])) {
                $breakdown[$code] = ['taxable' => 0.0, 'non_taxable' => 0.0];
            }
            $breakdown[$code]['taxable'] += $tax;
            $breakdown[$code]['non_taxable'] += $non;
        }

        $taxWithheld = 0.0;
        $sss = 0.0;
        $philhealth = 0.0;
        $pagibig = 0.0;

        foreach ($detail->deductions as $deduction) {
            $code = strtoupper((string) ($deduction->deductionType?->deduction_type_code ?? ''));
            $amount = (float) $deduction->employee_amount;

            if ($code === 'WHTX') {
                $taxWithheld += $amount;
            }

            if (in_array($code, [SssDeductionTypes::PREMIUM, SssDeductionTypes::MPF], true)) {
                $sss += $amount;
            }

            if (in_array($code, PhilhealthDeductionTypes::EXCLUSIVE_CODES, true)) {
                $philhealth += $amount;
            }

            if ($code === 'PIBG') {
                $pagibig += $amount;
            }
        }

        $tinDigits = GovernmentIdNumbers::normalize($employee->tin_number);
        $birthDate = $employee->birth_date
            ? Carbon::parse($employee->birth_date)->format('Y-m-d')
            : '';

        $isAboveMwe = $employee->employmentInformations
            ->contains(fn ($info) => (bool) ($info->salary?->is_above_minimum_wage_earner ?? false));

        $addressParts = array_filter([
            trim((string) ($employee->address_line ?? '')),
            trim((string) ($employee->barangay ?? '')),
            trim((string) ($employee->city_municipality ?? '')),
            trim((string) ($employee->province ?? '')),
        ]);

        $phone = trim((string) (
            $employee->phone
            ?: $employee->home_phone
            ?: $employee->work_phone
            ?: ''
        ));

        $hireDate = null;
        $employmentFrom = null;
        $employmentTo = null;
        $hasOpenSalary = false;

        foreach ($employee->employmentInformations as $info) {
            if ($info->hire_date) {
                $candidate = Carbon::parse($info->hire_date);
                $hireDate = $hireDate === null || $candidate->lt($hireDate) ? $candidate : $hireDate;
            }

            $salary = $info->salary;
            if ($salary === null) {
                continue;
            }

            if ($salary->date_effective_from) {
                $from = Carbon::parse($salary->date_effective_from);
                $employmentFrom = $employmentFrom === null || $from->lt($employmentFrom)
                    ? $from
                    : $employmentFrom;
            }

            if ($salary->date_effective_to === null) {
                $hasOpenSalary = true;
            } else {
                $to = Carbon::parse($salary->date_effective_to);
                $employmentTo = $employmentTo === null || $to->gt($employmentTo)
                    ? $to
                    : $employmentTo;
            }
        }

        return [
            'employee_id' => (int) $employee->employee_id,
            'employee_number' => (string) ($employee->employee_number ?? ''),
            'employee_name' => $this->formatEmployeeName($employee),
            'employee_name_upper' => strtoupper($this->formatEmployeeName($employee)),
            'last_name' => trim((string) ($employee->last_name ?? '')),
            'first_name' => trim((string) ($employee->first_name ?? '')),
            'middle_name' => trim((string) ($employee->middle_name ?? '')),
            'tin' => $tinDigits ?? '',
            'tin_formatted' => GovernmentIdNumbers::format($employee->tin_number, GovernmentIdNumbers::TYPE_TIN),
            'address' => implode(', ', $addressParts),
            'postal_code' => preg_replace('/\D/', '', (string) ($employee->postal_code ?? '')) ?? '',
            'local_home_address' => '',
            'foreign_address' => '',
            'phone' => $phone,
            'birth_date' => $birthDate,
            'birth_date_display' => $birthDate !== ''
                ? Carbon::parse($birthDate)->format('m/d/Y')
                : '',
            'birth_date_mmddyyyy' => $birthDate !== ''
                ? Carbon::parse($birthDate)->format('mdY')
                : '',
            'tax_status' => trim((string) ($employee->tax_status ?? '')),
            'employment_status' => trim((string) ($employee->employment_status ?? '')),
            'hire_date' => $hireDate?->format('Y-m-d') ?? '',
            'employment_from' => $employmentFrom?->format('Y-m-d') ?? ($hireDate?->format('Y-m-d') ?? ''),
            'employment_to' => $hasOpenSalary ? '' : ($employmentTo?->format('Y-m-d') ?? ''),
            'has_open_salary' => $hasOpenSalary,
            'taxable_compensation' => round($taxable, 2),
            'non_taxable_compensation' => round($nonTaxable, 2),
            'gross_compensation' => round($taxable + $nonTaxable, 2),
            'tax_withheld' => round($taxWithheld, 2),
            'sss_contribution' => round($sss, 2),
            'philhealth_contribution' => round($philhealth, 2),
            'pagibig_contribution' => round($pagibig, 2),
            'income_breakdown' => $breakdown,
            'is_above_minimum_wage_earner' => $isAboveMwe,
        ];
    }

    private function formatEmployeeName(Employee $employee): string
    {
        $parts = array_filter([
            trim((string) ($employee->last_name ?? '')),
            trim(implode(' ', array_filter([
                trim((string) ($employee->first_name ?? '')),
                trim((string) ($employee->middle_name ?? '')),
                trim((string) ($employee->suffix ?? '')),
            ]))),
        ]);

        if ($parts === []) {
            return (string) ($employee->full_name ?? '');
        }

        $given = $parts[1] ?? '';

        return $given !== '' ? $parts[0].', '.$given : $parts[0];
    }
}
