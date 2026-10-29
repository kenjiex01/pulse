<?php

namespace Tests\Feature;

use App\Models\BasicComputation;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Models\IncomeType;
use App\Models\PayrollAttendanceDay;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollDeduction;
use App\Models\PayrollDispute;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\RateGroup;
use App\Models\ShiftCode;
use App\Models\SysLog;
use App\Models\TimekeepingEmployeeSetup;
use App\Models\TimekeepingHolidayGroup;
use App\Models\User;
use App\Services\PayrollBatchService;
use App\Services\PayrollDisputeService;
use App\Services\PayrollOvertimeService;
use App\Services\PayrollTransactionUploadService;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollDisputeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private EmployeeSalary $salary;

    private PayrollCalendar $currentCalendar;

    private PayrollBatch $postedBatch;

    private PayrollBatchDetail $postedDetail;

    private PayrollBatch $batch;

    private PayrollBatchDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();
        $this->buildScenario();
    }

    private function buildScenario(): void
    {
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults([
            'policy_code' => 'DSPT',
            'policy_name' => 'Dispute Policy',
            'description' => 'Dispute tests',
            'is_active' => true,
            'grace_period' => 0,
        ]);

        $shift = ShiftCode::query()->create([
            'shift_code' => 'DSP8',
            'description' => 'Dispute 8h',
            'time_in' => '08:00',
            'time_out' => '16:00',
            'is_flexi_time' => false,
        ]);

        $holidayGroup = TimekeepingHolidayGroup::query()->create([
            'timekeeping_holiday_group_code' => 'DSPG',
            'description' => 'Dispute holiday group',
        ]);

        $this->employee = Employee::query()->create([
            'employee_number' => 'EMP-DSP-1',
            'first_name' => 'Dina',
            'last_name' => 'Disputa',
            'email' => 'dispute@example.com',
        ]);

        TimekeepingEmployeeSetup::query()->create([
            'employee_id' => $this->employee->employee_id,
            'timekeeping_holiday_group_id' => $holidayGroup->timekeeping_holiday_group_id,
            'shift_code_id' => $shift->shift_code_id,
            'timekeeping_policy_id' => $policy->timekeeping_policy_id,
        ]);

        $employment = $this->employee->employmentInformations()->create([
            'employee_id' => $this->employee->employee_id,
            'user_type' => EmployeeEmploymentInformation::TYPE_STAFF,
            'sort_order' => 1,
        ]);

        $this->salary = $employment->salaries()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'basic_computation_id' => BasicComputation::TIME_IN_OUT,
            'rate_group_id' => RateGroup::query()->value('rate_group_id'),
            'days_per_period' => 10,
            'hours_per_day' => 8,
            'date_effective_from' => '2026-01-01',
            'date_effective_to' => null,
            'is_fixed_rate' => false,
            'is_above_minimum_wage_earner' => true,
        ]);

        $this->salary->incomes()->create([
            'income_type_id' => IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 8000,
            'non_taxable' => 0,
            'sort_order' => 0,
        ]);

        $previousCalendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 811,
            'dt_from' => '2026-06-01 00:00:00',
            'dt_to' => '2026-06-15 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $this->currentCalendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 812,
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $this->postedBatch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $previousCalendar->payroll_calendar_id,
            'batch_no' => 801,
            'created_by_id' => $this->user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
        ]);

        $this->postedDetail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $this->postedBatch->payroll_batch_id,
            'employee_id' => $this->employee->employee_id,
        ]);

        // Mon 06-08: worked 8h, no OT paid (UOT). Tue 06-09: absent (UPD).
        // Wed 06-10: 60 min late (ODL). Thu 06-11: 60 min undertime (ODU).
        $this->snapshot('2026-06-08', basic: 8, timeIn: '08:00:00', timeOut: '18:00:00');
        $this->snapshot('2026-06-10', basic: 7, late: 1, timeIn: '09:00:00', timeOut: '16:00:00');
        $this->snapshot('2026-06-11', basic: 7, undertime: 1, timeIn: '08:00:00', timeOut: '15:00:00');

        $this->batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $this->currentCalendar->payroll_calendar_id,
            'batch_no' => 802,
            'created_by_id' => $this->user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
        ]);

        $this->detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $this->batch->payroll_batch_id,
            'employee_id' => $this->employee->employee_id,
        ]);
    }

    private function snapshot(string $date, float $basic, float $late = 0, float $undertime = 0, float $ot = 0, ?string $timeIn = null, ?string $timeOut = null): void
    {
        PayrollAttendanceDay::query()->create([
            'payroll_batch_detail_id' => $this->postedDetail->payroll_batch_detail_id,
            'employee_id' => $this->employee->employee_id,
            'work_date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'basic' => $basic,
            'ot' => $ot,
            'late' => $late,
            'undertime' => $undertime,
        ]);
    }

    private function rate(): float
    {
        return (float) $this->salary->fresh()->hourlyRate();
    }

    private function service(): PayrollDisputeService
    {
        return app(PayrollDisputeService::class);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fileDispute(array $input): PayrollDispute
    {
        $payload = $this->service()->validateForStore($this->detail, $input);

        return PayrollDispute::query()->create([
            ...$payload['attributes'],
            'payroll_batch_id' => $this->batch->payroll_batch_id,
            'payroll_batch_detail_id' => $this->detail->payroll_batch_detail_id,
            'employee_id' => $this->employee->employee_id,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
    }

    private function incomeFor(string $code): ?PayrollIncome
    {
        return PayrollIncome::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', $code)->value('income_type_id'))
            ->first();
    }

    private function batchUrl(string $suffix = ''): string
    {
        return '/payroll/transaction/batches/'.$this->batch->payroll_batch_id
            .'/employees/'.$this->detail->payroll_batch_detail_id.'/disputes'.$suffix;
    }

    #[Test]
    public function unpaid_days_pays_corrected_basic_and_ot_as_two_lines(): void
    {
        $dispute = $this->fileDispute([
            'category' => 'UPD',
            'work_date' => '2026-06-09',
            'time_in' => '08:00',
            'time_out' => '18:00',
            'remarks' => 'Biometric down',
        ]);

        $rate = $this->rate();
        $this->assertEqualsWithDelta(round(8 * $rate, 2), (float) $dispute->expected_pay, 0.01);
        $this->assertEqualsWithDelta(round(2 * $rate, 2), (float) $dispute->expected_ot_pay, 0.01);

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);

        $basic = $this->incomeFor('DUPD');
        $ot = $this->incomeFor('DUPO');

        $this->assertNotNull($basic);
        $this->assertNotNull($ot);
        $this->assertEqualsWithDelta((float) $dispute->expected_pay, (float) $basic->taxable, 0.01);
        $this->assertEqualsWithDelta((float) $dispute->expected_ot_pay, (float) $ot->taxable, 0.01);
        $this->assertSame('Jun 9, 2026', $basic->remarks);

        $dispute->refresh();
        $this->assertFalse($dispute->changedAfterFiling());
        $this->assertSame((int) $basic->payroll_income_id, (int) $dispute->payroll_income_id);
    }

    #[Test]
    public function unpaid_overtime_late_and_undertime_compute_against_previous_batch(): void
    {
        $rate = $this->rate();

        $uot = $this->fileDispute(['category' => 'UOT', 'work_date' => '2026-06-08', 'ot_start' => '16:00', 'ot_end' => '18:00']);
        $odl = $this->fileDispute(['category' => 'ODL', 'work_date' => '2026-06-10', 'time_in' => '08:00']);
        $odu = $this->fileDispute(['category' => 'ODU', 'work_date' => '2026-06-11', 'time_in' => '08:00', 'time_out' => '16:00']);

        $this->assertEqualsWithDelta(round(2 * $rate, 2), $uot->expectedTotal(), 0.01);
        $this->assertEqualsWithDelta(round(1 * $rate, 2), $odl->expectedTotal(), 0.01);
        $this->assertEqualsWithDelta(round(1 * $rate, 2), $odu->expectedTotal(), 0.01);

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);

        $this->assertEqualsWithDelta($uot->expectedTotal(), (float) $this->incomeFor('DUOT')?->taxable, 0.01);
        $this->assertEqualsWithDelta($odl->expectedTotal(), (float) $this->incomeFor('DODL')?->taxable, 0.01);
        $this->assertEqualsWithDelta($odu->expectedTotal(), (float) $this->incomeFor('DODU')?->taxable, 0.01);
    }

    #[Test]
    public function reprocess_does_not_duplicate_lines_and_zero_difference_creates_no_line(): void
    {
        $this->fileDispute(['category' => 'UPD', 'work_date' => '2026-06-09', 'time_in' => '08:00', 'time_out' => '16:00']);
        $zero = $this->fileDispute(['category' => 'ODL', 'work_date' => '2026-06-10', 'time_in' => '09:00']);

        $this->assertSame(0.0, $zero->expectedTotal());

        $service = app(PayrollBatchService::class);
        $service->prepareDetailTransactions($this->detail);
        $service->clearDetailTransactions($this->detail, preserveManualLines: true);
        $service->prepareDetailTransactions($this->detail);

        $this->assertSame(1, PayrollIncome::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('income_type_id', IncomeType::query()->where('income_type_code', 'DUPD')->value('income_type_id'))
            ->count());
        $this->assertNull($this->incomeFor('DODL'));
    }

    #[Test]
    public function over_payment_days_deducts_overpaid_basic(): void
    {
        $rate = $this->rate();

        $absent = $this->fileDispute(['category' => 'OPD', 'work_date' => '2026-06-08']);
        $partial = $this->fileDispute(['category' => 'OPD', 'work_date' => '2026-06-11', 'time_in' => '08:00', 'time_out' => '12:00']);

        $this->assertEqualsWithDelta(round(8 * $rate, 2), $absent->expectedTotal(), 0.01);
        $this->assertEqualsWithDelta(-round(8 * $rate, 2), $absent->signedExpectedTotal(), 0.01);
        $this->assertNull($absent->time_in);
        $this->assertGreaterThan(0, $partial->expectedTotal());
        $this->assertLessThan(round(7 * $rate, 2), $partial->expectedTotal());

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);

        $lines = PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('deduction_type_id', DeductionType::query()->where('deduction_type_code', 'DOPD')->value('deduction_type_id'))
            ->get();

        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta($absent->expectedTotal() + $partial->expectedTotal(), (float) $lines->sum('employee_amount'), 0.01);
        $this->assertFalse($absent->fresh()->changedAfterFiling());
        $this->assertNull($this->incomeFor('DUPD'));

        $service = app(PayrollBatchService::class);
        $service->clearDetailTransactions($this->detail, preserveManualLines: true);
        $service->prepareDetailTransactions($this->detail);
        $this->assertSame(2, PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('deduction_type_id', DeductionType::query()->where('deduction_type_code', 'DOPD')->value('deduction_type_id'))
            ->count());
    }

    #[Test]
    public function over_payment_overtime_deducts_overpaid_ot(): void
    {
        $this->snapshot('2026-06-12', basic: 8, ot: 2, timeIn: '08:00:00', timeOut: '18:00:00');
        $otPay = app(PayrollOvertimeService::class);
        $salary = $this->salary->fresh();

        $partial = $this->fileDispute(['category' => 'OPO', 'work_date' => '2026-06-12', 'ot_start' => '16:00', 'ot_end' => '17:00']);
        $this->assertEqualsWithDelta($otPay->overtimePay($salary, 60), $partial->expectedTotal(), 0.01);

        $partial->delete();
        $none = $this->fileDispute(['category' => 'OPO', 'work_date' => '2026-06-12']);
        $this->assertEqualsWithDelta($otPay->overtimePay($salary, 120), $none->expectedTotal(), 0.01);

        app(PayrollBatchService::class)->prepareDetailTransactions($this->detail);

        $line = PayrollDeduction::query()
            ->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)
            ->where('deduction_type_id', DeductionType::query()->where('deduction_type_code', 'DOPO')->value('deduction_type_id'))
            ->first();
        $this->assertNotNull($line);
        $this->assertEqualsWithDelta($none->expectedTotal(), (float) $line->employee_amount, 0.01);
    }

    #[Test]
    public function over_payment_conflicts_with_unpaid_on_same_day_and_requires_both_times(): void
    {
        $this->fileDispute(['category' => 'UPD', 'work_date' => '2026-06-09', 'time_in' => '08:00', 'time_out' => '16:00']);

        try {
            $this->fileDispute(['category' => 'OPD', 'work_date' => '2026-06-09']);
            $this->fail('Expected conflict with Unpaid Days.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('work_date', $exception->errors());
        }

        try {
            $this->fileDispute(['category' => 'OPD', 'work_date' => '2026-06-08', 'time_in' => '08:00']);
            $this->fail('Expected Time Out to be required.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('time_out', $exception->errors());
        }

        $this->actingAs($this->user);
        $prefill = $this->getJson($this->batchUrl('/prefill').'?work_date=2026-06-08&category=OPD')->assertOk()->json();
        $this->assertTrue($prefill['ok']);
        $this->assertSame(8.0, (float) $prefill['paid']['basic_hours']);
    }

    #[Test]
    public function store_prefill_preview_duplicate_and_soft_delete_via_http(): void
    {
        $this->actingAs($this->user);

        $prefill = $this->getJson($this->batchUrl('/prefill').'?work_date=2026-06-11&category=ODU')
            ->assertOk()
            ->json();

        $this->assertTrue($prefill['ok']);
        $this->assertSame('08:00', $prefill['values']['time_in']);
        $this->assertSame('15:00', $prefill['values']['time_out'] ?? '15:00');
        $this->assertSame(60, $prefill['paid']['undertime_minutes']);
        $this->assertNull($prefill['already_filed']);

        $preview = $this->postJson($this->batchUrl('/preview'), [
            'category' => 'ODU',
            'work_date' => '2026-06-11',
            'time_in' => '08:00',
            'time_out' => '16:00',
        ])->assertOk()->json();

        $this->assertTrue($preview['ok']);
        $this->assertEqualsWithDelta(round($this->rate(), 2), (float) $preview['total'], 0.01);

        $this->post($this->batchUrl(), [
            'category' => 'ODU',
            'work_date' => '2026-06-11',
            'time_in' => '08:00',
            'time_out' => '16:00',
            'remarks' => 'Approved by HR',
        ])->assertRedirect()->assertSessionHas('success');

        $dispute = PayrollDispute::query()->firstOrFail();
        $this->assertEqualsWithDelta((float) $preview['total'], $dispute->expectedTotal(), 0.01);
        $this->assertTrue(SysLog::query()->where('table_name', 'trn_payroll_disputes')->where('action', 'create')->exists());

        $again = $this->getJson($this->batchUrl('/prefill').'?work_date=2026-06-11&category=ODU')->json();
        $this->assertNotNull($again['already_filed']);

        $this->post($this->batchUrl(), [
            'category' => 'ODU',
            'work_date' => '2026-06-11',
            'time_in' => '08:00',
            'time_out' => '16:00',
        ])->assertSessionHasErrors('work_date');
        $this->assertSame(1, PayrollDispute::query()->count());

        $this->put($this->batchUrl('/'.$dispute->payroll_dispute_id), [
            'category' => 'ODU',
            'work_date' => '2026-06-11',
            'time_in' => '08:00',
            'time_out' => '15:30',
        ])->assertSessionHas('success');
        $this->assertEqualsWithDelta(round(0.5 * $this->rate(), 2), $dispute->fresh()->expectedTotal(), 0.01);

        $this->get(route('payroll.transaction.tab', [
            'tab' => 'batches',
            'view_payroll_batch' => $this->batch->payroll_batch_id,
            'view_batch_detail' => $this->detail->payroll_batch_detail_id,
            'batch_detail_tab' => 'disputes',
        ]))->assertOk()
            ->assertSee('File Dispute')
            ->assertSee('Over Deduction - Undertime')
            ->assertSee('payroll-batch-dispute-edit-modal-'.$dispute->payroll_dispute_id, false);

        $this->delete($this->batchUrl('/'.$dispute->payroll_dispute_id))->assertSessionHas('success');
        $this->assertSoftDeleted('trn_payroll_disputes', ['payroll_dispute_id' => $dispute->payroll_dispute_id]);
    }

    #[Test]
    public function unpaid_days_with_ot_blocks_unpaid_overtime_on_same_date(): void
    {
        $this->fileDispute(['category' => 'UPD', 'work_date' => '2026-06-09', 'time_in' => '08:00', 'time_out' => '18:00']);

        $conflict = $this->service()->conflictFor($this->detail, '2026-06-09', 'UOT');
        $this->assertNotNull($conflict);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->fileDispute(['category' => 'UOT', 'work_date' => '2026-06-09', 'ot_start' => '16:00', 'ot_end' => '18:00']);
    }

    #[Test]
    public function work_date_must_be_before_period_and_batch_must_be_open(): void
    {
        try {
            $this->fileDispute(['category' => 'UPD', 'work_date' => '2026-06-17', 'time_in' => '08:00', 'time_out' => '16:00']);
            $this->fail('Expected validation error for a date inside the current period.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('work_date', $exception->errors());
        }

        try {
            $this->fileDispute(['category' => 'UPD', 'work_date' => '2026-05-20', 'time_in' => '08:00', 'time_out' => '16:00']);
            $this->fail('Expected validation error for a date with no posted batch.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('work_date', $exception->errors());
        }

        $this->batch->update(['payroll_batch_status_id' => PayrollBatchStatus::POSTED]);
        $this->actingAs($this->user)
            ->post($this->batchUrl(), ['category' => 'UPD', 'work_date' => '2026-06-09', 'time_in' => '08:00', 'time_out' => '16:00'])
            ->assertSessionHas('error');
        $this->assertSame(0, PayrollDispute::query()->count());
    }

    #[Test]
    public function upload_files_disputes_with_autofill_and_reports_row_errors(): void
    {
        $uploads = app(PayrollTransactionUploadService::class);
        $header = implode(',', $uploads->fieldAliases('disputes'));
        $content = implode("\n", [
            $header,
            'EMP-DSP-1,Dina Disputa,UPD,06/09/2026,,08:00,16:00,,,Uploaded',
            'EMP-DSP-1,Dina Disputa,ODL,06/10/2026,,8:00 AM,,,,',
            'EMP-DSP-1,Dina Disputa,UPD,06/09/2026,,08:00,16:00,,,dup in file',
            'EMP-DSP-1,Dina Disputa,XXX,06/11/2026,,,,,,',
            'NOPE,Nobody,UPD,06/09/2026,,08:00,16:00,,,',
        ])."\n";

        $file = UploadedFile::fake()->createWithContent('disputes.csv', $content);
        $result = $uploads->parseUploadedFile($file, 'disputes', $this->currentCalendar);

        $this->assertSame(2, $result['valid_count'], implode(' | ', $result['errors']));
        $this->assertSame(3, $result['error_count']);
        $this->assertArrayHasKey('expected_pay', $result['valid'][0]);

        $token = $uploads->createStagingToken($this->user, 'disputes', $this->currentCalendar->payroll_calendar_id, $result);
        $transaction = $uploads->commit($this->user, $token);

        $this->assertSame(2, $transaction->disputeRecords()->count());
        $this->assertSame(8, (int) $transaction->payroll_transaction_type_id);
        $this->assertSame(2, PayrollDispute::query()->where('payroll_batch_detail_id', $this->detail->payroll_batch_detail_id)->count());
        $this->assertTrue(SysLog::query()->where('table_name', 'trn_payroll_disputes')->exists());
    }
}
