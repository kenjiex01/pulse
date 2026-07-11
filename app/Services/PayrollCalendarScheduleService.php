<?php

namespace App\Services;

use App\Models\DeductionType;
use App\Models\LoanType;
use App\Models\PayrollCalendar;
use App\Models\PayrollCalendarDeduction;
use App\Models\PayrollCalendarLoan;
use App\Support\PhilhealthDeductionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalendarScheduleService
{
    public function attachDefaultSchedule(PayrollCalendar $period): void
    {
        DB::transaction(function () use ($period): void {
            DeductionType::query()
                ->where('deduction_type_code', '!=', PhilhealthDeductionTypes::MINIMUM)
                ->pluck('deduction_type_id')
                ->each(function (int $deductionTypeId) use ($period): void {
                    PayrollCalendarDeduction::query()->firstOrCreate([
                        'payroll_calendar_id' => $period->payroll_calendar_id,
                        'deduction_type_id' => $deductionTypeId,
                    ]);
                });

            LoanType::query()->pluck('loan_type_id')->each(function (int $loanTypeId) use ($period): void {
                PayrollCalendarLoan::query()->firstOrCreate([
                    'payroll_calendar_id' => $period->payroll_calendar_id,
                    'loan_type_id' => $loanTypeId,
                ]);
            });
        });
    }

    /**
     * @param  array<int, int|string>  $deductionTypeIds
     * @param  array<int, int|string>  $loanTypeIds
     */
    public function sync(PayrollCalendar $period, array $deductionTypeIds, array $loanTypeIds): void
    {
        $deductionTypeIds = collect($deductionTypeIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $loanTypeIds = collect($loanTypeIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        DB::transaction(function () use ($period, $deductionTypeIds, $loanTypeIds): void {
            PayrollCalendarDeduction::query()
                ->where('payroll_calendar_id', $period->payroll_calendar_id)
                ->whereNotIn('deduction_type_id', $deductionTypeIds)
                ->delete();

            PayrollCalendarLoan::query()
                ->where('payroll_calendar_id', $period->payroll_calendar_id)
                ->whereNotIn('loan_type_id', $loanTypeIds)
                ->delete();

            foreach ($deductionTypeIds as $deductionTypeId) {
                PayrollCalendarDeduction::query()->firstOrCreate([
                    'payroll_calendar_id' => $period->payroll_calendar_id,
                    'deduction_type_id' => $deductionTypeId,
                ]);
            }

            foreach ($loanTypeIds as $loanTypeId) {
                PayrollCalendarLoan::query()->firstOrCreate([
                    'payroll_calendar_id' => $period->payroll_calendar_id,
                    'loan_type_id' => $loanTypeId,
                ]);
            }
        });
    }

    /**
     * @param  array<int, int|string>  $deductionTypeIds
     */
    public function assertExclusivePhilhealth(array $deductionTypeIds): void
    {
        $selectedCodes = DeductionType::query()
            ->whereIn('deduction_type_id', collect($deductionTypeIds)->map(fn ($id) => (int) $id)->filter()->all())
            ->whereIn('deduction_type_code', PhilhealthDeductionTypes::EXCLUSIVE_CODES)
            ->pluck('deduction_type_code')
            ->unique()
            ->values();

        if ($selectedCodes->count() > 1) {
            throw ValidationException::withMessages([
                'deduction_type_ids' => 'Select only one of Philhealth Premium or Philhealth Minimum.',
            ]);
        }
    }
}
