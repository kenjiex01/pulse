<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeWeeklyShift;
use App\Models\TimekeepingHolidayGroup;
use App\Models\User;
use App\Support\TimekeepingEmployeeProfile;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimekeepingEmployeeProfileWeeklyShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_store_persists_weekly_shift_codes_per_day(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-WEEKLY-SHIFT',
            'first_name' => 'Per',
            'last_name' => 'Day',
            'email' => 'per.day@example.com',
        ]);

        $weekday = ShiftCode::query()->create([
            'shift_code' => 'MONFRI',
            'description' => 'Mon–Fri 8-5',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
        ]);
        $saturday = ShiftCode::query()->create([
            'shift_code' => 'SATSH',
            'description' => 'Sat 9-6',
            'time_in' => '09:00:00',
            'time_out' => '18:00:00',
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'WKPR',
            'description' => 'Weekly profile test',
        ]);
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'WKPR',
            'policy_name' => 'Weekly Profile Policy',
            'is_active' => true,
        ]);

        $weeklyShifts = [];
        foreach ([2, 3, 4, 5, 6] as $dayId) {
            $weeklyShifts[$dayId] = $weekday->shift_code_id;
        }
        $weeklyShifts[7] = $saturday->shift_code_id;
        $weeklyShifts[1] = $weekday->shift_code_id;

        $this->actingAs($user)
            ->post(route(TimekeepingEmployeeProfile::routeName('store'), $employee->employee_id), [
                'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
                'timekeeping_policy_id' => $policy->timekeeping_policy_id,
                'weekly_shifts' => $weeklyShifts,
                'rest_days' => [
                    1 => ['selected' => 1],
                ],
            ])
            ->assertRedirect();

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
