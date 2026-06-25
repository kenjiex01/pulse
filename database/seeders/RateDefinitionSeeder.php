<?php

namespace Database\Seeders;

use App\Models\ComputationBasis;
use App\Models\DayType;
use App\Models\IncomeType;
use App\Models\LuDay;
use App\Models\NdRateGroup;
use App\Models\NdRateGroupDayType;
use App\Models\RateBasis;
use App\Models\RateGroup;
use App\Models\RateGroupDayType;
use App\Models\TimeType;
use Illuminate\Database\Seeder;

class RateDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ] as $id => $day) {
            LuDay::query()->updateOrCreate(['day_id' => $id], ['day' => $day]);
        }

        foreach ([
            1 => 'Computation Basis Rate Per Hour',
            2 => 'Fixed Amount Per Hour',
        ] as $id => $rateBasis) {
            RateBasis::query()->updateOrCreate(['rate_basis_id' => $id], ['rate_basis' => $rateBasis]);
        }

        foreach ([
            ['time_type_id' => 1, 'time_type_code' => 'BP', 'description' => 'Basic', 'time_class_id' => 1],
            ['time_type_id' => 2, 'time_type_code' => 'OT', 'description' => 'Overtime', 'time_class_id' => 1],
            ['time_type_id' => 3, 'time_type_code' => 'SOT', 'description' => 'Special Overtime', 'time_class_id' => 1],
            ['time_type_id' => 4, 'time_type_code' => 'NDIF', 'description' => 'ND Basic', 'time_class_id' => 2],
            ['time_type_id' => 5, 'time_type_code' => 'NOT', 'description' => 'ND Overtime', 'time_class_id' => 2],
            ['time_type_id' => 6, 'time_type_code' => 'NSOT', 'description' => 'ND Special Overtime', 'time_class_id' => 2],
        ] as $timeType) {
            TimeType::query()->updateOrCreate(['time_type_id' => $timeType['time_type_id']], $timeType);
        }

        $dayTypes = [
            ['day_type_id' => 1, 'day_type_code' => 'LEGL', 'description' => 'Legal Holiday', 'is_legal_holiday' => true],
            ['day_type_id' => 3, 'day_type_code' => 'REGU', 'description' => 'Regular'],
            ['day_type_id' => 4, 'day_type_code' => 'RES', 'description' => 'Restday', 'is_restday' => true],
            ['day_type_id' => 5, 'day_type_code' => 'RSLG', 'description' => 'Restday and Legal Holiday', 'is_restday' => true, 'is_legal_holiday' => true],
            ['day_type_id' => 6, 'day_type_code' => 'RSSP', 'description' => 'Restday and Special Holiday', 'is_restday' => true, 'is_special_holiday' => true],
            ['day_type_id' => 7, 'day_type_code' => 'SPCL', 'description' => 'Special Holiday', 'is_special_holiday' => true],
            ['day_type_id' => 8, 'day_type_code' => 'SUND', 'description' => 'Sunday', 'day_id' => 1],
        ];

        foreach ($dayTypes as $dayType) {
            DayType::query()->updateOrCreate(['day_type_id' => $dayType['day_type_id']], $dayType);
        }

        $rateGroup = RateGroup::query()->updateOrCreate(
            ['rate_group_code' => 'DGRP'],
            [
                'rate_basis_id' => 1,
                'description' => 'Default Rate Group',
            ],
        );

        $ndRateGroup = NdRateGroup::query()->updateOrCreate(
            ['nd_rate_group_code' => 'DNGR'],
            [
                'rate_basis_id' => 1,
                'description' => 'Default Night Diff. Rate Group',
                'tm_start' => '22:00',
                'tm_end' => '06:00',
            ],
        );

        $basicIncome = IncomeType::query()->where('income_type_code', 'BASC')->first();
        $overtimeIncome = IncomeType::query()->where('income_type_code', 'OVRT')->first();
        $computationBasisId = ComputationBasis::query()->where('computation_basis_id', 7)->value('computation_basis_id');

        if ($basicIncome && $overtimeIncome && $computationBasisId) {
            RateGroupDayType::query()->where('rate_group_id', $rateGroup->rate_group_id)->delete();

            $regularRates = [
                [1, 1, 1.0000],
                [1, 2, 2.6000],
                [3, 1, 1.0000],
                [3, 2, 1.2500],
                [4, 1, 1.3000],
                [4, 2, 1.6900],
                [5, 1, 2.6000],
                [5, 2, 3.3800],
                [6, 1, 1.5000],
                [6, 2, 1.9500],
                [7, 1, 1.3000],
                [7, 2, 1.6900],
                [8, 1, 1.3000],
                [8, 2, 1.6900],
            ];

            foreach ($regularRates as [$dayTypeId, $timeTypeId, $rate]) {
                RateGroupDayType::query()->create([
                    'rate_group_id' => $rateGroup->rate_group_id,
                    'day_type_id' => $dayTypeId,
                    'time_type_id' => $timeTypeId,
                    'computation_basis_id' => $computationBasisId,
                    'income_type_id' => $timeTypeId === 1 ? $basicIncome->income_type_id : $overtimeIncome->income_type_id,
                    'rate' => $rate,
                    'is_taxable' => true,
                ]);
            }

            NdRateGroupDayType::query()->where('nd_rate_group_id', $ndRateGroup->nd_rate_group_id)->delete();

            $ndRates = [
                [1, 4, 0.1000],
                [1, 5, 0.2600],
                [3, 4, 0.1000],
                [3, 5, 0.1250],
                [4, 4, 0.1300],
                [4, 5, 0.1690],
                [5, 4, 0.2600],
                [5, 5, 0.3380],
                [6, 4, 0.1500],
                [6, 5, 0.2000],
                [7, 4, 0.1300],
                [7, 5, 0.1690],
                [8, 4, 0.1300],
                [8, 5, 0.1700],
            ];

            foreach ($ndRates as [$dayTypeId, $timeTypeId, $rate]) {
                NdRateGroupDayType::query()->create([
                    'nd_rate_group_id' => $ndRateGroup->nd_rate_group_id,
                    'day_type_id' => $dayTypeId,
                    'time_type_id' => $timeTypeId,
                    'computation_basis_id' => $computationBasisId,
                    'income_type_id' => $overtimeIncome->income_type_id,
                    'rate' => $rate,
                    'is_taxable' => true,
                ]);
            }
        }
    }
}
