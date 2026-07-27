<?php

namespace Database\Seeders;

use App\Models\DeductionType;
use App\Models\PayrollCalendarDeduction;
use App\Support\SssDeductionTypes;
use Illuminate\Database\Seeder;

class SssMpfDeductionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $mpf = DeductionType::query()->updateOrCreate(
            ['deduction_type_code' => SssDeductionTypes::MPF],
            [
                'description' => 'SSS MPF',
                'employer_amount' => 0,
                'is_valid_govt_deduction' => true,
                'govt_table_id' => 3,
                'is_active' => true,
            ],
        );

        $sssPremiumId = DeductionType::query()
            ->where('deduction_type_code', SssDeductionTypes::PREMIUM)
            ->value('deduction_type_id');

        if ($sssPremiumId === null) {
            return;
        }

        // Attach SSS MPF to every payroll calendar that already schedules SSS Premium.
        $calendarIds = PayrollCalendarDeduction::query()
            ->where('deduction_type_id', (int) $sssPremiumId)
            ->pluck('payroll_calendar_id')
            ->unique()
            ->all();

        foreach ($calendarIds as $calendarId) {
            PayrollCalendarDeduction::query()->firstOrCreate([
                'payroll_calendar_id' => (int) $calendarId,
                'deduction_type_id' => (int) $mpf->deduction_type_id,
            ]);
        }
    }
}
