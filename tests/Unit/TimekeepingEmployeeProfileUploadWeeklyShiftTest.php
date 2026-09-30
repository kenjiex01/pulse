<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeWeeklyShift;
use App\Models\TimekeepingHolidayGroup;
use App\Models\User;
use App\Services\TimekeepingEmployeeProfileUploadService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TimekeepingEmployeeProfileUploadWeeklyShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_upload_applies_per_day_shift_columns_to_weekly_schedule(): void
    {
        $service = app(TimekeepingEmployeeProfileUploadService::class);
        $user = User::query()->firstOrFail();

        $weekday = ShiftCode::query()->create([
            'shift_code' => 'UPL-WD',
            'description' => 'Upload weekday',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
        ]);
        $saturday = ShiftCode::query()->create([
            'shift_code' => 'UPL-SAT',
            'description' => 'Upload saturday',
            'time_in' => '09:00:00',
            'time_out' => '18:00:00',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-UPLOAD-WEEKLY',
            'first_name' => 'Upload',
            'last_name' => 'Weekly',
            'email' => 'upload.weekly@example.com',
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'UPL-HG',
            'description' => 'Upload holiday group',
        ]);
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'UPL-POL',
            'policy_name' => 'Upload policy',
            'is_active' => true,
        ]);

        $aliases = $service->fieldAliases();
        $row = array_fill(0, count($aliases), '');
        $aliasIndex = array_flip($aliases);

        $row[$aliasIndex['emp_num']] = $employee->employee_number;
        $row[$aliasIndex['holiday_group_code']] = $holidayGroup->timekeeping_holiday_group_code;
        $row[$aliasIndex['policy_name']] = $policy->policy_name;
        $row[$aliasIndex['shift_code']] = $weekday->shift_code;
        $row[$aliasIndex['shift_sat']] = $saturday->shift_code;
        $row[$aliasIndex['rest_sun']] = '1';

        $csv = implode(',', $aliases)."\n"
            .implode(',', $service->fieldHeaders())."\n"
            .implode(',', array_fill(0, count($aliases), 'note'))."\n"
            .implode(',', $row)."\n";

        $path = tempnam(sys_get_temp_dir(), 'weekly-upload-');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'profile.csv', 'text/csv', null, true);

        $parsed = $service->parseUploadedFile($file);
        $token = $service->createStagingToken($user, $parsed);
        $service->commit($user, $token);

        $this->assertDatabaseHas('tbl_timekeeping_employee_weekly_shifts', [
            'employee_id' => $employee->employee_id,
            'day_id' => 6,
            'shift_code_id' => $weekday->shift_code_id,
        ]);
        $this->assertDatabaseHas('tbl_timekeeping_employee_weekly_shifts', [
            'employee_id' => $employee->employee_id,
            'day_id' => 7,
            'shift_code_id' => $saturday->shift_code_id,
        ]);
        $this->assertDatabaseMissing('tbl_timekeeping_employee_weekly_shifts', [
            'employee_id' => $employee->employee_id,
            'day_id' => 1,
        ]);

        $this->assertSame(
            6,
            TimekeepingEmployeeWeeklyShift::query()->where('employee_id', $employee->employee_id)->count(),
        );
    }
}
