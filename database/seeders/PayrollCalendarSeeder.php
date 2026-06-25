<?php

namespace Database\Seeders;

use App\Models\DeductionLoanPriority;
use App\Models\DeductionType;
use App\Models\LoanType;
use App\Models\PayrollSettingOther;
use Illuminate\Database\Seeder;

class PayrollCalendarSeeder extends Seeder
{
    public function run(): void
    {
        PayrollSettingOther::query()->firstOrCreate([], [
            'is_deduction_loan_priority_enabled' => false,
        ]);

        if (DeductionLoanPriority::query()->exists()) {
            return;
        }

        $priority = 1;

        DeductionType::query()->orderBy('description')->each(function (DeductionType $deductionType) use (&$priority): void {
            DeductionLoanPriority::query()->create([
                'deduction_type_id' => $deductionType->deduction_type_id,
                'loan_type_id' => null,
                'priority' => $priority,
            ]);
            $priority++;
        });

        LoanType::query()->orderBy('description')->each(function (LoanType $loanType) use (&$priority): void {
            DeductionLoanPriority::query()->create([
                'deduction_type_id' => null,
                'loan_type_id' => $loanType->loan_type_id,
                'priority' => $priority,
            ]);
            $priority++;
        });
    }
}
