<?php

namespace Tests\Feature;

use App\Models\PayrollBatch;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\SysLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollBatchPurgeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();
    }

    #[Test]
    public function user_can_purge_pending_payroll_batches(): void
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 902,
            'dt_from' => '2026-07-01 00:00:00',
            'dt_to' => '2026-07-15 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 9099,
            'created_by_id' => $this->user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PENDING,
            'withholding_tax_computation_id' => 1,
        ]);

        $response = $this->actingAs($this->user)->delete(route('payroll.transaction.batches.destroy'), [
            'selected_ids' => [$batch->payroll_batch_id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('trn_payroll_batches', [
            'payroll_batch_id' => $batch->payroll_batch_id,
        ]);

        $this->assertTrue(
            SysLog::query()
                ->where('table_name', 'trn_payroll_batches')
                ->where('action', 'delete')
                ->exists()
        );
    }

    #[Test]
    public function posted_payroll_batches_cannot_be_purged(): void
    {
        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 903,
            'dt_from' => '2026-07-16 00:00:00',
            'dt_to' => '2026-07-31 00:00:00',
            'calendar_month' => 7,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 9100,
            'created_by_id' => $this->user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::POSTED,
            'withholding_tax_computation_id' => 1,
        ]);

        $response = $this->actingAs($this->user)->delete(route('payroll.transaction.batches.destroy'), [
            'selected_ids' => [$batch->payroll_batch_id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('trn_payroll_batches', [
            'payroll_batch_id' => $batch->payroll_batch_id,
            'deleted_at' => null,
        ]);
    }
}
