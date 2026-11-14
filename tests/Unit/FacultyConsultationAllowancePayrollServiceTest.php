<?php

namespace Tests\Unit;

use App\Models\BasicComputation;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\LuDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\TimekeepingEmployeeConsultationHour;
use App\Models\User;
use App\Services\FacultyConsultationAllowancePayrollService;
use App\Services\PayrollBatchService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacultyConsultationAllowancePayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_expected_hours_count_consultation_days_in_pay_period_mon_and_thu_only(): void
    {
        $employee = $this->facultyWithConsultationSetup(hoursPerDay: 2.0, dayIds: [2, 5]);

        $employment = $employee->employmentInformations()->firstOrFail();
        $salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => 26,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'is_fixed_rate' => true,
        ]);

        $from = CarbonImmutable::parse('2026-06-01');
        $to = CarbonImmutable::parse('2026-06-30');

        $expected = app(FacultyConsultationAllowancePayrollService::class)
            ->expectedHoursForPeriod($salary, $employee, $from, $to);

        // June 2026: 5 Mondays + 4 Thursdays × 2h consultation = 18h
        $this->assertSame(18.0, $expected);
    }

    public function test_faculty_without_consultation_setup_has_zero_expected_hours_and_no_allowance_income(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-NOCH-'.uniqid(),
            'first_name' => 'No',
            'last_name' => 'Consult',
        ]);

        $employee->employmentInformations()->create([
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 1,
        ]);

        $detail = $this->allowanceBatchDetailFor($employee);
        $salary = $employee->employmentInformations()->firstOrFail()->salaries()->firstOrFail();

        $expected = app(FacultyConsultationAllowancePayrollService::class)
            ->expectedHoursForPeriod(
                $salary,
                $employee,
                CarbonImmutable::parse('2026-06-01'),
                CarbonImmutable::parse('2026-06-30'),
            );

        $this->assertSame(0.0, $expected);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $allowanceTypeId = IncomeType::query()->where('income_type_code', 'DEMN')->value('income_type_id');

        $this->assertNull(
            PayrollIncome::query()
                ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
                ->where('income_type_id', $allowanceTypeId)
                ->first(),
        );
    }

    public function test_actual_hours_credit_overlap_with_consultation_slots(): void
    {
        $employee = $this->facultyWithConsultationSetup(hoursPerDay: 2.0, scheduledDays: 1, dayId: 2);
        $service = app(FacultyConsultationAllowancePayrollService::class);

        $date = CarbonImmutable::parse('2026-06-15'); // Monday
        $this->assertSame(2, LuDay::idFromDate($date));

        $this->punch($employee, $date->setTime(9, 0)->toDateTimeString(), true);
        $this->punch($employee, $date->setTime(11, 0)->toDateTimeString(), false);

        $actual = $service->actualConsultationHours(
            (int) $employee->employee_id,
            $employee,
            $date,
            $date,
        );

        $this->assertSame(2.0, $actual);
    }

    public function test_payroll_batch_creates_allowance_deduction_when_short_of_required_hours(): void
    {
        $employee = $this->facultyWithConsultationSetup(hoursPerDay: 2.0, scheduledDays: 6);
        $detail = $this->allowanceBatchDetailFor($employee);

        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $deductionTypeId = DeductionType::query()->where('deduction_type_code', 'ALWD')->value('deduction_type_id');

        $deduction = PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
            ->where('deduction_type_id', $deductionTypeId)
            ->first();

        $this->assertNotNull($deduction);
        $this->assertSame(26.0, (float) $deduction->hours);
        $this->assertSame(5000.0, (float) $deduction->employee_amount);
    }

    public function test_no_allowance_deduction_when_consultation_hours_are_fully_met(): void
    {
        $employee = $this->facultyWithConsultationSetup(hoursPerDay: 2.0, scheduledDays: 6);
        $from = CarbonImmutable::parse('2026-06-16');
        $to = CarbonImmutable::parse('2026-06-30');

        $cursor = $from;
        while ($cursor->lte($to)) {
            if ($employee->consultationHours->firstWhere('day_id', LuDay::idFromDate($cursor)) !== null) {
                $this->punch($employee, $cursor->setTime(9, 0)->toDateTimeString(), true);
                $this->punch($employee, $cursor->setTime(11, 0)->toDateTimeString(), false);
            }
            $cursor = $cursor->addDay();
        }

        $detail = $this->allowanceBatchDetailFor($employee, $from, $to);
        app(PayrollBatchService::class)->prepareDetailTransactions($detail);

        $deductionTypeId = DeductionType::query()->where('deduction_type_code', 'ALWD')->value('deduction_type_id');

        $this->assertNull(
            PayrollDeduction::query()
                ->where('payroll_batch_detail_id', $detail->payroll_batch_detail_id)
                ->where('deduction_type_id', $deductionTypeId)
                ->first(),
        );
    }

    /**
     * @param  list<int>|null  $dayIds
     */
    private function facultyWithConsultationSetup(
        float $hoursPerDay,
        int $scheduledDays = 6,
        ?int $dayId = null,
        ?array $dayIds = null,
    ): Employee {
        $employee = Employee::query()->create([
            'employee_number' => 'FAC-ALW-'.uniqid(),
            'first_name' => 'Fac',
            'last_name' => 'Allowance',
        ]);

        $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_FACULTY,
            'sort_order' => 1,
        ]);

        if ($dayIds !== null) {
            $ids = $dayIds;
        } elseif ($dayId !== null) {
            $ids = [$dayId];
        } else {
            $ids = array_slice([2, 3, 4, 5, 6, 7], 0, $scheduledDays);
        }

        foreach ($ids as $id) {
            TimekeepingEmployeeConsultationHour::query()->create([
                'employee_id' => $employee->employee_id,
                'day_id' => $id,
                'time_start' => '09:00',
                'time_end' => sprintf('%02d:00', 9 + (int) $hoursPerDay),
                'sort_order' => 0,
            ]);
        }

        return $employee->fresh(['consultationHours', 'employmentInformations.salaries.incomes']);
    }

    private function allowanceBatchDetailFor(
        Employee $employee,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        float $daysPerPeriod = 26,
    ): PayrollBatchDetail {
        $user = User::query()->firstOrFail();
        $from ??= CarbonImmutable::parse('2026-06-16');
        $to ??= CarbonImmutable::parse('2026-06-30');

        $employment = $employee->employmentInformations()->firstOrFail();

        $salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'days_per_period' => $daysPerPeriod,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'is_fixed_rate' => true,
        ]);

        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'DEMN')->value('income_type_id'),
            'taxable' => 0,
            'non_taxable' => 5000,
            'sort_order' => 0,
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => $from,
            'dt_to' => $to,
            'calendar_month' => (int) $from->format('n'),
            'is_regular_period' => null,
            'is_allowance_period' => true,
        ]);

        $calendar->userTypes()->create(['user_type' => EmployeeEmploymentInformation::TYPE_FACULTY]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        return PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);
    }

    private function punch(Employee $employee, string $dateTime, bool $isIn): void
    {
        $transaction = RawTimekeepingTransaction::query()->create([
            'timekeeping_transaction_type_id' => RawTimekeepingTransaction::TYPE_TIME_IN_OUT,
            'dt_from' => $dateTime,
            'dt_to' => $dateTime,
            'uploaded_by_id' => 0,
            'batch_no' => 1,
        ]);

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => $employee->employee_id,
            'dt_datetime' => $dateTime,
            'is_in' => $isIn,
        ]);
    }
}
