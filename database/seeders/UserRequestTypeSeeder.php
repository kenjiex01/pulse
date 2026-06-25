<?php

namespace Database\Seeders;

use App\Models\LuUserRequestType;
use Illuminate\Database\Seeder;

class UserRequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['user_request_type_id' => 1, 'user_request_type' => 'Overtime', 'filename' => 'overtime.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 2, 'user_request_type' => 'Official Business Trip', 'filename' => 'obt.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 3, 'user_request_type' => 'Failure to Time in/Time Out', 'filename' => 'timeinout.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 4, 'user_request_type' => 'Work Schedule', 'filename' => 'workschedule.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 5, 'user_request_type' => 'Leave of Absence', 'filename' => 'leaves.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 6, 'user_request_type' => 'Cost Centers', 'filename' => 'costcenters.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 7, 'user_request_type' => 'Payroll Batch - Posting', 'filename' => 'paybatch_posting.php', 'is_employee' => false, 'is_user' => true],
            ['user_request_type_id' => 8, 'user_request_type' => 'Shift Code', 'filename' => 'shiftschedule.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 9, 'user_request_type' => 'HR Forms', 'filename' => 'hrforms.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 10, 'user_request_type' => 'Transfer of Approval Rights', 'filename' => 'transfer_approval.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 11, 'user_request_type' => 'TOIL Credit', 'filename' => 'toil.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 12, 'user_request_type' => 'Rest Day', 'filename' => 'restday.php', 'is_employee' => true, 'is_user' => false],
            ['user_request_type_id' => 13, 'user_request_type' => 'Transfer of Team', 'filename' => 'transfer_team.php', 'is_employee' => true, 'is_user' => false],
        ];

        foreach ($types as $type) {
            LuUserRequestType::query()->updateOrCreate(
                ['user_request_type_id' => $type['user_request_type_id']],
                $type,
            );
        }
    }
}
