<?php

namespace Tests\Feature;

use App\Models\BasicComputation;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\ShiftCode;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\User;
use App\Services\PayrollBatchService;
use App\Services\PayrollTransactionUploadService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollManualAttendanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PayrollCalendar $calendar;

    private PayrollBatch $batch;

    private PayrollBatchDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();

        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'MADT',
            'policy_name' => 'Manual attendance policy',
            'description' => 'Manual attendance deduction tests',
            'is_active' => true,
            'grace_period' => 0,
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'MAD8',
            'description' => 'Manual 8h',
            'time_in' => '08:00',
            'time_out' => '16:00',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'MADG',
            'description' => 'Manual holiday group',
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-MAD-1',
            'first_name' => 'Lito',
            'last_name' => 'Late',
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $employment = $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'is_fixed_rate' => false,
            'is_above_minimum_wage_earner' => true,
        ]);

        $salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 8000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $this->calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 921,
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $this->batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $this->calendar->payroll_calendar_id,
            'batch_no' => 921,
            'created_by_id' => $this->user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $this->detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $this->batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);
        $this->actingAs($this->user);
    }

    private function typeId(string $code): int
    {
        return (int) DeductionType::query()->where('deduction_type_code', $code)->value('deduction_type_id');
    }

    private function url(string $suffix): string
    {
        return '/payroll/transaction/batches/'.$this->batch->payroll_batch_id
            .'/employees/'.$this->detail->payroll_batch_detail_id.$suffix;
    }

    private function manualLine(string $code): ?PayrollDeduction
    {
        return PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('deduction_type_id', $this->typeId($code))
            ->where('is_manual', true)
            ->first();
    }

    #[Test]
    public function preview_computes_hours_from_time_and_amount_from_hourly_rate(): void
    {
        $late = $this->getJson($this->url('/deductions/attendance-preview?'.http_build_query([
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
            'time' => '08:30',
        ])))->assertOk()->json();

        $this->assertTrue($late['ok']);
        $this->assertEqualsWithDelta(0.5, $late['computed_hours'], 0.0001);
        $this->assertGreaterThan(0, $late['hourly_rate']);
        $this->assertEqualsWithDelta(round(0.5 * $late['hourly_rate'], 2), $late['amount'], 0.001);

        $override = $this->getJson($this->url('/deductions/attendance-preview?'.http_build_query([
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
            'time' => '08:30',
            'hours' => 2,
        ])))->assertOk()->json();
        $this->assertEqualsWithDelta(round(2 * $late['hourly_rate'], 2), $override['amount'], 0.001);

        $undertime = $this->getJson($this->url('/deductions/attendance-preview?'.http_build_query([
            'deduction_type_id' => $this->typeId('UTDE'),
            'reference_date' => '2026-06-17',
            'time' => '15:00',
        ])))->assertOk()->json();
        $this->assertEqualsWithDelta(1.0, $undertime['computed_hours'], 0.0001);
    }

    #[Test]
    public function manual_late_line_requires_date_and_time_and_auto_computes_amount(): void
    {
        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('LTDE'),
            'hours' => 1,
            'employee_amount' => 99999,
        ])->assertSessionHasErrors();
        $this->assertNull($this->manualLine('LTDE'));

        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
            'employee_amount' => 99999,
        ])->assertSessionHasErrors();
        $this->assertNull($this->manualLine('LTDE'));

        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
            'time_in' => '08:45',
            'employee_amount' => 99999,
            'employer_amount' => 50,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $line = $this->manualLine('LTDE');
        $this->assertNotNull($line);
        $this->assertSame('08:45', $line->time_in);
        $this->assertNull($line->time_out);
        $this->assertEqualsWithDelta(0.75, (float) $line->hours, 0.0001);
        $this->assertSame(0.0, (float) $line->employer_amount);
        $this->assertNotEquals(99999.0, (float) $line->employee_amount);

        $rate = $this->getJson($this->url('/deductions/attendance-preview?'.http_build_query([
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
        ])))->json('hourly_rate');
        $this->assertEqualsWithDelta(round(0.75 * $rate, 2), (float) $line->employee_amount, 0.001);
    }

    #[Test]
    public function manual_late_line_appears_in_late_day_details_modal(): void
    {
        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('LTDE'),
            'reference_date' => '2026-06-17',
            'time_in' => '08:40',
        ])->assertSessionHas('success');

        $breakdown = app(\App\Services\PayrollAttendanceDayBreakdownService::class)->forDetail($this->detail->fresh());
        $this->assertCount(1, $breakdown['LTDE']);
        $this->assertSame('2026-06-17', $breakdown['LTDE'][0]['work_date']);
        $this->assertSame('08:40', $breakdown['LTDE'][0]['time_in']);
        $this->assertSame(40, $breakdown['LTDE'][0]['minutes']);
        $this->assertSame('Manual', $breakdown['LTDE'][0]['source']);
        $this->assertSame('MAD8', $breakdown['LTDE'][0]['shift_code']);

        $this->get(route('payroll.transaction.tab', [
            'tab' => 'batches',
            'view_payroll_batch' => $this->batch->payroll_batch_id,
            'view_batch_detail' => $this->detail->payroll_batch_detail_id,
        ]))->assertOk()
            ->assertSee('data-modal-open="payroll-batch-late-breakdown-modal"', false)
            ->assertSee('Jun 17, 2026');
    }

    #[Test]
    public function basic_income_from_biometric_logs_shows_worked_days(): void
    {
        $transaction = \App\Models\RawTimekeepingTransaction::query()->create([
            'batch_no' => 9921,
            'timekeeping_transaction_type_id' => 1,
            'dt_from' => '2026-06-16',
            'dt_to' => '2026-06-30',
            'uploaded_by_id' => $this->user->id,
        ]);

        foreach (['2026-06-17', '2026-06-18'] as $date) {
            foreach ([['08:00:00', true], ['16:00:00', false]] as [$time, $isIn]) {
                \App\Models\RawTimekeepingInandout::query()->create([
                    'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                    'employee_id' => $this->detail->employee_id,
                    'dt_datetime' => $date.' '.$time,
                    'is_in' => $isIn,
                    'timekeeping_trantype' => 1,
                ]);
            }
        }

        $service = app(PayrollBatchService::class);
        $service->clearDetailTransactions($this->detail, preserveManualLines: true);
        $service->prepareDetailTransactions($this->detail->fresh());

        $basic = \App\Models\PayrollIncome::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'))
            ->firstOrFail();

        $this->assertGreaterThan(0, (float) $basic->hours);
        $this->assertSame(2.0, (float) $basic->days);
    }

    #[Test]
    public function overtime_autofill_follows_policy_before_and_after_shift_settings(): void
    {
        $transaction = \App\Models\RawTimekeepingTransaction::query()->create([
            'batch_no' => 9922,
            'timekeeping_transaction_type_id' => 1,
            'dt_from' => '2026-06-16',
            'dt_to' => '2026-06-30',
            'uploaded_by_id' => $this->user->id,
        ]);

        foreach ([['2026-06-19 07:00:00', true], ['2026-06-19 18:00:00', false]] as [$datetime, $isIn]) {
            \App\Models\RawTimekeepingInandout::query()->create([
                'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
                'employee_id' => $this->detail->employee_id,
                'dt_datetime' => $datetime,
                'is_in' => $isIn,
                'timekeeping_trantype' => 1,
            ]);
        }

        $policy = $this->detail->employee->timekeepingSetup->policy;
        $previewUrl = $this->url('/overtime-approvals/preview?work_date=2026-06-19');

        $both = $this->getJson($previewUrl)->assertOk()->json();
        $this->assertSame(['07:00', '18:00'], [$both['suggested_ot_start'], $both['suggested_ot_end']]);
        $this->assertSame(180, $both['autofill_minutes']);

        $policy->update(['is_ot_autofill_before_shift' => false, 'is_ot_autofill_after_shift' => true]);
        $after = $this->getJson($previewUrl)->assertOk()->json();
        $this->assertSame(['16:00', '18:00'], [$after['suggested_ot_start'], $after['suggested_ot_end']]);
        $this->assertSame(120, $after['autofill_minutes']);
        $this->assertSame(180, $after['excess_minutes']);

        $policy->update(['is_ot_autofill_before_shift' => true, 'is_ot_autofill_after_shift' => false]);
        $before = $this->getJson($previewUrl)->assertOk()->json();
        $this->assertSame(['07:00', '08:00'], [$before['suggested_ot_start'], $before['suggested_ot_end']]);
        $this->assertSame(60, $before['autofill_minutes']);
    }

    #[Test]
    public function policy_general_settings_save_overtime_autofill_sides(): void
    {
        $policy = $this->detail->employee->timekeepingSetup->policy;
        $url = route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'general']);
        $base = [
            'enable_attendance_approval' => 1,
            'buffer_time_in' => 4,
            'buffer_time_out' => 10,
            'enable_employee_validation_for_rest_days' => 0,
        ];

        $this->put($url, $base + ['is_ot_autofill_before_shift' => 0, 'is_ot_autofill_after_shift' => 0])
            ->assertSessionHasErrors('is_ot_autofill_after_shift');

        $this->put($url, $base + ['is_ot_autofill_before_shift' => 0, 'is_ot_autofill_after_shift' => 1])
            ->assertSessionHasNoErrors();
        $policy->refresh();
        $this->assertFalse($policy->is_ot_autofill_before_shift);
        $this->assertTrue($policy->is_ot_autofill_after_shift);

        $this->get(route(\App\Support\TimekeepingPolicy::routeName('tab'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'general']))
            ->assertOk()
            ->assertSee('Overtime auto-fill')
            ->assertSee('name="is_ot_autofill_before_shift"', false);
    }

    #[Test]
    public function manual_undertime_line_uses_time_out_and_typed_hours_override(): void
    {
        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('UTDE'),
            'reference_date' => '2026-06-18',
            'time_in' => '08:00',
        ])->assertSessionHasErrors();

        $this->post($this->url('/deductions'), [
            'deduction_type_id' => $this->typeId('UTDE'),
            'reference_date' => '2026-06-18',
            'time_out' => '15:00',
            'hours' => 1.5,
        ])->assertSessionHas('success');

        $line = $this->manualLine('UTDE');
        $this->assertNotNull($line);
        $this->assertSame('15:00', $line->time_out);
        $this->assertEqualsWithDelta(1.5, (float) $line->hours, 0.0001);
        $this->assertGreaterThan(0, (float) $line->employee_amount);
    }

    #[Test]
    public function deductions_upload_requires_date_and_time_and_auto_computes_late_and_undertime(): void
    {
        $uploads = app(PayrollTransactionUploadService::class);
        $header = implode(',', $uploads->fieldAliases('deductions'));
        $content = implode("\n", [
            $header,
            'EMP-MAD-1,Lito Late,LTDE,,,99999,10,,,06/17/2026,08:30,',
            'EMP-MAD-1,Lito Late,UTDE,,,,,,,06/18/2026,,15:00',
            'EMP-MAD-1,Lito Late,LTDE,1,,,,,,,08:30,',
            'EMP-MAD-1,Lito Late,UTDE,1,,,,,,06/18/2026,,',
        ])."\n";

        $file = UploadedFile::fake()->createWithContent('deductions.csv', $content);
        $result = $uploads->parseUploadedFile($file, 'deductions', $this->calendar);

        $this->assertSame(2, $result['valid_count'], implode(' | ', $result['errors']));
        $this->assertSame(2, $result['error_count']);

        $late = $result['valid'][0];
        $this->assertEqualsWithDelta(0.5, (float) $late['hours'], 0.0001);
        $this->assertSame('08:30', $late['time_in']);
        $this->assertSame(0.0, (float) $late['empr_amount']);
        $this->assertGreaterThan(0, (float) $late['emp_amount']);
        $this->assertNotEquals(99999.0, (float) $late['emp_amount']);

        $undertime = $result['valid'][1];
        $this->assertEqualsWithDelta(1.0, (float) $undertime['hours'], 0.0001);
        $this->assertSame('15:00', $undertime['time_out']);

        $token = $uploads->createStagingToken($this->user, 'deductions', $this->calendar->payroll_calendar_id, $result);
        $transaction = $uploads->commit($this->user, $token);

        $records = $transaction->deductionRecords()->orderBy('payroll_deduction_id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('08:30', $records[0]->time_in);
        $this->assertSame('15:00', $records[1]->time_out);
    }
}
