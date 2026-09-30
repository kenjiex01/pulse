<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Services\TimekeepingEmployeeProfileUploadService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TimekeepingEmployeeProfileUploadSkipEmptyRowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_rows_without_core_setup_values_are_skipped_not_flagged(): void
    {
        $service = app(TimekeepingEmployeeProfileUploadService::class);

        $noSetup = Employee::query()->create([
            'employee_number' => 'EMP-NO-SETUP',
            'first_name' => 'No',
            'last_name' => 'Setup',
            'email' => 'no.setup@example.com',
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'SKIP-WD',
            'description' => 'Skip test weekday',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
        ]);
        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'SKIP-HG',
            'description' => 'Skip test HG',
        ]);
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'SKIP-POL',
            'policy_name' => 'Skip test policy',
            'is_active' => true,
        ]);

        $withSetup = Employee::query()->create([
            'employee_number' => 'EMP-WITH-SETUP',
            'first_name' => 'With',
            'last_name' => 'Setup',
            'email' => 'with.setup@example.com',
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $withSetup->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $aliases = $service->fieldAliases();
        $aliasIndex = array_flip($aliases);

        $blankRow = array_fill(0, count($aliases), '');
        $blankRow[$aliasIndex['emp_num']] = $noSetup->employee_number;

        $filledRow = array_fill(0, count($aliases), '');
        $filledRow[$aliasIndex['emp_num']] = $withSetup->employee_number;
        $filledRow[$aliasIndex['holiday_group_code']] = $holidayGroup->timekeeping_holiday_group_code;
        $filledRow[$aliasIndex['policy_name']] = $policy->policy_name;
        $filledRow[$aliasIndex['shift_code']] = $shift->shift_code;

        $csv = $this->csvLine($aliases)."\n"
            .$this->csvLine($service->fieldHeaders())."\n"
            .$this->csvLine($service->fieldDescriptions())."\n"
            .$this->csvLine($blankRow)."\n"
            .$this->csvLine($filledRow)."\n";

        $path = tempnam(sys_get_temp_dir(), 'skip-upload-');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'profile.csv', 'text/csv', null, true);

        $parsed = $service->parseUploadedFile($file);

        $this->assertSame(1, $parsed['valid_count']);
        $this->assertSame([], $parsed['errors']);
        $this->assertSame('EMP-WITH-SETUP', $parsed['valid'][0]['emp_num'] ?? null);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function csvLine(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $cells);
        rewind($handle);
        $line = stream_get_contents($handle) ?: '';
        fclose($handle);

        return rtrim($line, "\n");
    }
}
