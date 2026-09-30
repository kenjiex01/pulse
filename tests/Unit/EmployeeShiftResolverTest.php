<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingEmployeeWeeklyShift;
use App\Models\TimekeepingHolidayGroup;
use App\Services\EmployeeShiftResolver;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShiftResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_weekly_shift_is_used_for_matching_weekday(): void
    {
        $weekdayShift = ShiftCode::query()->create([
            'shift_code' => 'WD85',
            'description' => 'Weekday 8-5',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
        ]);
        $saturdayShift = ShiftCode::query()->create([
            'shift_code' => 'SAT96',
            'description' => 'Saturday 9-6',
            'time_in' => '09:00:00',
            'time_out' => '18:00:00',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'WEEKLY-SHIFT-001',
            'first_name' => 'Weekly',
            'last_name' => 'Shift',
            'email' => 'weekly.shift@example.com',
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'WKSH',
            'description' => 'Weekly shift test',
        ]);
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'WKSH',
            'policy_name' => 'Weekly Shift Policy',
            'is_active' => true,
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $weekdayShift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        foreach ([2, 3, 4, 5, 6] as $dayId) {
            TimekeepingEmployeeWeeklyShift::query()->create([
                'employee_id' => $employee->employee_id,
                'day_id' => $dayId,
                'shift_code_id' => $weekdayShift->shift_code_id,
            ]);
        }

        TimekeepingEmployeeWeeklyShift::query()->create([
            'employee_id' => $employee->employee_id,
            'day_id' => 7,
            'shift_code_id' => $saturdayShift->shift_code_id,
        ]);

        $resolver = app(EmployeeShiftResolver::class);
        $monday = CarbonImmutable::parse('2026-09-28'); // Monday
        $saturday = CarbonImmutable::parse('2026-10-03'); // Saturday

        $this->assertSame('WD85', $resolver->forDate($employee, $monday)?->shift_code);
        $this->assertSame('SAT96', $resolver->forDate($employee, $saturday)?->shift_code);
    }
}
