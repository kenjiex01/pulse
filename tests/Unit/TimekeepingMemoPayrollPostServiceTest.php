<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use App\Services\TimekeepingMemoAttendanceService;
use App\Services\TimekeepingMemoPayrollPostService;
use App\Services\TimekeepingMemoSendService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TimekeepingMemoPayrollPostServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sends_memo_when_violation_count_meets_setup_threshold(): void
    {
        [$batch, $employee, $user] = $this->makePostedBatchContext();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'payroll_post_late_memo',
            'name' => 'Payroll Post Late Memo',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        TimekeepingMemoSetup::query()->where('violation_type', 'late')->update([
            'company_document_form_id' => $form->company_document_form_id,
            'occurrence_count' => 3,
            'email_subject' => 'Late memo',
            'email_body' => 'Body',
        ]);

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('violationCountForEmployee')
                ->andReturnUsing(fn ($employee, $from, $to, $type) => $type === 'late' ? 3 : 0);
        });

        $this->mock(TimekeepingMemoSendService::class, function ($mock) use ($employee, $user): void {
            $mock->shouldReceive('sendForEmployee')
                ->once()
                ->with(
                    Mockery::on(fn ($value) => $value instanceof Employee && $value->employee_id === $employee->employee_id),
                    '2026-06-16',
                    '2026-06-30',
                    'late',
                    null,
                    Mockery::on(fn ($value) => $value instanceof User && $value->id === $user->id),
                )
                ->andReturn(['submission_id' => 1, 'sent_dates' => ['2026-06-17']]);
        });

        $summary = app(TimekeepingMemoPayrollPostService::class)->sendForPostedBatch($batch, $user);

        $this->assertSame(1, $summary['sent']);
        $this->assertSame([], $summary['errors']);
    }

    public function test_skips_memo_when_violation_count_is_below_setup_threshold(): void
    {
        [$batch, , $user] = $this->makePostedBatchContext();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'payroll_post_late_memo_skip',
            'name' => 'Payroll Post Late Memo Skip',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        TimekeepingMemoSetup::query()->where('violation_type', 'late')->update([
            'company_document_form_id' => $form->company_document_form_id,
            'occurrence_count' => 3,
            'email_subject' => 'Late memo',
            'email_body' => 'Body',
        ]);

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('violationCountForEmployee')->andReturn(2);
        });

        $this->mock(TimekeepingMemoSendService::class, function ($mock): void {
            $mock->shouldNotReceive('sendForEmployee');
        });

        $summary = app(TimekeepingMemoPayrollPostService::class)->sendForPostedBatch($batch, $user);

        $this->assertSame(0, $summary['sent']);
        $this->assertSame([], $summary['errors']);
    }

    public function test_sends_each_configured_violation_type_independently(): void
    {
        [$batch, $employee, $user] = $this->makePostedBatchContext();

        $form = CompanyDocumentForm::query()->create([
            'code' => 'payroll_post_all_memos',
            'name' => 'Payroll Post All Memos',
            'document_type' => CompanyDocumentForm::TYPE_MEMO,
            'is_active' => true,
            'version' => 1,
        ]);

        foreach (['late', 'undertime', 'absent'] as $type) {
            TimekeepingMemoSetup::query()->where('violation_type', $type)->update([
                'company_document_form_id' => $form->company_document_form_id,
                'occurrence_count' => 1,
                'email_subject' => ucfirst($type).' memo',
                'email_body' => 'Body',
            ]);
        }

        $this->mock(TimekeepingMemoAttendanceService::class, function ($mock): void {
            $mock->shouldReceive('violationCountForEmployee')->andReturn(1);
        });

        $this->mock(TimekeepingMemoSendService::class, function ($mock): void {
            $mock->shouldReceive('sendForEmployee')->times(3)->andReturn(['submission_id' => 1, 'sent_dates' => ['2026-06-17']]);
        });

        $summary = app(TimekeepingMemoPayrollPostService::class)->sendForPostedBatch($batch, $user);

        $this->assertSame(3, $summary['sent']);
    }

    /**
     * @return array{0: PayrollBatch, 1: Employee, 2: User}
     */
    private function makePostedBatchContext(): array
    {
        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'MEMO-POST-'.uniqid(),
            'first_name' => 'Memo',
            'last_name' => 'Post',
            'email' => 'memo.post.'.uniqid().'@example.com',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => random_int(100, 900),
            'dt_from' => '2026-06-16 00:00:00',
            'dt_to' => '2026-06-30 00:00:00',
            'calendar_month' => 6,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => random_int(100, 900),
            'created_by_id' => $user->id,
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
        ]);

        PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        return [$batch->fresh(['payrollCalendar', 'details.employee']), $employee, $user];
    }
}
