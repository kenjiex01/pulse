<?php

namespace Database\Seeders;

use App\Models\LeaveProcessingMode;
use App\Models\LuExcessHour;
use App\Models\LuNonRegularOt;
use App\Models\LuRounding;
use App\Models\TimekeepingPolicy as TimekeepingPolicyModel;
use App\Models\TimekeepingPolicyTeamSetting;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Illuminate\Database\Seeder;

class TimekeepingPolicySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLookups();
        $this->seedPolicy();
    }

    private function seedLookups(): void
    {
        foreach ([
            ['excess_hour_id' => 1, 'excess_hour' => 'Disregard excess hours'],
            ['excess_hour_id' => 2, 'excess_hour' => 'Consider excess hours as OT'],
            ['excess_hour_id' => 3, 'excess_hour' => 'Offset late with excess hours and disregard remainder'],
            ['excess_hour_id' => 4, 'excess_hour' => 'Offset late with excess hours and consider remainder'],
        ] as $row) {
            LuExcessHour::query()->updateOrCreate(
                ['excess_hour_id' => $row['excess_hour_id']],
                ['excess_hour' => $row['excess_hour']],
            );
        }

        foreach ([
            ['rounding_id' => 1, 'rounding' => 'Round Off'],
            ['rounding_id' => 2, 'rounding' => 'Round Up'],
        ] as $row) {
            LuRounding::query()->updateOrCreate(
                ['rounding_id' => $row['rounding_id']],
                ['rounding' => $row['rounding']],
            );
        }

        foreach ([
            ['non_regular_ot_id' => 0, 'description' => 'Automatically compute regular and OT hours'],
            ['non_regular_ot_id' => 1, 'description' => 'Require Filling of Forms to compute regular and OT hours'],
            ['non_regular_ot_id' => 2, 'description' => 'Compute regular hours but require filling of forms for OT hours'],
        ] as $row) {
            LuNonRegularOt::query()->updateOrCreate(
                ['non_regular_ot_id' => $row['non_regular_ot_id']],
                ['description' => $row['description']],
            );
        }

        foreach ([
            ['leave_processing_mode_id' => 1, 'mode_label' => 'Use applied leaves'],
            ['leave_processing_mode_id' => 2, 'mode_label' => 'Deduct hrs. rendered'],
        ] as $row) {
            LeaveProcessingMode::query()->updateOrCreate(
                ['leave_processing_mode_id' => $row['leave_processing_mode_id']],
                ['mode_label' => $row['mode_label']],
            );
        }

        foreach ([
            ['limit' => 0, 'description' => 'No Limit'],
            ['limit' => 1, 'description' => '1 Level'],
            ['limit' => 2, 'description' => '2 Levels'],
            ['limit' => 3, 'description' => '3 Levels'],
        ] as $index => $row) {
            TimekeepingPolicyTeamSetting::query()->updateOrCreate(
                ['timekeeping_policy_team_setting_id' => $index + 1],
                $row,
            );
        }
    }

    private function seedPolicy(): void
    {
        if (TimekeepingPolicyModel::query()->exists()) {
            return;
        }

        TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'DEFAULT',
            'policy_name' => 'Default Policy',
            'description' => 'Default timekeeping policy',
            'is_active' => true,
        ]);
    }
}
