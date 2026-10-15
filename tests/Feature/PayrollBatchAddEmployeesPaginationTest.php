<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollCalendarUserType;
use App\Models\PayType;
use App\Models\User;
use App\Support\PayrollTransactionModule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBatchAddEmployeesPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_add_employees_modal_pages_past_the_first_set(): void
    {
        $user = User::query()->firstOrFail();
        $batch = $this->makeStaffBatch();
        $marker = 'PaginateStaff';

        foreach (range(1, 26) as $index) {
            $this->makeStaffEmployee($marker, $index);
        }

        $pageOne = $this->actingAs($user)->get(route(PayrollTransactionModule::routeName('tab'), [
            'tab' => 'batches',
            'view_payroll_batch' => $batch->payroll_batch_id,
            'add_employees' => 1,
            'add_employee_search' => $marker,
        ]));

        $pageOne->assertOk();
        $pageOne->assertSee('Showing 1–25 of 26', false);
        $pageOne->assertSee('EMP-PAG-01');
        $pageOne->assertDontSee('EMP-PAG-26');
        $pageOne->assertSee('add_employee_page=2', false);
        $pageOne->assertSee('>Next</a>', false);
        $pageOne->assertDontSee('Showing first 200');

        $pageTwo = $this->actingAs($user)->get(route(PayrollTransactionModule::routeName('tab'), [
            'tab' => 'batches',
            'view_payroll_batch' => $batch->payroll_batch_id,
            'add_employees' => 1,
            'add_employee_search' => $marker,
            'add_employee_page' => 2,
        ]));

        $pageTwo->assertOk();
        $pageTwo->assertSee('Showing 26–26 of 26', false);
        $pageTwo->assertSee('EMP-PAG-26');
        $pageTwo->assertSee('add_employee_page=1', false);
        $pageTwo->assertSee('>Previous</a>', false);
        $pageTwo->assertSee('add_employees=1', false);
    }

    private function makeStaffEmployee(string $marker, int $index): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-PAG-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'first_name' => 'A'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'last_name' => $marker,
            'is_active' => true,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $employment = $employee->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'days_per_period' => 11,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
        ]);
    }

    private function makeStaffBatch(): PayrollBatch
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        PayrollCalendarUserType::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
        ]);

        return PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => User::query()->value('id'),
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);
    }
}
